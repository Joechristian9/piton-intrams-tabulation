<?php

namespace App\Support;

use App\Models\Candidate;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Short-lived list of recent judge submissions, kept in the file cache
 * (not the database) so admin pages can show toast alerts.
 */
class ScoreSubmissionFeed
{
    private const KEY = 'score-submission-feed';
    private const LIMIT = 50;

    private const CATEGORIES = [
        'production_number'     => 'Production Number',
        'casual_wear'           => 'Sports Wear',
        'swim_wear'             => 'Swim Wear',
        'formal_wear'           => 'Formal Wear',
        'closed_door_interview' => 'Closed Door Interview',
        'face_and_figure'       => 'Beauty of the Face and Figure',
        'delivery'              => 'Delivery',
        'overall_appeal'        => 'Over-all Appeal / X-factor',
    ];

    public static function push(int $judgeId, string $category, array $candidateIds): void
    {
        $judge = User::find($judgeId)?->name ?? 'A judge';

        $genders = Candidate::whereIn('id', $candidateIds)->pluck('gender')->unique();
        $group = $genders->count() === 1 ? ucfirst($genders->first()) . ' ' : '';

        $label = self::CATEGORIES[$category] ?? $category;
        $message = "{$judge} submitted {$group}{$label} scores";

        $store = Cache::store('file');

        // Lock so two judges submitting at once don't overwrite each other.
        $store->lock(self::KEY . '-lock', 5)->block(5, function () use ($store, $message) {
            $feed = $store->get(self::KEY, ['seq' => 0, 'events' => []]);

            $feed['seq']++;
            $feed['events'][] = ['id' => $feed['seq'], 'message' => $message];
            $feed['events'] = array_slice($feed['events'], -self::LIMIT);

            $store->put(self::KEY, $feed, now()->addDay());
        });
    }

    /**
     * Events newer than $after, plus the latest sequence number.
     */
    public static function since(?int $after): array
    {
        $feed = Cache::store('file')->get(self::KEY, ['seq' => 0, 'events' => []]);

        return [
            'seq' => $feed['seq'],
            'events' => $after === null
                ? []
                : array_values(array_filter($feed['events'], fn ($e) => $e['id'] > $after)),
        ];
    }
}
