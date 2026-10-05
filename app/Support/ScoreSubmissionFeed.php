<?php

namespace App\Support;

use App\Models\Candidate;
use App\Models\Category;
use App\Models\EventGroup;
use App\Models\User;

/**
 * Recent judge submissions per event, shown to admins as toast alerts.
 */
class ScoreSubmissionFeed
{
    private static function feed(int $eventId): EventFeed
    {
        return new EventFeed("score-submission-feed:{$eventId}");
    }

    public static function push(Category $category, User $judge, array $candidateIds): void
    {
        $groupIds = Candidate::whereIn('id', $candidateIds)->pluck('group_id')->unique();
        $group = $groupIds->count() === 1 ? EventGroup::find($groupIds->first())?->name . ' ' : '';

        self::feed($category->event_id)->push([
            'message' => "{$judge->name} submitted {$group}{$category->name} scores",
        ]);
    }

    /** @deprecated old single-pageant controllers; removed with them. */
    public static function pushLegacy(int $judgeId, string $category, array $candidateIds): void
    {
        $judge = User::find($judgeId)?->name ?? 'A judge';

        $genders = Candidate::whereIn('id', $candidateIds)->pluck('gender')->unique();
        $group = $genders->count() === 1 ? ucfirst($genders->first()) . ' ' : '';

        $label = Criteria::LABELS[$category] ?? $category;

        self::feed(LiveVersions::LEGACY)->push(['message' => "{$judge} submitted {$group}{$label} scores"]);
    }

    /**
     * Events newer than $after, plus the latest sequence number. A first poll
     * ($after null) returns no backlog.
     */
    public static function since(int $eventId, ?int $after): array
    {
        $feed = self::feed($eventId)->read();

        return [
            'seq' => $feed['seq'],
            'events' => $after === null
                ? []
                : array_values(array_filter($feed['events'], fn ($e) => $e['id'] > $after)),
        ];
    }
}
