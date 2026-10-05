<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Per-event version stamps for data that other people's open pages show. Every
 * page gets its event's stamps as the shared `live` prop; the judges' and
 * admins' pollers get the current ones and reload the page when a stamp they
 * watch differs (resources/js/lib/liveVersions.js).
 *
 * Kept in `cache.feed_store` like EventFeed (the array cache in tests).
 */
class LiveVersions
{
    /** Event status, settings, groups, categories, candidates or judges changed. */
    public const EVENT = 'event';

    /** Finalists added or removed (finals pages and the judges' sidebar). */
    public const FINALISTS = 'finalists';

    /** A judge saved scores (admin results and progress). */
    public const SCORES = 'scores';

    /** @deprecated old single-pageant code; judges belong to the event now. */
    public const JUDGES = self::EVENT;

    /** Slot used by the old single-pageant pages until they're removed. */
    public const LEGACY = 0;

    private const TOPICS = [self::EVENT, self::FINALISTS, self::SCORES];

    /** Mark these topics of one event as changed. */
    public static function bump(int $eventId, string ...$topics): void
    {
        // A fresh random stamp, not a counter: no lock needed, and any change is new.
        $stamp = (string) Str::ulid();

        self::store()->putMany(
            collect($topics)->unique()->mapWithKeys(fn ($topic) => [self::key($eventId, $topic) => $stamp])->all(),
            now()->addDays(2),
        );
    }

    /** @return array<string, string|null> topic => stamp (null if never changed) */
    public static function all(int $eventId): array
    {
        $stamps = self::store()->many(array_map(fn ($topic) => self::key($eventId, $topic), self::TOPICS));

        return array_combine(self::TOPICS, array_values($stamps));
    }

    private static function key(int $eventId, string $topic): string
    {
        return "live-version:{$eventId}:{$topic}";
    }

    private static function store(): Repository
    {
        return Cache::store(config('cache.feed_store', 'file'));
    }
}
