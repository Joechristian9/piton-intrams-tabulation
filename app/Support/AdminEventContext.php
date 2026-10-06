<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Event;
use Illuminate\Http\Request;

/**
 * The event an admin is looking at: the one in the URL (an event's setup,
 * results or notify pages, or one of its categories). Pages outside an event —
 * the events list, the account page — have none, so the sidebar shows only
 * Management until the admin opens an event.
 */
class AdminEventContext
{
    public static function current(Request $request): ?Event
    {
        $route = $request->route();
        if (! $route) {
            return null;
        }

        $event = $route->parameter('event');
        if ($event instanceof Event) {
            return $event;
        }

        $category = $route->parameter('category');

        return $category instanceof Category ? $category->event : null;
    }
}
