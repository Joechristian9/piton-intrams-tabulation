<?php

namespace App\Repositories;

use App\Models\TopFiveScore;

class TopFiveFinalistScoreRepository
{
    public function updateOrCreateScore(int $judgeId, int $topFiveId, string $category, $scoreValue)
    {
        // Use top_five_id instead of candidate_id
        $record = TopFiveScore::firstOrNew([
            'judge_id' => $judgeId,
            'top_five_id' => $topFiveId,
        ]);

        // Update only the current category score
        $record->{$category} = $scoreValue;

        // Recalculate total score
        $record->total_score =
            ($record->face_and_figure ?? 0) +
            ($record->delivery ?? 0) +
            ($record->overall_appeal ?? 0);

        $record->save();

        return $record;
    }
}
