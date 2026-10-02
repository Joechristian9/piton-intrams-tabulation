<?php

namespace App\Http\Controllers;

use App\Repositories\TopFiveFinalistScoreRepository;
use App\Support\Criteria;
use App\Support\ScoreSubmissionFeed;
use Illuminate\Http\Request;

class TopFiveScoreController extends Controller
{
    protected $scores;

    public function __construct(TopFiveFinalistScoreRepository $scores)
    {
        $this->scores = $scores;
    }

    /**
     * Generic function to store scores for a given category
     */
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

    /**
     * Store Face & Figure scores
     */
    public function faceAndFigureStore(Request $request)
    {
        return $this->storeScores($request, 'face_and_figure');
    }

    /**
     * Store Delivery scores
     */
    public function deliveryStore(Request $request)
    {
        return $this->storeScores($request, 'delivery');
    }

    /**
     * Store Overall Appeal scores
     */
    public function overallAppealStore(Request $request)
    {
        return $this->storeScores($request, 'overall_appeal');
    }
}
