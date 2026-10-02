<?php

namespace App\Http\Controllers;

use App\Models\TopFiveCandidates;
use App\Models\TopFiveScore;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TopFiveCandidateController extends Controller
{
    /**
     * Render Top Five candidates with existing scores for a specific category
     */
    protected function renderCategory(string $view, string $categoryField)
    {
        $judgeId = Auth::id();

        // One query for all of this judge's scores, instead of one per finalist.
        $judgeScores = TopFiveScore::where('judge_id', $judgeId)->get()->keyBy('top_five_id');

        $candidates = TopFiveCandidates::with('candidate')
            ->get()
            ->sortBy(function ($item) {
                return $item->candidate->candidate_number ?? 0;
            })
            ->values()
            ->map(function ($item) use ($judgeScores, $categoryField) {
                $score = $judgeScores->get($item->id);

                return [
                    'id' => $item->id,
                    'candidate_number' => $item->candidate->candidate_number ?? null,
                    'candidate_id' => $item->candidate_id,
                    'profile_img' => $item->candidate->profile_img ?? null,
                    'first_name' => $item->candidate->first_name ?? null,
                    'last_name' => $item->candidate->last_name ?? null,
                    'course' => $item->candidate->course ?? null,
                    'gender' => $item->candidate->gender ?? null,
                    $categoryField => $score->{$categoryField} ?? null,
                    'has_existing_score' => [
                        'face_and_figure' => $score->face_and_figure ?? null,
                        'delivery' => $score->delivery ?? null,
                        'overall_appeal' => $score->overall_appeal ?? null,
                    ],
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ];
            });

        return Inertia::render($view, [
            'candidates' => $candidates,
            'category' => $categoryField,
        ]);
    }


    public function faceAndFigure()
    {
        return $this->renderCategory('Categories/TopFiveFinalist/BeautyFaceFigure', 'face_and_figure');
    }

    public function delivery()
    {
        return $this->renderCategory('Categories/TopFiveFinalist/Delivery', 'delivery');
    }

    public function overallAppeal()
    {
        return $this->renderCategory('Categories/TopFiveFinalist/OverallAppeal', 'overall_appeal');
    }
}
