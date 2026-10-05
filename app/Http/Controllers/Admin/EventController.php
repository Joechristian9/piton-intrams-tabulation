<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventGroup;
use App\Results\EventResults;
use App\Support\EventLocks;
use App\Support\LiveVersions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Events: create, edit settings, start/close (several can be live), duplicate
 * and delete. Start, Close and Delete need the admin's password.
 */
class EventController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Events/Index', [
            // Counts and "has scores" in the same query for every event (no query per event).
            'events' => Event::withCount(['candidates', 'judges'])->withExists('scores')
                ->orderByDesc('id')->get()->map(fn (Event $e) => [
                    'id' => $e->id,
                    'name' => $e->name,
                    'code' => $e->code,
                    'status' => $e->status,
                    'rounds' => $e->rounds,
                    'started_at' => $e->started_at?->toIso8601String(),
                    'candidates' => $e->candidates_count,
                    'judges' => $e->judges_count,
                    'hasScores' => (bool) $e->scores_exists,
                ]),
        ]);
    }

    public function store(Request $request)
    {
        $event = Event::create($this->validated($request));

        return redirect()->route('admin.events.edit', $event);
    }

    public function edit(Event $event)
    {
        // One query each for groups, categories and candidates, with their counts and
        // "has scores" flags attached; the page-level locks are derived from those.
        $categories = $event->categories()->withExists('scores')->get();
        $judges = EventJudgeController::judgeRows($event);
        $roundHasScores = fn (int $round) => $categories->where('round', $round)->contains('scores_exists', true);

        return Inertia::render('Admin/Events/Edit', [
            'event' => [
                ...$event->only(['id', 'name', 'code', 'status', 'rounds', 'finalists_per_group', 'finals_from_zero', 'round1_weight', 'finals_weight']),
            ],
            'groups' => $event->groups()->withCount('candidates')->get()->map(fn ($g) => [
                'id' => $g->id, 'name' => $g->name, 'position' => $g->position,
                'candidates' => $g->candidates_count,
            ]),
            'categories' => $categories->map(fn ($c) => [
                'id' => $c->id, 'round' => $c->round, 'name' => $c->name,
                'max_score' => (float) $c->max_score, 'position' => $c->position,
                'hasScores' => (bool) $c->scores_exists,
            ]),
            'candidates' => $event->candidates()->withExists('scores')
                ->orderBy('group_id')->orderBy('candidate_number')->get()
                ->map(fn ($c) => [...EventResults::candidatePayload($c), 'group_id' => $c->group_id, 'hasScores' => (bool) $c->scores_exists]),
            'judges' => $judges,
            'locks' => [
                'hasScores' => $categories->contains('scores_exists', true),
                'roundHasScores' => [1 => $roundHasScores(1), 2 => $roundHasScores(2)],
                'finalistsSet' => $event->finalistsSet(),
                'hasJudges' => $judges !== [],
            ],
        ]);
    }

    public function update(Request $request, Event $event)
    {
        $data = $this->validated($request, $event);

        $locked = [];
        if (EventLocks::hasScores($event)) {
            foreach (['rounds', 'finals_from_zero', 'round1_weight', 'finals_weight'] as $field) {
                if ($data[$field] != $event->{$field}) {
                    $locked[$field] = EventLocks::SCORING_STARTED;
                }
            }
        }
        if ($event->finalistsSet() && $data['finalists_per_group'] != $event->finalists_per_group) {
            $locked['finalists_per_group'] = "This can't change after the finalists are set.";
        }
        if ($data['code'] !== $event->code && $event->judges()->exists()) {
            $locked['code'] = "The code can't change after judges are created.";
        }
        // Going down to 1 round would hide the finals setup instead of removing it.
        if ($data['rounds'] === 1 && $event->rounds === 2 && ! isset($locked['rounds'])) {
            if ($event->finalistsSet()) {
                $locked['rounds'] = "This can't change after the finalists are set.";
            } elseif ($event->categories()->where('round', 2)->exists()) {
                $locked['rounds'] = 'Delete the Round 2 categories first.';
            }
        }
        if ($locked) {
            throw ValidationException::withMessages($locked);
        }

        $event->update($data);
        LiveVersions::bump($event->id, LiveVersions::EVENT);

        return back();
    }

    public function start(Request $request, Event $event)
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $missing = array_keys(array_filter([
            'judges' => ! $event->judges()->exists(),
            'groups' => ! $event->groups()->exists(),
            'Round 1 categories' => ! $event->categories()->where('round', 1)->exists(),
            'Round 2 categories' => $event->rounds === 2 && ! $event->categories()->where('round', 2)->exists(),
        ]));
        $emptyGroups = $event->groups()->doesntHave('candidates')->pluck('name');
        if ($emptyGroups->isNotEmpty()) {
            $missing[] = 'candidates in ' . $emptyGroups->implode(', ');
        }
        if ($missing) {
            throw ValidationException::withMessages(['event' => 'Before starting, add: ' . implode(', ', $missing) . '.']);
        }

        $event->forceFill(['status' => Event::LIVE, 'started_at' => now(), 'closed_at' => null])->save();
        LiveVersions::bump($event->id, LiveVersions::EVENT);

        return back();
    }

    public function close(Request $request, Event $event)
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $event->forceFill(['status' => Event::CLOSED, 'closed_at' => now()])->save();
        LiveVersions::bump($event->id, LiveVersions::EVENT);

        return back();
    }

    public function destroy(Request $request, Event $event)
    {
        $request->validate(['password' => ['required', 'current_password']]);

        if (EventLocks::hasScores($event)) {
            throw ValidationException::withMessages(['event' => "This event has scores, so it can't be deleted. Close it instead."]);
        }

        DB::transaction(function () use ($event) {
            $event->judges()->delete();
            $event->delete();
        });
        Storage::disk('uploads')->deleteDirectory("candidates/{$event->id}");

        return redirect()->route('admin.events.index');
    }

    public function duplicate(Event $event)
    {
        $copy = DB::transaction(function () use ($event) {
            $copy = Event::create([
                ...$event->only(['rounds', 'finalists_per_group', 'finals_from_zero', 'round1_weight', 'finals_weight']),
                'name' => "Copy of {$event->name}",
                'code' => $this->freeCode($event->code),
                'status' => Event::SETUP,
            ]);

            foreach ($event->groups as $group) {
                EventGroup::create(['event_id' => $copy->id, ...$group->only(['name', 'position'])]);
            }
            foreach ($event->categories as $category) {
                Category::create(['event_id' => $copy->id, ...$category->only(['round', 'name', 'max_score', 'position'])]);
            }

            return $copy;
        });

        return redirect()->route('admin.events.edit', $copy);
    }

    /** Validated settings, normalized so unused finals settings are cleared. */
    private function validated(Request $request, ?Event $event = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'alpha_dash', 'max:20', Rule::unique('events', 'code')->ignore($event?->id)],
            'rounds' => ['required', 'integer', Rule::in([1, 2])],
            'finalists_per_group' => ['nullable', 'required_if:rounds,2', 'integer', 'min:1', 'max:50'],
            'finals_from_zero' => ['boolean'],
            'round1_weight' => ['nullable', 'integer', 'min:0', 'max:100'],
            'finals_weight' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $twoRounds = (int) $data['rounds'] === 2;
        $fromZero = ! $twoRounds || $request->boolean('finals_from_zero', true);

        if (! $fromZero && (int) ($data['round1_weight'] ?? -1) + (int) ($data['finals_weight'] ?? -1) !== 100) {
            throw ValidationException::withMessages(['round1_weight' => 'Round 1 and Finals weights must add up to 100.']);
        }

        return [
            'name' => $data['name'],
            'code' => $data['code'],
            'rounds' => (int) $data['rounds'],
            'finalists_per_group' => $twoRounds ? (int) $data['finalists_per_group'] : null,
            'finals_from_zero' => $fromZero,
            'round1_weight' => $fromZero ? null : (int) $data['round1_weight'],
            'finals_weight' => $fromZero ? null : (int) $data['finals_weight'],
        ];
    }

    /** "{code}-copy", then "{code}-copy-2"…, shortening the code to stay within 20 characters. */
    private function freeCode(string $original): string
    {
        for ($i = 1; ; $i++) {
            $suffix = $i === 1 ? '-copy' : "-copy-{$i}";
            $code = rtrim(substr($original, 0, 20 - strlen($suffix)), '-') . $suffix;

            if (! Event::where('code', $code)->exists()) {
                return $code;
            }
        }
    }
}
