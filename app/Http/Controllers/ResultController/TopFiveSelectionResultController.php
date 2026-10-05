<?php

namespace App\Http\Controllers\ResultController;

use App\Http\Controllers\Controller;
use App\Services\TopFiveSelectionService;
use App\Models\Candidate;
use App\Models\TopFiveCandidates;
use App\Support\LiveVersions;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TopFiveSelectionResultController extends Controller
{
    /** Finalists per gender. */
    private const FINALIST_COUNT = 3;

    protected $service;

    public function __construct(TopFiveSelectionService $service)
    {
        $this->service = $service;
    }

    public function productionNumberResults()
    {
        $results = $this->service->getResultsPerCategory('production_number');

        return Inertia::render('Admin/ProductionNumberResult', [
            'maleCandidates' => $results['maleCandidates'],
            'femaleCandidates' => $results['femaleCandidates'],
            'judgeOrder' => $results['judgeOrder'],
            'categoryName' => 'Production Number',
        ]);
    }

    public function casualWearResults()
    {
        $results = $this->service->getResultsPerCategory('casual_wear');

        return Inertia::render('Admin/CasualWearResult', [
            'maleCandidates' => $results['maleCandidates'],
            'femaleCandidates' => $results['femaleCandidates'],
            'judgeOrder' => $results['judgeOrder'],
            'categoryName' => 'Sports Wear',
        ]);
    }

    public function swimWearResults()
    {
        $results = $this->service->getResultsPerCategory('swim_wear');

        return Inertia::render('Admin/SwimWearResult', [
            'maleCandidates' => $results['maleCandidates'],
            'femaleCandidates' => $results['femaleCandidates'],
            'judgeOrder' => $results['judgeOrder'],
            'categoryName' => 'Swim Wear',
        ]);
    }

    public function formalWearResults()
    {
        $results = $this->service->getResultsPerCategory('formal_wear');

        return Inertia::render('Admin/FormalWearResult', [
            'maleCandidates' => $results['maleCandidates'],
            'femaleCandidates' => $results['femaleCandidates'],
            'judgeOrder' => $results['judgeOrder'],
            'categoryName' => 'Formal Wear',
        ]);
    }
    public function closedDoorInterviewResults()
    {
        $results = $this->service->getResultsPerCategory('closed_door_interview');

        return Inertia::render('Admin/ClosedDoorInterviewResult', [
            'maleCandidates' => $results['maleCandidates'],
            'femaleCandidates' => $results['femaleCandidates'],
            'judgeOrder' => $results['judgeOrder'],
            'categoryName' => 'Casual Interview',
        ]);
    }
    public function topFiveSelectionResults()
    {
        $results = $this->service->getTopFiveSelectionResults();

        return Inertia::render('Admin/TopFiveSelectionResult', [
            'maleCandidates' => $results['maleCandidates'],
            'femaleCandidates' => $results['femaleCandidates'],
            'categories' => $results['categories'],
            'judgeOrder' => $results['judgeOrder'],
            'categoryName' => 'Top Three Selection',
        ]);
    }

    public function setTopFive(Request $request)
    {
        // Setting the finalists closes Round 1 for good: admins only, and they must
        // re-enter their own account password to confirm.
        abort_unless($request->user()->role === 'admin', 403);

        $request->validate([
            'password' => ['required', 'current_password'],
            'candidate_ids' => 'required|array',
            'candidate_ids.*' => 'distinct|exists:candidates,id',
        ]);

        $ids = $request->candidate_ids;
        $perGender = Candidate::whereIn('id', $ids)->pluck('gender')->countBy();

        if (($perGender['male'] ?? 0) !== self::FINALIST_COUNT || ($perGender['female'] ?? 0) !== self::FINALIST_COUNT) {
            throw ValidationException::withMessages([
                'candidate_ids' => 'Select exactly ' . self::FINALIST_COUNT . ' male and ' . self::FINALIST_COUNT . ' female finalists.',
            ]);
        }

        // Only remove finalists who dropped out, so finals scores already
        // given to candidates who stay in are kept.
        DB::transaction(function () use ($ids) {
            TopFiveCandidates::whereNotIn('candidate_id', $ids)->delete();

            $existing = TopFiveCandidates::pluck('candidate_id')->all();
            foreach (array_diff($ids, $existing) as $candidateId) {
                TopFiveCandidates::create(['candidate_id' => $candidateId]);
            }
        });

        // Open judge and admin pages reload: the finals categories appear, and removed
        // finalists' scores are gone.
        LiveVersions::bump(LiveVersions::FINALISTS, LiveVersions::SCORES);

        return redirect()->back()->with('success', 'Top 3 Male & Female saved successfully!');
    }
}
