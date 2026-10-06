<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Event;
use Illuminate\Http\Request;

/**
 * The event an admin is looking at: the one in the URL (remembered in the
 * session), else the last one they opened, else the most recently started live
 * event, else the newest event.
 */
class AdminEventContext
{
    private const SESSION_KEY = 'admin_event_id';

    public static function current(Request $request): ?Event
    {
        $fromRoute = self::fromRoute($request);
        if ($fromRoute) {
            $request->session()->put(self::SESSION_KEY, $fromRoute->id);

            return $fromRoute;
        }

        $remembered = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;
        if ($remembered && $event = Event::find($remembered)) {
            return $event;
        }

        return Event::where('status', Event::LIVE)->orderByDesc('started_at')->orderByDesc('id')->first()
            ?? Event::orderByDesc('id')->first();
    }

    private static function fromRoute(Request $request): ?Event
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
