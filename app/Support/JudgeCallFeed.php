<?php

namespace App\Support;

use App\Models\Category;
use App\Models\User;

/**
 * Notifications from the admin asking an event's judges to start scoring,
 * polled by the judges' pages. Kept in the cache per event, not the database.
 */
class JudgeCallFeed
{
    /** Judges still see a call sent within this window (e.g. if they log in late). */
    private const RECENT_MINUTES = 120;

    private static function feed(int $eventId): EventFeed
    {
        return new EventFeed("judge-call-feed:{$eventId}");
    }

    /**
     * @param  array<int>|null  $judgeIds  null = every judge of the event
     */
    public static function push(int $eventId, string $sender, ?int $categoryId, ?string $message, ?array $judgeIds): array
    {
        $label = $categoryId ? Category::find($categoryId)?->name : null;

        return self::feed($eventId)->push([
            'category_id' => $categoryId,
            'label' => $label,
            'message' => $message ?: ($label
                ? "Please score the candidates for {$label}."
                : 'Please check your scoring sheets.'),
            'sender' => $sender,
            'judge_ids' => $judgeIds ? array_values(array_map('intval', $judgeIds)) : null,
            'sent_at' => now()->toIso8601String(),
        ]);
    }

    /** Recent calls addressed to this judge, newest last, plus the latest id. */
    public static function forJudge(User $judge): array
    {
        return self::filterFor(self::feed($judge->event_id)->read(), $judge->id);
    }

    /** The latest calls of an event, newest first, for the admin page. */
    public static function recent(int $eventId, int $count = 10): array
    {
        return array_reverse(array_slice(self::feed($eventId)->read()['events'], -$count));
    }

    private static function filterFor(array $feed, int $judgeId): array
    {
        $since = now()->subMinutes(self::RECENT_MINUTES);

        $events = array_values(array_filter(
            $feed['events'],
            fn ($e) => ($e['judge_ids'] === null || in_array($judgeId, $e['judge_ids'], true))
                && now()->parse($e['sent_at'])->gte($since)
        ));

        return [
            'seq' => $feed['seq'],
            // Recipients aren't any one judge's business.
            'events' => array_map(fn ($e) => array_diff_key($e, ['judge_ids' => true]), $events),
        ];
    }

    // ---- Old single-pageant pages (removed with them) ----

    private static function legacyFeed(): EventFeed
    {
        return new EventFeed('judge-call-feed');
    }

    /** @deprecated */
    public static function pushLegacy(string $sender, ?string $category, ?string $message, ?array $judgeIds): array
    {
        $label = $category ? Criteria::LABELS[$category] : null;

        return self::legacyFeed()->push([
            'category' => $category,
            'label' => $label,
            'route' => $category ? Criteria::JUDGE_ROUTES[$category] : null,
            'message' => $message ?: ($label
                ? "Please score the candidates for {$label}."
                : 'Please check your scoring sheets.'),
            'sender' => $sender,
            'judge_ids' => $judgeIds ? array_values(array_map('intval', $judgeIds)) : null,
            'sent_at' => now()->toIso8601String(),
        ]);
    }

    /** @deprecated */
    public static function forJudgeLegacy(int $judgeId): array
    {
        return self::filterFor(self::legacyFeed()->read(), $judgeId);
    }

    /** @deprecated */
    public static function recentLegacy(int $count = 10): array
    {
        return array_reverse(array_slice(self::legacyFeed()->read()['events'], -$count));
    }
}
