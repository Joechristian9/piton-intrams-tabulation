<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Support\LiveVersions;
use App\Support\ScoreSubmissionFeed;
use Illuminate\Http\Request;

/** Polled by admin pages: an event's new submissions (toasts) and live stamps. */
class ScoreSubmissionController extends Controller
{
    public function index(Request $request, Event $event)
    {
        $after = $request->query('after');

        return response()->json([
            ...ScoreSubmissionFeed::since($event->id, $after === null ? null : (int) $after),
            'live' => LiveVersions::all($event->id),
        ]);
    }
}
