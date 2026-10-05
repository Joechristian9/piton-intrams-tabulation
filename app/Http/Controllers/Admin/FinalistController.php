<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Finalist;
use App\Models\Score;
use App\Support\LiveVersions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sets an event's finalists (Top N per group). Closes Round 1 for good, so the
 * admin re-enters their password. Finalists who stay keep their finals scores;
 * removed finalists' finals scores are deleted.
 */
class FinalistController extends Controller
{
    public function store(Request $request, Event $event)
    {
        $request->validate([
            'password' => ['required', 'current_password'],
            'candidate_ids' => ['required', 'array'],
            'candidate_ids.*' => ['integer', 'distinct'],
        ]);

        if ($event->rounds !== 2) {
            throw ValidationException::withMessages(['candidate_ids' => 'This event has only one round, so it has no finalists.']);
        }

        $ids = array_map('intval', $request->input('candidate_ids'));
        $candidates = $event->candidates()->whereIn('id', $ids)->get(['id', 'group_id']);

        if ($candidates->count() !== count($ids)) {
            throw ValidationException::withMessages(['candidate_ids' => 'Some of these candidates are not in this event.']);
        }

        $n = $event->finalists_per_group;
        $perGroup = $candidates->countBy('group_id');
        foreach ($event->groups as $group) {
            if (($perGroup[$group->id] ?? 0) !== $n) {
                throw ValidationException::withMessages(['candidate_ids' => "Select exactly {$n} per group."]);
            }
        }

        DB::transaction(function () use ($event, $ids) {
            $removed = $event->finalists()->whereNotIn('candidate_id', $ids)->pluck('candidate_id');
            if ($removed->isNotEmpty()) {
                Score::whereIn('candidate_id', $removed)
                    ->whereIn('category_id', $event->categories()->where('round', 2)->pluck('id'))
                    ->delete();
                $event->finalists()->whereIn('candidate_id', $removed)->delete();
            }

            $existing = $event->finalists()->pluck('candidate_id')->all();
            foreach (array_diff($ids, $existing) as $candidateId) {
                Finalist::create(['event_id' => $event->id, 'candidate_id' => $candidateId]);
            }

            $event->forceFill(['finalists_set_at' => now()])->save();
        });

        LiveVersions::bump($event->id, LiveVersions::FINALISTS, LiveVersions::SCORES);

        return back()->with('success', "Top {$n} saved.");
    }
}
