<?php

namespace App\Http\Controllers;

use App\Repositories\CandidateRepository;
use App\Models\TopFiveSelectionScore;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CandidateController extends Controller
{
    protected $candidates;

    public function __construct(CandidateRepository $candidates)
    {
        $this->candidates = $candidates;
    }

    protected function renderCategory(string $view, string $categoryField)
    {
        $candidates = $this->candidates->all();

        $user = Auth::user();


        $judgeId = $user->id;

        // Load existing scores for the logged-in judge
        $scores = TopFiveSelectionScore::where('judge_id', $judgeId)
            ->pluck($categoryField, 'candidate_id');

        // Attach existing score to each candidate
        foreach ($candidates as $candidate) {
            $candidate->existing_score = $scores[$candidate->id] ?? null;
        }

        return Inertia::render($view, [
            'candidates' => $candidates,
        ]);
    }

    public function production_number()
    {
        return $this->renderCategory('Categories/ProductionNumber', 'production_number');
    }

    public function casual_wear()
    {
        return $this->renderCategory('Categories/CasualWear', 'casual_wear');
    }

    public function swim_wear()
    {
        return $this->renderCategory('Categories/SwimWear', 'swim_wear');
    }

    public function formal_wear()
    {
        return $this->renderCategory('Categories/FormalWear', 'formal_wear');
    }

    public function closed_door_interview()
    {
        return $this->renderCategory('Categories/ClosedDoorInterview', 'closed_door_interview');
    }
}
