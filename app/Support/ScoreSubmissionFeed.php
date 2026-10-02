<?php

namespace App\Support;

use App\Models\Candidate;
use App\Models\User;

/**
 * Recent judge submissions, shown to admins as toast alerts.
 */
class ScoreSubmissionFeed
{
    private static function feed(): EventFeed
    {
        return new EventFeed('score-submission-feed');
    }

    public static function push(int $judgeId, string $category, array $candidateIds): void
    {
        $judge = User::find($judgeId)?->name ?? 'A judge';

        $genders = Candidate::whereIn('id', $candidateIds)->pluck('gender')->unique();
        $group = $genders->count() === 1 ? ucfirst($genders->first()) . ' ' : '';

        $label = Criteria::LABELS[$category] ?? $category;

        self::feed()->push(['message' => "{$judge} submitted {$group}{$label} scores"]);
    }

    /**
     * Events newer than $after, plus the latest sequence number.
     */
    public static function since(?int $after): array
    {
        $feed = self::feed()->read();

        return [
            'seq' => $feed['seq'],
            'events' => $after === null
                ? []
                : array_values(array_filter($feed['events'], fn ($e) => $e['id'] > $after)),
        ];
    }
}
