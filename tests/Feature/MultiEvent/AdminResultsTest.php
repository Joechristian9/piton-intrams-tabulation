<?php

namespace Tests\Feature\MultiEvent;

use App\Legacy\LegacyImporter;
use App\Legacy\LegacyResults;
use App\Models\Event;
use App\Models\User;
use App\Support\AdminEventContext;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminResultsTest extends TestCase
{
    use BuildsEvents, RefreshDatabase, SeedsLegacyData;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_event_one_category_page_matches_the_old_results(): void
    {
        $this->seedLegacy(withAdminRow: false);
        $legacy = LegacyResults::snapshot()['category:casual_wear']['female'];
        $event = app(LegacyImporter::class)->import();
        $category = $this->category($event, 'Sports Wear');

        $this->actingAs($this->admin)
            ->get(route('admin.results.category', [$event, $category]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Results/Category')
                ->where('event.name', 'PITON Pageant')
                ->where('category.name', 'Sports Wear')
                ->where('groups.0.name', 'Female')
                // JSON drops '.0' from whole numbers, so compare as floats.
                ->where('groups.0.rows', fn ($rows) => collect($rows)->map(fn ($r) => (float) $r['total'])->all() === array_column($legacy, 'total')
                    && collect($rows)->pluck('rank')->all() === array_column($legacy, 'rank')));
    }

    public function test_round_one_and_standings_pages_render_for_admins(): void
    {
        $event = $this->makeEvent(['status' => Event::LIVE]);

        $this->actingAs($this->admin)->get(route('admin.results.round1', $event))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Results/Round')
                ->where('categories.0.name', 'Sports Wear')
                ->where('event.finalists_per_group', 3)
                ->where('event.finalistsSet', false));

        $this->actingAs($this->admin)->get(route('admin.results.standings', $event))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Results/Standings')
                ->where('mode', 'finals')
                ->where('categories.0.name', 'Delivery')
                ->has('judges'));
    }

    public function test_judges_cannot_open_results(): void
    {
        $event = $this->makeEvent(['status' => Event::LIVE]);
        $judge = $this->addJudges($event, 1)->first();

        $this->actingAs($judge)->get(route('admin.results.category', [$event, $this->category($event, 'Sports Wear')]))->assertForbidden();
        $this->actingAs($judge)->get(route('admin.results.round1', $event))->assertForbidden();
        $this->actingAs($judge)->get(route('admin.results.standings', $event))->assertForbidden();
    }

    public function test_category_of_another_event_is_404(): void
    {
        $a = $this->makeEvent();
        $b = $this->makeEvent();

        $this->actingAs($this->admin)
            ->get(route('admin.results.category', [$a, $this->category($b, 'Sports Wear')]))
            ->assertNotFound();
    }

    public function test_admin_context_follows_the_last_event_opened_then_defaults_to_the_latest_live_one(): void
    {
        $older = $this->makeEvent(['status' => Event::LIVE]);
        $older->forceFill(['started_at' => now()->subDay()])->save();
        $newer = $this->makeEvent(['status' => Event::LIVE]);
        $newer->forceFill(['started_at' => now()])->save();
        $this->makeEvent(['status' => Event::SETUP]);

        $request = Request::create('/');
        $request->setLaravelSession(app('session.store'));
        $this->assertSame($newer->id, AdminEventContext::current($request)->id);

        $this->actingAs($this->admin)->get(route('admin.results.round1', $older))
            ->assertInertia(fn (Assert $page) => $page->where('nav.event.id', $older->id));
        $this->actingAs($this->admin)->get('/profile')
            ->assertInertia(fn (Assert $page) => $page->where('nav.event.id', $older->id));
    }

    public function test_admin_nav_lists_result_pages_for_the_event(): void
    {
        $event = $this->makeEvent(['status' => Event::LIVE], ['Female'], [[1, 'Swim Wear', 25], [2, 'Delivery', 40]]);
        $other = $this->makeEvent(['status' => Event::SETUP]);

        $nav = Navigation::for($this->admin, $event);

        $this->assertSame(['Top 3 Selection', 'Top 3 Finalist', 'Management'], array_column($nav['sections'], 'label'));
        $this->assertSame(['Swim Wear', 'Top 3 Selection Results'], array_column($nav['sections'][0]['items'], 'label'));
        $this->assertSame(route('admin.results.round1', $event, false), $nav['sections'][0]['items'][1]['href']);
        $this->assertSame(['Delivery', 'Final Standings'], array_column($nav['sections'][1]['items'], 'label'));
        $this->assertSame([$event->id, $other->id], array_column($nav['events'], 'id'));
    }

    public function test_single_round_admin_nav(): void
    {
        $event = $this->makeEvent(['status' => Event::LIVE, 'rounds' => 1, 'finalists_per_group' => null], ['Female'], [[1, 'Talent', 50]]);

        $nav = Navigation::for($this->admin, $event);

        $this->assertSame(['Categories', 'Management'], array_column($nav['sections'], 'label'));
        $this->assertSame(['Talent', 'Final Standings'], array_column($nav['sections'][0]['items'], 'label'));
    }
}
