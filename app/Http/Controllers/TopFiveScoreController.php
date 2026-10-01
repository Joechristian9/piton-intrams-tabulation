<?php

namespace App\Http\Controllers;

use App\Repositories\TopFiveFinalistScoreRepository;
use App\Models\TopFiveCandidates;
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
        ]);

        $judgeId = $request->input('judge_id');
        $scores = $request->input('scores');

        foreach ($scores as $candidateId => $scoreValue) {
            $topFive = TopFiveCandidates::where('candidate_id', $candidateId)->first();

            if (!$topFive) {
                continue;
            }

            $topFiveId = $topFive->id;

            $this->scores->updateOrCreateScore($judgeId, $topFiveId, $category, $scoreValue);
        }

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
