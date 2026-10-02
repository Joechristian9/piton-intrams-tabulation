<?php

namespace App\Services;

use App\Models\TopFiveSelectionScore;
use App\Models\Candidate;
use App\Models\User;

class TopFiveSelectionService
{
    protected $categories = [
        'production_number',
        'casual_wear',
        'swim_wear',
        'formal_wear',
        'closed_door_interview',
    ];

    public function getResultsPerCategory(string $category)
    {
        $judgeOrder = $this->judgeOrder();

        // Get all candidates
        $maleCandidatesList = Candidate::where('gender', 'male')->get();
        $femaleCandidatesList = Candidate::where('gender', 'female')->get();

        // Load all scores at once
        $scores = TopFiveSelectionScore::all()->groupBy('candidate_id');

        $maleCandidates = $this->processCandidates($maleCandidatesList, $scores, $category, $judgeOrder);
        $femaleCandidates = $this->processCandidates($femaleCandidatesList, $scores, $category, $judgeOrder);

        return [
            'maleCandidates' => $maleCandidates,
            'femaleCandidates' => $femaleCandidates,
            'judgeOrder' => $judgeOrder,
        ];
    }

    protected function processCandidates($candidatesList, $scores, $category, $judgeOrder)
    {
        $processed = [];
        $count = 0;

        foreach ($candidatesList as $candidate) {
            $count++;

            // Initialize all judges with 0
            $candidateScores = array_fill_keys(array_column($judgeOrder, 'id'), 0);

            // Fill in scores if they exist
            $candidateScoresFromDB = $scores[$candidate->id] ?? collect();
            foreach ($candidateScoresFromDB as $score) {
                if (array_key_exists($score->judge_id, $candidateScores)) {
                    $candidateScores[$score->judge_id] = $score->{$category} ?? 0;
                }
            }

            $processed[] = [
                'candidate' => $candidate,
                'scores' => $candidateScores,
                'total' => round(array_sum($candidateScores), 2),
                'rank' => 0,
                'candidate_number' => $count,
            ];
        }

        return $this->assignRanking($processed);
    }

    public function getTopFiveSelectionResults()
    {
        // Get all candidates
        $maleCandidatesList = Candidate::where('gender', 'male')->get();
        $femaleCandidatesList = Candidate::where('gender', 'female')->get();

        // Load all scores at once
        $scores = TopFiveSelectionScore::all()->groupBy('candidate_id');

        $maleCandidates = $this->processTotalPerCategory($maleCandidatesList, $scores);
        $femaleCandidates = $this->processTotalPerCategory($femaleCandidatesList, $scores);

        return [
            'maleCandidates' => $maleCandidates,
            'femaleCandidates' => $femaleCandidates,
            'categories' => $this->categories,
            'judgeOrder' => $this->judgeOrder(),
        ];
    }

    protected function processTotalPerCategory($candidatesList, $scores)
    {
        $processed = [];
        $count = 0;

        foreach ($candidatesList as $candidate) {
            $count++;

            // Initialize all categories with 0
            $categoryTotals = array_fill_keys($this->categories, 0);

            // Sum all judges' scores per category
            $candidateScores = $scores[$candidate->id] ?? collect();
            foreach ($candidateScores as $score) {
                foreach ($this->categories as $cat) {
                    $categoryTotals[$cat] += $score->{$cat} ?? 0;
                }
            }

            $processed[] = [
                'candidate' => $candidate,
                'scores' => $categoryTotals,
                'total' => round(array_sum($categoryTotals), 2),
                'rank' => 0,
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
