<?php

namespace App\Http\Controllers;

use App\Repositories\TopFiveSelectionScoreRepository;
use App\Support\Criteria;
use App\Support\ScoreSubmissionFeed;
use Illuminate\Http\Request;
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
        $request->validate([
            'judge_id' => 'required|exists:users,id',
            'scores' => 'required|array',
            // A judge can never give more than the category's maximum.
            'scores.*' => ['required', 'numeric', 'min:0', 'max:' . Criteria::max($category)],
        ]);

        $judgeId = $request->input('judge_id');
        $scores = $request->input('scores');

        $this->scores->saveScores($judgeId, $category, $scores);

        ScoreSubmissionFeed::push($judgeId, $category, array_keys($scores));

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
