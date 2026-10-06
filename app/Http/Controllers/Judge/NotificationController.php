<?php

namespace App\Http\Controllers\Judge;

use App\Http\Controllers\Controller;
use App\Support\JudgeCallFeed;
use App\Support\LiveVersions;
use Illuminate\Http\Request;

/** Polled by judges' pages: their event's notifications and live stamps. */
class NotificationController extends Controller
{
    public function __invoke(Request $request)
    {
        $judge = $request->user();
        abort_unless($judge->role === 'judge', 403);

        if (! $judge->event_id) {
            return response()->json(['seq' => 0, 'events' => [], 'live' => null]);
        }

        return response()->json([
            ...JudgeCallFeed::forJudge($judge),
            'live' => LiveVersions::all($judge->event_id),
        ]);
    }
}
