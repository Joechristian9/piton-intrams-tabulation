<?php

namespace App\Repositories;

use App\Models\TopFiveSelectionScore;
use Illuminate\Support\Facades\DB;

class TopFiveSelectionScoreRepository
{
    /**
     * Save one judge's scores for a category: [candidateId => score].
     * One query loads the existing rows and a single transaction commits every
     * write, instead of a lookup and a separate commit per candidate.
     */
    public function saveScores(int $judgeId, string $category, array $scores): void
    {
        DB::transaction(function () use ($judgeId, $category, $scores) {
            $existing = TopFiveSelectionScore::where('judge_id', $judgeId)
                ->whereIn('candidate_id', array_keys($scores))
                ->get()
                ->keyBy('candidate_id');

            foreach ($scores as $candidateId => $scoreValue) {
                $record = $existing->get($candidateId)
                    ?? new TopFiveSelectionScore(['judge_id' => $judgeId, 'candidate_id' => $candidateId]);

                // Update only the current category score
                $record->{$category} = $scoreValue;

                // Recalculate the total for all categories
                $record->total_scores =
                    ($record->production_number ?? 0) +
                    ($record->casual_wear ?? 0) +
                    ($record->swim_wear ?? 0) +
                    ($record->formal_wear ?? 0) +
                    ($record->closed_door_interview ?? 0);

                $record->save();
            }
        });
    }
}
