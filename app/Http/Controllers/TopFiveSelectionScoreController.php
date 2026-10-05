<?php

namespace App\Http\Controllers;

use App\Models\TopFiveCandidates;
use App\Repositories\TopFiveSelectionScoreRepository;
use App\Support\Criteria;
use App\Support\LiveVersions;
use App\Support\ScoreSubmissionFeed;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TopFiveSelectionScoreController extends Controller
{
    protected $scores;

    public function __construct(TopFiveSelectionScoreRepository $scores)
    {
        $this->scores = $scores;
    }

    private function storeScores(Request $request, string $category)
    {
        // Only judges score, and always as themselves: a `judge_id` sent by the
        // browser is ignored, so nobody can submit scores under another judge.
        abort_unless($request->user()->role === 'judge', 403);

        $request->validate([
            'scores' => 'required|array',
            // A judge can never give more than the category's maximum.
            'scores.*' => ['required', 'numeric', 'min:0', 'max:' . Criteria::max($category)],
        ]);

        // Round 1 closes once the admin sets the Top 3: its scores decided the
        // finalists, so they can no longer be added or changed.
        if (TopFiveCandidates::exists()) {
            throw ValidationException::withMessages([
                'scores' => 'The Top 3 finalists have been set, so Top 3 Selection scores can no longer be changed.',
            ]);
        }

        $judgeId = $request->user()->id;
        $scores = $request->input('scores');

        $this->scores->saveScores($judgeId, $category, $scores);

        ScoreSubmissionFeed::push($judgeId, $category, array_keys($scores));
        LiveVersions::bump(LiveVersions::SCORES);

        return back();
    }

    public function production_number_store(Request $request)
    {
        return $this->storeScores($request, 'production_number');
    }

    public function casual_wear_store(Request $request)
    {
        return $this->storeScores($request, 'casual_wear');
    }

    public function swim_wear_store(Request $request)
    {
        return $this->storeScores($request, 'swim_wear');
    }

    public function formal_wear_store(Request $request)
    {
        return $this->storeScores($request, 'formal_wear');
    }

    public function closed_door_interview_store(Request $request)
    {
        return $this->storeScores($request, 'closed_door_interview');
    }
}
