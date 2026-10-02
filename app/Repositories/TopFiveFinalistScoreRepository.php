<?php

namespace App\Repositories;

use App\Models\TopFiveCandidates;
use App\Models\TopFiveScore;
use Illuminate\Support\Facades\DB;

class TopFiveFinalistScoreRepository
{
    /**
     * Save one judge's finals scores for a category: [candidateId => score].
     * Candidates who aren't finalists are skipped. Finalists and existing rows
     * are each loaded in one query, and every write commits in one transaction.
     */
    public function saveScores(int $judgeId, string $category, array $scores): void
    {
        DB::transaction(function () use ($judgeId, $category, $scores) {
            // candidate_id => top_five_id
            $finalists = TopFiveCandidates::whereIn('candidate_id', array_keys($scores))
                ->pluck('id', 'candidate_id');

            $existing = TopFiveScore::where('judge_id', $judgeId)
                ->whereIn('top_five_id', $finalists->values())
                ->get()
                ->keyBy('top_five_id');

            foreach ($scores as $candidateId => $scoreValue) {
                $topFiveId = $finalists->get($candidateId);

                if (! $topFiveId) {
                    continue;
                }

                $record = $existing->get($topFiveId)
                    ?? new TopFiveScore(['judge_id' => $judgeId, 'top_five_id' => $topFiveId]);

                // Update only the current category score
                $record->{$category} = $scoreValue;

                // Recalculate total score
                $record->total_score =
                    ($record->face_and_figure ?? 0) +
                    ($record->delivery ?? 0) +
                    ($record->overall_appeal ?? 0);

                $record->save();
            }
        });
    }
}
