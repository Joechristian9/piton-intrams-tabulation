<?php

namespace App\Http\Controllers;

use App\Support\ScoreSubmissionFeed;
use Illuminate\Http\Request;

class ScoreSubmissionController extends Controller
{
    /**
     * Recent judge submissions for the admin toast alerts.
     */
    public function index(Request $request)
    {
        abort_unless($request->user()->role === 'admin', 403);

        $after = $request->query('after');

        return response()->json(
            ScoreSubmissionFeed::since($after === null ? null : (int) $after)
        );
    }
}
