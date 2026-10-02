<?php

namespace App\Support;

/**
 * Notifications from the admin asking judges to start scoring, polled by the
 * judges' pages. Kept in the cache, not the database.
 */
class JudgeCallFeed
{
    /** Judges still see a call sent within this window (e.g. if they log in late). */
    private const RECENT_MINUTES = 120;

    private static function feed(): EventFeed
    {
        return new EventFeed('judge-call-feed');
    }

    /**
     * @param  array<int>|null  $judgeIds  null = every judge
     */
    public static function push(string $sender, ?string $category, ?string $message, ?array $judgeIds): array
    {
        $label = $category ? Criteria::LABELS[$category] : null;

        return self::feed()->push([
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

    /** Recent calls addressed to this judge, newest last, plus the latest id. */
    public static function forJudge(int $judgeId): array
    {
        $feed = self::feed()->read();
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

    /** The latest calls, newest first, for the admin page. */
    public static function recent(int $count = 10): array
    {
        return array_reverse(array_slice(self::feed()->read()['events'], -$count));
    }
}
