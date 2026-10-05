<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Version stamps for data that other people's open pages show. Every page gets the
 * current stamps as the shared `live` prop; the judges' and admins' pollers get them
 * too, and reload the page when a stamp differs from the one the page was built with.
 *
 * Kept in `cache.feed_store` like EventFeed (the array cache in tests).
 */
class LiveVersions
{
    /** Finalists added or removed (judges' finals pages and the sidebar). */
    public const FINALISTS = 'finalists';

    /** Judges added or renamed (admin result columns, judges' names). */
    public const JUDGES = 'judges';

    /** Any judge saved scores (admin results and progress). */
    public const SCORES = 'scores';

    private const TOPICS = [self::FINALISTS, self::JUDGES, self::SCORES];

    /** Mark these topics as changed. */
    public static function bump(string ...$topics): void
    {
        // A fresh random stamp, not a counter: no lock needed, and any change is new.
        $stamp = (string) Str::ulid();

        self::store()->putMany(
            collect($topics)->mapWithKeys(fn ($topic) => [self::key($topic) => $stamp])->all(),
            now()->addDays(2),
        );
    }

    /** @return array<string, string|null> topic => stamp (null if never changed) */
    public static function all(): array
    {
        $stamps = self::store()->many(array_map(self::key(...), self::TOPICS));

        return array_combine(self::TOPICS, array_values($stamps));
    }

    private static function key(string $topic): string
    {
        return "live-version:{$topic}";
    }

    private static function store(): Repository
    {
        return Cache::store(config('cache.feed_store', 'file'));
    }
}
