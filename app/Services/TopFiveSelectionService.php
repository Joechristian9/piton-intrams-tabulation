<?php

namespace App\Services;

use App\Models\TopFiveSelectionScore;
use App\Models\Candidate;

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
        $judgeOrder = ['judge_1', 'judge_2', 'judge_3', 'judge_4', 'judge_5'];

        // Get all candidates
        $maleCandidatesList = Candidate::where('gender', 'male')->get();
        $femaleCandidatesList = Candidate::where('gender', 'female')->get();

        // Load all scores at once
        $scores = TopFiveSelectionScore::with('judge')->get();

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
            $candidateScores = array_fill_keys($judgeOrder, 0);

            // Fill in scores if they exist
            $candidateScoresFromDB = $scores->where('candidate_id', $candidate->id);
            foreach ($candidateScoresFromDB as $score) {
                if (in_array($score->judge->name, $judgeOrder)) {
                    $candidateScores[$score->judge->name] = $score->{$category} ?? 0;
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
        $scores = TopFiveSelectionScore::with('judge')->get();

        $maleCandidates = $this->processTotalPerCategory($maleCandidatesList, $scores);
        $femaleCandidates = $this->processTotalPerCategory($femaleCandidatesList, $scores);

        return [
            'maleCandidates' => $maleCandidates,
            'femaleCandidates' => $femaleCandidates,
            'categories' => $this->categories,
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
            $candidateScores = $scores->where('candidate_id', $candidate->id);
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
}
