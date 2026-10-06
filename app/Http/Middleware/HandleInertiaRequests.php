<?php

namespace App\Http\Middleware;

use App\Support\AdminEventContext;
use App\Support\LiveVersions;
use App\Support\Navigation;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // The event an admin is looking at, resolved once per request.
        $adminEvent = null;
        $resolveAdminEvent = function () use ($request, &$adminEvent) {
            if ($adminEvent === null && $request->user()?->role === 'admin') {
                $adminEvent = AdminEventContext::current($request) ?? false;
            }

            return $adminEvent ?: null;
        };

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            // Sidebar items built from the user's event (App\Support\Navigation).
            'nav' => fn () => Navigation::for($request->user(), $resolveAdminEvent()),
            // Version stamps this page was built with; the pollers reload when they change.
            // Judges: their event. Admins: the event they're looking at.
            'live' => function () use ($request, $resolveAdminEvent) {
                $eventId = $request->user()?->event_id ?? $resolveAdminEvent()?->id;

                return $eventId ? LiveVersions::all($eventId) : null;
            },
        ];
    }
}
