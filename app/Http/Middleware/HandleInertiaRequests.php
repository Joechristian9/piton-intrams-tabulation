<?php

namespace App\Http\Middleware;

use App\Models\TopFiveCandidates;
use App\Support\LiveVersions;
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
            // The sidebar hides the Top 3 Finalist categories until the admin sets them.
            'finalistsSet' => fn () => $request->user() !== null && TopFiveCandidates::exists(),
            // Version stamps this page was built with; the pollers reload when they change.
            'live' => fn () => $request->user() ? LiveVersions::all() : null,
        ];
    }
}
