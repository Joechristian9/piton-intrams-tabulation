<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Score;
use App\Support\JudgeCallFeed;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Notify an event's judges to score a category, and see their progress. */
class NotifyController extends Controller
{
    public function index(Event $event)
    {
        $n = $event->finalists_per_group;
        $roundLabel = fn (int $round) => $event->rounds === 1
            ? 'Categories'
            : ($round === 1 ? "Top {$n} Selection" : "Top {$n} Finalist");

        return Inertia::render('Admin/NotifyJudges', [
            'event' => ResultsController::eventPayload($event),
            'judges' => $event->judges()->get(['id', 'name']),
            'categories' => $event->categories->map(fn ($c) => [
                'key' => $c->id,
                'label' => $c->name,
                'round' => $roundLabel($c->round),
            ])->values(),
            'progress' => $this->progress($event),
            'recent' => JudgeCallFeed::recent($event->id),
            'sendUrl' => route('admin.notify.send', $event),
        ]);
    }

    public function store(Request $request, Event $event)
    {
        $data = $request->validate([
            'category' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('event_id', $event->id)],
            'message' => ['nullable', 'string', 'max:200'],
            'judge_ids' => ['nullable', 'array'],
            'judge_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->where('role', 'judge')->where('event_id', $event->id),
            ],
        ]);

        JudgeCallFeed::push(
            $event->id,
            $request->user()->name,
            $data['category'] ?? null,
            isset($data['message']) ? trim($data['message']) : null,
            empty($data['judge_ids']) ? null : $data['judge_ids'],
        );

        return back();
    }

    /**
     * Scores given per category and judge:
     * [categoryId => ['total' => candidates in that round, 'scored' => [judgeId => n]]].
     */
    private function progress(Event $event): array
    {
        $counts = Score::whereIn('category_id', $event->categories->pluck('id'))
            ->selectRaw('category_id, judge_id, COUNT(*) as n')
            ->groupBy('category_id', 'judge_id')
            ->get()
            ->groupBy('category_id');

        $totals = [1 => $event->candidates()->count(), 2 => $event->finalists()->count()];

        $progress = [];
        foreach ($event->categories as $category) {
            $progress[$category->id] = [
                'total' => $totals[$category->round],
                'scored' => ($counts[$category->id] ?? collect())->mapWithKeys(fn ($row) => [$row->judge_id => (int) $row->n]),
            ];
        }

        return $progress;
    }
}
