<?php

namespace App\Repositories;

use App\Models\TopFiveSelectionScore;

class TopFiveSelectionScoreRepository
{
    public function updateOrCreateScore(int $judgeId, int $candidateId, string $category, $scoreValue)
    {
        // Find existing record or create a new one
        $record = TopFiveSelectionScore::firstOrNew([
            'judge_id' => $judgeId,
            'candidate_id' => $candidateId,
        ]);

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

        return $record;
    }
}