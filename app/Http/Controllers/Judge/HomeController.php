<?php

namespace App\Http\Controllers\Judge;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Where everyone lands after logging in. A judge goes straight to their event's
 * first category (the finals once finalists are set), or sees a waiting page
 * while the event isn't live. Admins go to the events list; judges not yet moved
 * to an event keep the dashboard.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if ($user->role === 'admin') {
            return redirect()->route('admin.events.index');
        }

        $event = $user->event;

        if (! $event) {
            return Inertia::render('Dashboard');
        }

        if (! $event->isLive()) {
            return Inertia::render('Judge/Waiting', [
                'state' => $event->status === Event::CLOSED ? 'ended' : 'not_started',
                'eventName' => $event->name,
            ]);
        }

        $round = $event->rounds === 2 && $event->finalistsSet() ? 2 : 1;
        $first = $event->categories->firstWhere('round', $round);

        return $first
            ? redirect()->route('score.show', $first)
            : Inertia::render('Judge/Waiting', ['state' => 'not_started', 'eventName' => $event->name]);
    }
}
