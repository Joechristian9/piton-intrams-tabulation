<?php

namespace App\Legacy;

use Illuminate\Support\Facades\DB;

/**
 * A frozen copy of the original single-pageant results (TopFiveSelectionService
 * and TopFiveService) over the old tables, kept so `events:migrate-legacy` can
 * prove Event #1 computes identically. Uses DB::table only, so it keeps working
 * after the old models and services are deleted. Do not "improve" the
 * arithmetic: it must reproduce what the app showed before the migration.
 */
class LegacyResults
{
    /** Old column => Event #1 category (names, max points and order as shown today). */
    public const CATEGORY_MAP = [
        'production_number' => ['round' => 1, 'name' => 'Production Number', 'max' => 10, 'position' => 1],
        'casual_wear' => ['round' => 1, 'name' => 'Sports Wear', 'max' => 25, 'position' => 2],
        'swim_wear' => ['round' => 1, 'name' => 'Swim Wear', 'max' => 25, 'position' => 3],
        'formal_wear' => ['round' => 1, 'name' => 'Formal Wear', 'max' => 25, 'position' => 4],
        'closed_door_interview' => ['round' => 1, 'name' => 'Casual Interview', 'max' => 15, 'position' => 5],
        'face_and_figure' => ['round' => 2, 'name' => 'Beauty of the Face and Figure', 'max' => 50, 'position' => 1],
        'delivery' => ['round' => 2, 'name' => 'Delivery', 'max' => 40, 'position' => 2],
        'overall_appeal' => ['round' => 2, 'name' => 'Over-all Appeal / X-factor', 'max' => 10, 'position' => 3],
    ];

    /**
     * Every results page of the old app, in the Snapshot shape:
     * ['category:{key}' => G, 'round1' => G, 'finals' => G], G = ['female' => Row[], 'male' => Row[]].
     */
    public static function snapshot(): array
    {
        $judgeIds = DB::table('users')->where('role', 'judge')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();

        // Candidates by id, grouped by gender (Candidate::all()->groupBy('gender')).
        $candidates = DB::table('candidates')->orderBy('id')->get(['id', 'gender']);
        $selectionPopulation = [
            'female' => $candidates->where('gender', 'female')->map(fn ($c) => ['id' => (int) $c->id, 'key' => (int) $c->id])->values()->all(),
            'male' => $candidates->where('gender', 'male')->map(fn ($c) => ['id' => (int) $c->id, 'key' => (int) $c->id])->values()->all(),
        ];
        $selectionScores = DB::table('top_five_selection_scores')->orderBy('id')->get()->groupBy('candidate_id');

        // Finalists in top_five_candidates order, keyed by their top_five id.
        $finalists = DB::table('top_five_candidates')
            ->join('candidates', 'candidates.id', '=', 'top_five_candidates.candidate_id')
            ->orderBy('top_five_candidates.id')
            ->get(['top_five_candidates.id as top_five_id', 'candidates.id', 'candidates.gender']);
        $finalsPopulation = [
            'female' => $finalists->where('gender', 'female')->map(fn ($f) => ['id' => (int) $f->id, 'key' => (int) $f->top_five_id])->values()->all(),
            'male' => $finalists->where('gender', 'male')->map(fn ($f) => ['id' => (int) $f->id, 'key' => (int) $f->top_five_id])->values()->all(),
        ];
        $finalsScores = DB::table('top_five_scores')->orderBy('id')->get()->groupBy('top_five_id');

        $snapshot = [];
        foreach (self::CATEGORY_MAP as $key => $meta) {
            [$population, $scores] = $meta['round'] === 1
                ? [$selectionPopulation, $selectionScores]
                : [$finalsPopulation, $finalsScores];

            foreach (['female', 'male'] as $gender) {
                $snapshot["category:{$key}"][$gender] = self::perCategory($population[$gender], $scores, $key, $judgeIds);
            }
        }

        $round1Keys = array_keys(array_filter(self::CATEGORY_MAP, fn ($m) => $m['round'] === 1));
        $finalsKeys = array_keys(array_filter(self::CATEGORY_MAP, fn ($m) => $m['round'] === 2));
        foreach (['female', 'male'] as $gender) {
            $snapshot['round1'][$gender] = self::totals($selectionPopulation[$gender], $selectionScores, $round1Keys, count($judgeIds));
            $snapshot['finals'][$gender] = self::totals($finalsPopulation[$gender], $finalsScores, $finalsKeys, count($judgeIds));
        }

        return $snapshot;
    }

    /** processCandidates(): each judge's score (0 when missing) and their average. */
    private static function perCategory(array $population, $scores, string $category, array $judgeIds): array
    {
        $rows = [];
        foreach ($population as $member) {
            $candidateScores = array_fill_keys($judgeIds, 0);

            foreach ($scores[$member['key']] ?? [] as $score) {
                if (array_key_exists((int) $score->judge_id, $candidateScores)) {
                    $candidateScores[(int) $score->judge_id] = $score->{$category} ?? 0;
                }
            }

            $rows[] = [
                'candidate_id' => $member['id'],
                'scores' => array_map('floatval', $candidateScores),
                'total' => round(array_sum($candidateScores) / max(1, count($judgeIds)), 2),
                'rank' => 0,
            ];
        }

        return self::assignRanking($rows);
    }

    /** processTotalPerCategory(): sum of every score row per category ÷ panel size. */
    private static function totals(array $population, $scores, array $categories, int $judgeCount): array
    {
        $rows = [];
        foreach ($population as $member) {
            $categoryTotals = array_fill_keys($categories, 0);

            foreach ($scores[$member['key']] ?? [] as $score) {
                foreach ($categories as $cat) {
                    $categoryTotals[$cat] += $score->{$cat} ?? 0;
                }
            }

            $averages = array_map(fn ($sum) => $sum / max(1, $judgeCount), $categoryTotals);

            $rows[] = [
                'candidate_id' => $member['id'],
                'scores' => array_map(fn ($avg) => (float) round($avg, 2), $averages),
                'total' => (float) round(array_sum($averages), 2),
                'rank' => 0,
            ];
        }

        return self::assignRanking($rows);
    }

    private static function assignRanking(array $candidates): array
    {
        usort($candidates, fn ($a, $b) => $b['total'] <=> $a['total']);

        $rank = 1;
        $lastTotal = null;

        foreach ($candidates as $index => &$c) {
            if ($lastTotal !== null && $c['total'] === $lastTotal) {
                $c['rank'] = $rank;
            } else {
                $rank = $index + 1;
                $c['rank'] = $rank;
                $lastTotal = $c['total'];
            }
        }

        return $candidates;
    }
}
