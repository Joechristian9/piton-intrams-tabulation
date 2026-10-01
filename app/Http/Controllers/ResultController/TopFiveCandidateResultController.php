<?php

namespace App\Http\Controllers\ResultController;

use App\Http\Controllers\Controller;
use App\Services\TopFiveService;
use Inertia\Inertia;

class TopFiveCandidateResultController extends Controller
{
    protected $service;

    public function __construct(TopFiveService $service)
    {
        $this->service = $service;
    }

    /**
     * Display results for Beauty of the Face and Figure category
     */
    public function beautyFaceResults()
    {
        $results = $this->service->getResultsPerCategory('face_and_figure');

        return Inertia::render('Admin/TopFiveCategories/BeautyFaceFigureResult', [
            'maleCandidates'   => $results['maleCandidates'],
            'femaleCandidates' => $results['femaleCandidates'],
            'judgeOrder'       => $results['judgeOrder'],
            'categoryName'     => 'Beauty of the Face and Figure',
        ]);
    }

    /**
     * Display results for Delivery category
     */
    public function deliveryResults()
    {
        $results = $this->service->getResultsPerCategory('delivery');

        return Inertia::render('Admin/TopFiveCategories/DeliveryResult', [
            'maleCandidates'   => $results['maleCandidates'],
            'femaleCandidates' => $results['femaleCandidates'],
            'judgeOrder'       => $results['judgeOrder'],
            'categoryName'     => 'Delivery',
        ]);
    }

    /**
     * Display results for Over-all Appeal / X-factor category
     */
    public function overallAppealResults()
    {
        $results = $this->service->getResultsPerCategory('overall_appeal');

        return Inertia::render('Admin/TopFiveCategories/OverallAppealResult', [
            'maleCandidates'   => $results['maleCandidates'],
            'femaleCandidates' => $results['femaleCandidates'],
            'judgeOrder'       => $results['judgeOrder'],
            'categoryName'     => 'Over-all Appeal / X-factor',
        ]);
    }
    public function totalResults()
    {
        $results = $this->service->getTotalResults();

        return Inertia::render('Admin/TopFiveCategories/TotalResults', [
            'maleCandidates'   => $results['maleCandidates'],
            'femaleCandidates' => $results['femaleCandidates'],
            'judgeOrder'       => $results['judgeOrder'],
            'categoryName'     => 'Total Combined Scores',
        ]);
    }
}
