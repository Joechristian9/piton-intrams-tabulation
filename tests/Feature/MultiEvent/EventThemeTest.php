<?php

namespace Tests\Feature\MultiEvent;

use App\Models\CustomTheme;
use App\Models\Event;
use App\Models\User;
use App\Support\AppTheme;
use App\Support\LiveVersions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventThemeTest extends TestCase
{
    use BuildsEvents, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function settings(Event $event, array $overrides = []): array
    {
        return [...$event->only(['name', 'code', 'rounds', 'finalists_per_group']), 'finals_from_zero' => true, ...$overrides];
    }

    public function test_each_event_uses_its_own_theme_and_pages_outside_events_use_the_default(): void
    {
        $blue = $this->makeEvent(['status' => Event::LIVE, 'theme' => 'blue']);
        $plain = $this->makeEvent(['status' => Event::LIVE]);
        AppTheme::set('emerald');

        // Judges: their event's theme, or the default when the event has none.
        $this->actingAs($this->addJudges($blue, 1)->first())->get(route('score.show', $this->category($blue, 'Sports Wear')))
            ->assertSee('data-theme="blue"', false)
            ->assertInertia(fn (Assert $page) => $page->where('theme', 'blue'));
        $this->actingAs($this->addJudges($plain, 1)->first())->get(route('score.show', $this->category($plain, 'Sports Wear')))
            ->assertInertia(fn (Assert $page) => $page->where('theme', 'emerald'));

        // Admins: the event they have open; the default elsewhere.
        $this->actingAs($this->admin)->get(route('admin.results.standings', $blue))
            ->assertInertia(fn (Assert $page) => $page->where('theme', 'blue'));
        $this->actingAs($this->admin)->get(route('admin.events.edit', $blue))
            ->assertInertia(fn (Assert $page) => $page->where('theme', 'blue')->where('event.theme', 'blue'));
        $this->actingAs($this->admin)->get(route('admin.events.index'))
            ->assertInertia(fn (Assert $page) => $page->where('theme', 'emerald')->where('themes.default', 'emerald'));
    }

    public function test_a_custom_event_theme_sends_its_colors(): void
    {
        $custom = CustomTheme::create(['name' => 'School', 'accent' => '#3b82f6', 'surface' => '#0f172a']);
        $event = $this->makeEvent(['status' => Event::LIVE, 'theme' => $custom->key()]);

        $this->actingAs($this->addJudges($event, 1)->first())->get(route('score.show', $this->category($event, 'Sports Wear')))
            ->assertSee('--accent-400: 59 130 246', false)
            ->assertInertia(fn (Assert $page) => $page->where('theme', $custom->key())->where('themeVars.--accent-400', '59 130 246'));
    }

    public function test_admin_sets_an_event_theme_in_its_settings(): void
    {
        $event = $this->makeEvent();
        $before = LiveVersions::all($event->id)['event'];

        $this->actingAs($this->admin)->put(route('admin.events.update', $event), $this->settings($event, ['theme' => 'violet']))
            ->assertSessionHasNoErrors();
        $this->assertSame('violet', $event->fresh()->theme);
        $this->assertNotSame($before, LiveVersions::all($event->id)['event']);   // judges' pages reload

        // A form without the field keeps it; null goes back to Default; unknown themes are refused.
        $this->actingAs($this->admin)->put(route('admin.events.update', $event), $this->settings($event, ['name' => 'Renamed']));
        $this->assertSame('violet', $event->fresh()->theme);
        $this->actingAs($this->admin)->put(route('admin.events.update', $event), $this->settings($event, ['theme' => null]));
        $this->assertNull($event->fresh()->theme);
        $this->actingAs($this->admin)->put(route('admin.events.update', $event), $this->settings($event, ['theme' => 'neon']))
            ->assertSessionHasErrors('theme');

        // New events and copies.
        $this->actingAs($this->admin)->post(route('admin.events.store'), [
            'name' => 'Talent Night', 'code' => 'talent', 'rounds' => 1, 'theme' => 'sunset',
        ])->assertSessionHasNoErrors();
        $talent = Event::where('code', 'talent')->sole();
        $this->assertSame('sunset', $talent->theme);
        $this->actingAs($this->admin)->post(route('admin.events.duplicate', $talent));
        $this->assertSame('sunset', Event::where('code', 'talent-copy')->sole()->theme);
    }

    public function test_a_theme_used_by_an_event_cannot_be_deleted_and_the_theme_page_lists_its_events(): void
    {
        $custom = CustomTheme::create(['name' => 'School', 'accent' => '#3b82f6', 'surface' => '#0f172a']);
        $event = $this->makeEvent(['name' => 'Mr & Ms', 'theme' => $custom->key()]);
        $this->makeEvent(['theme' => 'blue']);
        $this->makeEvent();

        $this->actingAs($this->admin)->get(route('admin.theme.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('customThemes.0.usedBy', ['Mr & Ms'])
                ->where('presets.1.key', 'blue')
                ->has('presets.1.usedBy', 1)
                ->where('defaultCount', 1));

        $this->actingAs($this->admin)->delete(route('admin.themes.destroy', $custom))->assertSessionHasErrors('theme');
        $this->assertNotNull($custom->fresh());

        // A theme removed some other way: the event falls back to the default.
        $event->forceFill(['theme' => 'custom-999'])->save();
        $this->assertSame(['key' => 'gold', 'vars' => null], AppTheme::resolve($event->fresh()));
    }

    public function test_changing_the_default_reloads_judges_of_events_on_default(): void
    {
        $onDefault = $this->makeEvent();
        $ownTheme = $this->makeEvent(['theme' => 'blue']);
        $before = [LiveVersions::all($onDefault->id)['event'], LiveVersions::all($ownTheme->id)['event']];

        $this->actingAs($this->admin)->put(route('admin.theme.update'), ['theme' => 'violet'])->assertSessionHasNoErrors();

        $this->assertNotSame($before[0], LiveVersions::all($onDefault->id)['event']);
        $this->assertSame($before[1], LiveVersions::all($ownTheme->id)['event']);
    }
}
