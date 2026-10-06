<?php

namespace App\Http\Controllers\Judge;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use App\Models\Score;
use App\Results\EventResults;
use App\Support\LiveVersions;
use App\Support\ScoreSubmissionFeed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * A judge scores one category of their own event. Scores are always saved for
 * the logged-in judge; the event must be live, every candidate must be in this
 * round, and Round 1 locks once the finalists are set.
 */
class ScoringController extends Controller
{
    public function show(Request $request, Category $category)
    {
        $event = $this->judgesEvent($request, $category);

        if (! $event->isLive()) {
            return redirect()->route('dashboard');
        }

        $existing = Score::where('category_id', $category->id)
            ->where('judge_id', $request->user()->id)
            ->pluck('score', 'candidate_id');

        $allowed = $this->roundCandidateIds($event, $category);

        $candidates = $event->candidates()
            ->whereIn('id', $allowed)
            ->orderBy('candidate_number')->orderBy('id')
            ->get();

        return Inertia::render('Judge/Score', [
            'event' => ['id' => $event->id, 'name' => $event->name],
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'max_score' => (float) $category->max_score,
                'round' => $category->round,
            ],
            'groups' => $event->groups->map(fn ($group) => [
                'id' => $group->id,
                'name' => $group->name,
                'candidates' => $candidates->where('group_id', $group->id)->values()->map(fn ($c) => [
                    ...EventResults::candidatePayload($c),
                    'existing_score' => isset($existing[$c->id]) ? (float) $existing[$c->id] : null,
                ])->all(),
            ])->all(),
            'roundClosed' => $category->round === 1 && $event->finalistsSet(),
            'finalistsPerGroup' => $event->finalists_per_group,
        ]);
    }

    public function store(Request $request, Category $category)
    {
        $event = $this->judgesEvent($request, $category);

        $request->validate([
            'scores' => ['required', 'array'],
            'scores.*' => ['required', 'numeric', 'min:0', 'max:' . (float) $category->max_score],
        ]);

        if (! $event->isLive()) {
            // Straight to the landing page: redirecting back to the scoring page would
            // bounce again (it isn't shown for a closed event) and lose the error, and
            // the browser would then treat the submit as a success.
            return redirect()->route('dashboard')
                ->withErrors(['scores' => "This event isn't running right now."]);
        }

        if ($category->round === 1 && $event->finalistsSet()) {
            $n = $event->finalists_per_group;
            throw ValidationException::withMessages([
                'scores' => "The Top {$n} finalists have been set, so Top {$n} Selection scores can no longer be changed.",
            ]);
        }

        $scores = $request->input('scores');
        $candidateIds = array_map('intval', array_keys($scores));

        if (array_diff($candidateIds, $this->roundCandidateIds($event, $category)) !== []) {
            throw ValidationException::withMessages(['scores' => 'Some of these candidates are not part of this round.']);
        }

        $judge = $request->user();
        $now = now();

        DB::transaction(fn () => Score::upsert(
            array_map(fn ($id) => [
                'category_id' => $category->id,
                'candidate_id' => $id,
                'judge_id' => $judge->id,
                'score' => $scores[$id],
                'created_at' => $now,
                'updated_at' => $now,
            ], $candidateIds),
            ['category_id', 'candidate_id', 'judge_id'],
            ['score', 'updated_at'],
        ));

        ScoreSubmissionFeed::push($category, $judge, $candidateIds);
        LiveVersions::bump($event->id, LiveVersions::SCORES);

        return back();
    }

    /** Only judges, and only for a category of their own event (404 otherwise). */
    private function judgesEvent(Request $request, Category $category): Event
    {
        $user = $request->user();
        abort_unless($user->role === 'judge', 403);
        abort_unless($user->event_id !== null && $category->event_id === $user->event_id, 404);

        return $category->event;
    }

    /** @return array<int, int> candidates who can be scored in this category's round */
    private function roundCandidateIds(Event $event, Category $category): array
    {
        return $category->round === 2
            ? $event->finalists()->pluck('candidate_id')->map(fn ($id) => (int) $id)->all()
            : $event->candidates()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
