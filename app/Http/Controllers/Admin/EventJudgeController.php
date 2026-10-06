<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use App\Support\EventLocks;
use App\Support\JudgeAccounts;
use App\Support\LiveVersions;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/** An event's judge accounts: create by count, rename, reset password, delete, print slips. */
class EventJudgeController extends Controller
{
    public function store(Request $request, Event $event)
    {
        $data = $request->validate(['count' => ['required', 'integer', 'min:1', 'max:30']]);

        JudgeAccounts::create($event, (int) $data['count']);
        LiveVersions::bump($event->id, LiveVersions::EVENT);

        return back();
    }

    public function update(Request $request, User $judge)
    {
        $this->ensureEventJudge($judge);
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $judge->update($data);
        LiveVersions::bump($judge->event_id, LiveVersions::EVENT);

        return back();
    }

    public function reset(User $judge)
    {
        $this->ensureEventJudge($judge);
        JudgeAccounts::resetPassword($judge);

        return back();
    }

    public function destroy(Request $request, User $judge)
    {
        $this->ensureEventJudge($judge);
        $request->validate(['password' => ['required', 'current_password']]);

        if (EventLocks::judgeHasScores($judge)) {
            throw ValidationException::withMessages(['judge' => "This judge has scores, so they can't be deleted."]);
        }

        $judge->delete();
        LiveVersions::bump($judge->event_id, LiveVersions::EVENT);

        return back();
    }

    public function slips(Event $event)
    {
        return Inertia::render('Admin/Events/JudgeSlips', [
            'event' => ['id' => $event->id, 'name' => $event->name],
            'judges' => self::judgeRows($event),
        ]);
    }

    /** @return array<int, array{id:int, name:string, username:?string, password:?string, score_count:int}> */
    public static function judgeRows(Event $event): array
    {
        return $event->judges()->withCount('scores')->get()->map(fn (User $j) => [
            'id' => $j->id,
            'name' => $j->name,
            'username' => $j->username,
            'password' => JudgeAccounts::revealPassword($j),
            'score_count' => $j->scores_count,
        ])->all();
    }

    private function ensureEventJudge(User $judge): void
    {
        abort_unless($judge->role === 'judge' && $judge->event_id !== null, 404);
    }
}
