<?php

namespace App\Http\Middleware;

use App\Models\TopFiveCandidates;
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
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            // Sidebar items built from the user's event (App\Support\Navigation).
            'nav' => fn () => Navigation::for($request->user(), null),
            // The sidebar hides the Top 3 Finalist categories until the admin sets them.
            'finalistsSet' => fn () => $request->user() !== null && TopFiveCandidates::exists(),
            // Version stamps this page was built with; the pollers reload when they change.
            // Judges: their event. Admins: the old pages' slot until the admin event
            // context arrives (multi-event plan, Task 10).
            'live' => fn () => $request->user()
                ? LiveVersions::all($request->user()->event_id ?? LiveVersions::LEGACY)
                : null,
        ];
    }
}
