<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomTheme;
use App\Models\Event;
use App\Support\AppTheme;
use App\Support\LiveVersions;
use App\Support\ThemeColors;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Color themes: the default theme picked here (pages outside an event, and events
 * left on "Default"), and the themes admins make from two colors. Each event picks
 * its own theme in its settings (EventController).
 */
class ThemeController extends Controller
{
    public function edit()
    {
        // Which events use each theme (events on "Default" are listed under the default).
        $usedBy = Event::whereNotNull('theme')->orderBy('name')->get(['name', 'theme'])
            ->groupBy('theme')->map(fn ($events) => $events->pluck('name')->all());

        return Inertia::render('Admin/Theme', [
            'presets' => collect(AppTheme::presets())->map(fn ($p) => [...$p, 'usedBy' => $usedBy[$p['key']] ?? []]),
            'customThemes' => CustomTheme::orderBy('name')->get()->map(fn (CustomTheme $t) => [
                'id' => $t->id,
                'key' => $t->key(),
                'name' => $t->name,
                'accent' => $t->accent,
                'surface' => $t->surface,
                'vars' => $t->variables(),
                'usedBy' => $usedBy[$t->key()] ?? [],
            ]),
            'defaultCount' => Event::whereNull('theme')->count(),
        ]);
    }

    /** Set the default theme (a preset key or "custom-{id}"). */
    public function update(Request $request)
    {
        $data = $request->validate([
            'theme' => ['required', 'string', function ($attribute, $value, $fail) {
                if (! AppTheme::exists($value)) {
                    $fail("That theme doesn't exist any more. Refresh the page.");
                }
            }],
        ]);

        AppTheme::set($data['theme']);
        $this->refreshEvents(Event::whereNull('theme'));

        return back();
    }

    public function store(Request $request)
    {
        $theme = CustomTheme::create($this->validated($request, null));

        if ($request->boolean('apply')) {
            AppTheme::set($theme->key());
            $this->refreshEvents(Event::whereNull('theme'));
        }

        return back();
    }

    public function updateCustom(Request $request, CustomTheme $customTheme)
    {
        $customTheme->update($this->validated($request, $customTheme));

        $events = AppTheme::current() === $customTheme->key()
            ? Event::where('theme', $customTheme->key())->orWhereNull('theme')
            : Event::where('theme', $customTheme->key());
        $this->refreshEvents($events);

        return back();
    }

    public function destroy(CustomTheme $customTheme)
    {
        if (AppTheme::current() === $customTheme->key()) {
            throw ValidationException::withMessages([
                'theme' => 'This is the default theme. Pick another default before deleting it.',
            ]);
        }
        $events = Event::where('theme', $customTheme->key())->orderBy('name')->pluck('name');
        if ($events->isNotEmpty()) {
            throw ValidationException::withMessages([
                'theme' => 'These events use this theme: ' . $events->implode(', ') . '. Pick another theme for them first.',
            ]);
        }

        $customTheme->delete();

        return back();
    }

    /** Judges' open pages reload (their event's stamp changes) so they get the new colors. */
    private function refreshEvents($query): void
    {
        foreach ($query->pluck('id') as $eventId) {
            LiveVersions::bump($eventId, LiveVersions::EVENT);
        }
    }

    private function validated(Request $request, ?CustomTheme $theme): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:40', Rule::unique('custom_themes', 'name')->ignore($theme?->id)],
            'accent' => ['required', 'string', 'regex:' . ThemeColors::HEX],
            'surface' => ['required', 'string', 'regex:' . ThemeColors::HEX],
        ], [
            'name.unique' => 'There is already a theme with this name.',
            'accent.regex' => 'Pick an accent color.',
            'surface.regex' => 'Pick a background color.',
        ]);

        $data = ['name' => trim($data['name']), 'accent' => strtolower($data['accent']), 'surface' => strtolower($data['surface'])];

        if ($problems = ThemeColors::problems($data['accent'], $data['surface'])) {
            throw ValidationException::withMessages($problems);
        }

        return $data;
    }
}
