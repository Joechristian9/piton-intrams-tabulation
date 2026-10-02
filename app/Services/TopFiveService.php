<?php

namespace App\Services;

use App\Models\TopFiveScore;
use App\Models\Candidate;
use App\Models\TopFiveCandidates;
use App\Models\User;

class TopFiveService
{
    protected $categories = [
        'face_and_figure',
        'delivery',
        'overall_appeal',
    ];

    /**
     * Get results per category for Top Five selection
     */
    public function getResultsPerCategory(string $category)
    {
        $judgeOrder = $this->judgeOrder();

        // Get only top 5 males and females
        [$maleCandidatesList, $femaleCandidatesList] = $this->finalistsByGender();

        // Load all scores
        $scores = TopFiveScore::all()->groupBy('top_five_id');

        $maleCandidates   = $this->processCandidates($maleCandidatesList, $scores, $category, $judgeOrder);
        $femaleCandidates = $this->processCandidates($femaleCandidatesList, $scores, $category, $judgeOrder);

        return [
            'maleCandidates'   => $maleCandidates,
            'femaleCandidates' => $femaleCandidates,
            'judgeOrder'       => $judgeOrder,
        ];
    }

    protected function processCandidates($candidatesList, $scores, $category, $judgeOrder)
    {
        $processed = [];
        $count = 0;

        foreach ($candidatesList as $item) {
            $count++;
            $candidate = $item['candidate'];
            $topFiveId = $item['top_five_id'];

            // Initialize scores for each judge
            $candidateScores = array_fill_keys(array_column($judgeOrder, 'id'), 0);

            // Fill in scores from DB using top_five_id
            $candidateScoresFromDB = $scores[$topFiveId] ?? collect();
            foreach ($candidateScoresFromDB as $score) {
                if (array_key_exists($score->judge_id, $candidateScores)) {
                    $candidateScores[$score->judge_id] = $score->{$category} ?? 0;
                }
            }

            $processed[] = [
                'candidate'        => $candidate,
                'scores'           => $candidateScores,
                'total'            => round(array_sum($candidateScores) / max(1, count($judgeOrder)), 2),
                'rank'             => 0,
                'candidate_number' => $count,
            ];
        }

        return $this->assignRanking($processed);
    }

    /**
     * Per-category score = the average of all judges' scores, so each category
     * is out of its own maximum and the round total is out of 100.
     */
    protected function processTotalPerCategory($candidatesList, $scores, int $judgeCount)
    {
        $processed = [];
        $count = 0;

        foreach ($candidatesList as $item) {
            $count++;
            $candidate = $item['candidate'];
            $topFiveId = $item['top_five_id'];

            $categoryTotals = array_fill_keys($this->categories, 0);

            $candidateScores = $scores[$topFiveId] ?? collect();
            foreach ($candidateScores as $score) {
                foreach ($this->categories as $cat) {
                    $categoryTotals[$cat] += $score->{$cat} ?? 0;
                }
            }

            // Average over the whole panel (a judge who hasn't scored yet counts as 0).
            $averages = array_map(fn ($sum) => $sum / max(1, $judgeCount), $categoryTotals);

            $processed[] = [
                'candidate'        => $candidate,
                'scores'           => array_map(fn ($avg) => round($avg, 2), $averages),
                'total'            => round(array_sum($averages), 2),
                'rank'             => 0,
                'candidate_number' => $count,
            ];
        }

        return $this->assignRanking($processed);
    }

    private function assignRanking(array $candidates): array
    {
        usort($candidates, fn($a, $b) => $b['total'] <=> $a['total']);

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

    public function getTotalResults()
    {
        $judgeOrder = $this->judgeOrder();

        [$maleCandidatesList, $femaleCandidatesList] = $this->finalistsByGender();

        $scores = TopFiveScore::all()->groupBy('top_five_id');

        $maleCandidates   = $this->processTotalPerCategory($maleCandidatesList, $scores, count($judgeOrder));
        $femaleCandidates = $this->processTotalPerCategory($femaleCandidatesList, $scores, count($judgeOrder));

        return [
            'maleCandidates'   => $maleCandidates,
            'femaleCandidates' => $femaleCandidates,
            'judgeOrder'       => $judgeOrder,
        ];
    }

    /**
     * Male and female finalists (with their candidate) from a single query.
     */
    private function finalistsByGender(): array
    {
        $byGender = TopFiveCandidates::with('candidate')->get()
            ->filter(fn ($item) => $item->candidate)
            ->map(fn ($item) => ['candidate' => $item->candidate, 'top_five_id' => $item->id])
            ->groupBy(fn ($item) => $item['candidate']->gender);

        return [
            $byGender->get('male', collect())->values(),
            $byGender->get('female', collect())->values(),
        ];
    }

    /**
     * All judges in a stable order; scores are keyed by judge id so renamed
     * or newly added judges still line up with their scores.
     */
    private function judgeOrder(): array
    {
        return User::where('role', 'judge')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn ($judge) => ['id' => $judge->id, 'name' => $judge->name])
            ->all();
    }
}
