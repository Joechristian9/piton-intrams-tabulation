<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\TopFiveCandidates;
use App\Models\TopFiveScore;
use App\Models\TopFiveSelectionScore;
use App\Models\User;
use App\Support\Criteria;
use App\Support\JudgeCallFeed;
use App\Support\LiveVersions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class JudgeNotificationController extends Controller
{
    /**
     * Admin page: pick a category and judges, see who still has scoring to do.
     */
    public function index()
    {
        $judges = User::where('role', 'judge')->orderBy('id')->get(['id', 'name']);

        return Inertia::render('Admin/NotifyJudges', [
            'judges' => $judges,
            'categories' => collect(Criteria::LABELS)
                ->map(fn ($label, $key) => [
                    'key' => $key,
                    'label' => $label,
                    'round' => Criteria::isFinals($key) ? 'Top 3 Finalist' : 'Top 3 Selection',
                ])
                ->values(),
            'progress' => $this->progress(),
            'recent' => JudgeCallFeed::recent(),
        ]);
    }

    /**
     * Send a notification to all judges or to the selected ones.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'category' => ['nullable', Rule::in(array_keys(Criteria::LABELS))],
            'message' => ['nullable', 'string', 'max:200'],
            'judge_ids' => ['nullable', 'array'],
            'judge_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->where('role', 'judge'),
            ],
        ]);

        JudgeCallFeed::push(
            $request->user()->name,
            $data['category'] ?? null,
            isset($data['message']) ? trim($data['message']) : null,
            empty($data['judge_ids']) ? null : $data['judge_ids'],
        );

        return back();
    }

    /**
     * Polled by judges' pages: recent notifications addressed to this judge.
     */
    public function feed(Request $request)
    {
        abort_unless($request->user()->role === 'judge', 403);

        return response()->json([
            ...JudgeCallFeed::forJudge($request->user()->id),
            'live' => LiveVersions::all(LiveVersions::LEGACY),
        ]);
    }

    /**
     * How many candidates each judge has scored per category:
     * ['casual_wear' => ['total' => 11, 'scored' => [judgeId => n]], ...]
     */
    private function progress(): array
    {
        $selection = array_keys(Criteria::SELECTION);
        $finals = array_keys(Criteria::FINALS);

        // COUNT(column) only counts non-null scores.
        $countColumns = fn (array $columns) => implode(', ', array_map(
            fn ($c) => "COUNT({$c}) as {$c}",
            $columns
        ));

        $selectionCounts = TopFiveSelectionScore::selectRaw('judge_id, ' . $countColumns($selection))
            ->groupBy('judge_id')->get()->keyBy('judge_id');
        $finalsCounts = TopFiveScore::selectRaw('judge_id, ' . $countColumns($finals))
            ->groupBy('judge_id')->get()->keyBy('judge_id');

        $candidateTotal = Candidate::count();
        $finalistTotal = TopFiveCandidates::count();

        $progress = [];
        foreach ([[$selection, $selectionCounts, $candidateTotal], [$finals, $finalsCounts, $finalistTotal]] as [$columns, $counts, $total]) {
            foreach ($columns as $column) {
                $progress[$column] = [
                    'total' => $total,
                    'scored' => $counts->map(fn ($row) => (int) $row->{$column}),
                ];
            }
        }

        return $progress;
    }
}
