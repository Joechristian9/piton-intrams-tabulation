<?php

namespace App\Support;

use App\Models\Candidate;
use App\Models\Event;
use App\Models\Score;
use App\Models\User;

/**
 * "Has scores" checks behind every edit lock: once judges have scored, changes
 * that would alter submitted results are blocked (spec §4).
 */
class EventLocks
{
    public const SCORING_STARTED = "This can't change after scoring has started.";

    public static function hasScores(Event $event): bool
    {
        return Score::whereIn('category_id', $event->categories()->pluck('id'))->exists();
    }

    public static function roundHasScores(Event $event, int $round): bool
    {
        return Score::whereIn('category_id', $event->categories()->where('round', $round)->pluck('id'))->exists();
    }

    public static function candidateHasScores(Candidate $candidate): bool
    {
        return Score::where('candidate_id', $candidate->id)->exists();
    }

    public static function judgeHasScores(User $judge): bool
    {
        return Score::where('judge_id', $judge->id)->exists();
    }
}
