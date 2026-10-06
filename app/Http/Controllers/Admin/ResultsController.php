<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use App\Results\EventResults;
use Inertia\Inertia;

/** Results pages for one event (admins only; routes use the `admin` middleware). */
class ResultsController extends Controller
{
    public function category(Event $event, Category $category)
    {
        abort_unless($category->event_id === $event->id, 404);

        return Inertia::render('Admin/Results/Category', [
            'event' => self::eventPayload($event),
            ...(new EventResults($event))->category($category),
        ]);
    }

    public function round1(Event $event)
    {
        return Inertia::render('Admin/Results/Round', [
            'event' => self::eventPayload($event),
            ...(new EventResults($event))->round(1),
            'finalistIds' => $event->finalists()->pluck('candidate_id')->all(),
        ]);
    }

    public function standings(Event $event)
    {
        $results = new EventResults($event);
        $standings = $results->standings();
        // Non-weighted standings show the deciding round's category averages.
        $round = $standings['mode'] === 'single' ? 1 : 2;

        return Inertia::render('Admin/Results/Standings', [
            'event' => self::eventPayload($event),
            ...$standings,
            'categories' => $results->round($round)['categories'],
            'judges' => $results->judges(),
        ]);
    }

    public static function eventPayload(Event $event): array
    {
        return [
            'id' => $event->id,
            'name' => $event->name,
            'status' => $event->status,
            'rounds' => $event->rounds,
            'finalists_per_group' => $event->finalists_per_group,
            'finals_from_zero' => $event->finals_from_zero,
            'finalistsSet' => $event->finalistsSet(),
        ];
    }
}
