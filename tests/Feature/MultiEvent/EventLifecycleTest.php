<?php

namespace Tests\Feature\MultiEvent;

use App\Models\Category;
use App\Models\Event;
use App\Models\User;
use App\Support\LiveVersions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventLifecycleTest extends TestCase
{
    use BuildsEvents, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function settings(array $overrides = []): array
    {
        return [
            'name' => 'Mr & Ms ICT 2027', 'code' => 'ict27', 'rounds' => 2,
            'finalists_per_group' => 3, 'finals_from_zero' => true, ...$overrides,
        ];
    }

    private function as(): static
    {
        return $this->actingAs($this->admin);
    }

    public function test_admin_creates_an_event_in_setup(): void
    {
        $this->as()->post(route('admin.events.store'), $this->settings())
            ->assertRedirect(route('admin.events.edit', Event::sole()));

        $event = Event::sole();
        $this->assertSame(['Mr & Ms ICT 2027', 'ict27', Event::SETUP, 2, 3, true, null, null], [
            $event->name, $event->code, $event->status, $event->rounds, $event->finalists_per_group,
            $event->finals_from_zero, $event->round1_weight, $event->finals_weight,
        ]);
    }

    public function test_settings_are_validated(): void
    {
        Event::factory()->create(['code' => 'taken']);

        $this->as()->post(route('admin.events.store'), $this->settings(['code' => 'taken']))->assertSessionHasErrors('code');
        $this->as()->post(route('admin.events.store'), $this->settings(['code' => 'has space']))->assertSessionHasErrors('code');
        $this->as()->post(route('admin.events.store'), $this->settings(['rounds' => 3]))->assertSessionHasErrors('rounds');
        $this->as()->post(route('admin.events.store'), $this->settings(['finalists_per_group' => null]))->assertSessionHasErrors('finalists_per_group');
        $this->as()->post(route('admin.events.store'), $this->settings(['finals_from_zero' => false, 'round1_weight' => 40, 'finals_weight' => 50]))
            ->assertSessionHasErrors(['round1_weight' => 'Round 1 and Finals weights must add up to 100.']);
        $this->assertSame(1, Event::count());
    }

    public function test_carry_over_weights_are_saved_and_single_round_clears_finals_settings(): void
    {
        $this->as()->post(route('admin.events.store'), $this->settings(['finals_from_zero' => false, 'round1_weight' => 40, 'finals_weight' => 60]));
        $this->assertSame([40, 60], [Event::sole()->round1_weight, Event::sole()->finals_weight]);

        $this->as()->post(route('admin.events.store'), $this->settings(['code' => 'solo', 'rounds' => 1, 'finalists_per_group' => null]));
        $solo = Event::where('code', 'solo')->sole();
        $this->assertNull($solo->finalists_per_group);
        $this->assertTrue($solo->finals_from_zero);
    }

    public function test_settings_lock_once_scoring_has_started(): void
    {
        $event = $this->makeEvent(['status' => Event::LIVE]);
        $judge = $this->addJudges($event, 1)->first();
        $this->score($this->category($event, 'Sports Wear'), $this->addCandidate($event, 'Female', 1), $judge, 20);

        $this->as()->put(route('admin.events.update', $event), $this->settings(['code' => $event->code, 'rounds' => 1, 'finalists_per_group' => null]))
            ->assertSessionHasErrors(['rounds' => "This can't change after scoring has started."]);
        $this->as()->put(route('admin.events.update', $event), $this->settings(['code' => $event->code, 'finals_from_zero' => false, 'round1_weight' => 50, 'finals_weight' => 50]))
            ->assertSessionHasErrors('finals_from_zero');

        // Renaming is still fine.
        $this->as()->put(route('admin.events.update', $event), $this->settings(['code' => $event->code, 'name' => 'Renamed']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Renamed', $event->fresh()->name);
    }

    public function test_finalists_per_group_locks_after_finalists_and_code_locks_after_judges(): void
    {
        $event = $this->makeEvent();
        $this->addJudges($event, 1);

        $this->as()->put(route('admin.events.update', $event), $this->settings(['code' => 'newcode']))
            ->assertSessionHasErrors(['code' => "The code can't change after judges are created."]);

        $this->setFinalists($event, $this->addCandidate($event, 'Female', 1));
        $this->as()->put(route('admin.events.update', $event), $this->settings(['code' => $event->code, 'finalists_per_group' => 5]))
            ->assertSessionHasErrors('finalists_per_group');
    }

    /** Review Focus 4: an incomplete event can't start. */
    public function test_start_needs_judges_groups_and_categories_for_each_round(): void
    {
        $event = $this->makeEvent([], [], [[1, 'A', 10]]);

        $this->as()->post(route('admin.events.start', $event), ['password' => 'password'])
            ->assertSessionHasErrors(['event' => 'Before starting, add: judges, groups, Round 2 categories.']);
        $this->assertSame(Event::SETUP, $event->fresh()->status);
    }

    public function test_switching_to_one_round_needs_round_two_cleared_first(): void
    {
        $event = $this->makeEvent([], ['Female'], [[1, 'A', 10], [2, 'B', 40]]);

        $this->as()->put(route('admin.events.update', $event), $this->settings(['code' => $event->code, 'rounds' => 1, 'finalists_per_group' => null]))
            ->assertSessionHasErrors(['rounds' => 'Delete the Round 2 categories first.']);
        $this->assertSame(2, $event->fresh()->rounds);

        $this->category($event, 'B')->delete();
        $this->setFinalists($event, $this->addCandidate($event, 'Female', 1));
        $this->as()->put(route('admin.events.update', $event), $this->settings(['code' => $event->code, 'rounds' => 1, 'finalists_per_group' => null]))
            ->assertSessionHasErrors(['rounds' => "This can't change after the finalists are set."]);
    }

    public function test_start_needs_candidates_in_every_group(): void
    {
        $event = $this->makeEvent();
        $this->addJudges($event, 1);
        $this->addCandidate($event, 'Female', 1);

        $this->as()->post(route('admin.events.start', $event), ['password' => 'password'])
            ->assertSessionHasErrors(['event' => 'Before starting, add: candidates in Male.']);
        $this->assertSame(Event::SETUP, $event->fresh()->status);
    }

    public function test_duplicate_code_stays_within_20_characters(): void
    {
        $event = $this->makeEvent(['code' => 'mr-ms-intrams-2026x']);   // 19 chars
        Event::factory()->create(['code' => 'mr-ms-intrams-c']);

        $this->as()->post(route('admin.events.duplicate', $event))->assertRedirect();

        $copy = Event::where('name', 'Copy of ' . $event->name)->sole();
        $this->assertLessThanOrEqual(20, strlen($copy->code));
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $copy->code);
        // The copy's settings can be saved without touching the code.
        $this->as()->put(route('admin.events.update', $copy), $this->settings(['code' => $copy->code, 'name' => 'Next year']))
            ->assertSessionHasNoErrors();
    }

    public function test_start_and_close_need_the_password_and_bump_the_event_stamp(): void
    {
        $event = $this->makeEvent();
        $this->addJudges($event, 1);
        $this->addCandidate($event, 'Female', 1);
        $this->addCandidate($event, 'Male', 1);

        $this->as()->post(route('admin.events.start', $event), ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->assertSame(Event::SETUP, $event->fresh()->status);

        $before = LiveVersions::all($event->id)['event'];
        $this->as()->post(route('admin.events.start', $event), ['password' => 'password'])->assertSessionHasNoErrors();
        $this->assertSame(Event::LIVE, $event->fresh()->status);
        $this->assertNotNull($event->fresh()->started_at);
        $this->assertNotSame($before, LiveVersions::all($event->id)['event']);

        $this->as()->post(route('admin.events.close', $event), ['password' => 'password'])->assertSessionHasNoErrors();
        $this->assertSame(Event::CLOSED, $event->fresh()->status);

        // A closed event can start again.
        $this->as()->post(route('admin.events.start', $event), ['password' => 'password'])->assertSessionHasNoErrors();
        $this->assertSame(Event::LIVE, $event->fresh()->status);
    }

    public function test_starting_one_event_leaves_other_live_events_running(): void
    {
        $a = $this->makeEvent(['status' => Event::LIVE]);
        $b = $this->makeEvent();
        $this->addJudges($b, 1);
        $this->addCandidate($b, 'Female', 1);
        $this->addCandidate($b, 'Male', 1);

        $this->as()->post(route('admin.events.start', $b), ['password' => 'password'])->assertSessionHasNoErrors();

        $this->assertSame([Event::LIVE, Event::LIVE], [$a->fresh()->status, $b->fresh()->status]);
    }

    public function test_delete_needs_password_and_no_scores(): void
    {
        $event = $this->makeEvent();
        $judge = $this->addJudges($event, 1)->first();
        $candidate = $this->addCandidate($event, 'Female', 1);
        $this->score($this->category($event, 'Sports Wear'), $candidate, $judge, 20);

        $this->as()->delete(route('admin.events.destroy', $event), ['password' => 'password'])
            ->assertSessionHasErrors(['event' => "This event has scores, so it can't be deleted. Close it instead."]);

        $empty = $this->makeEvent();
        $this->as()->delete(route('admin.events.destroy', $empty), ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->as()->delete(route('admin.events.destroy', $empty), ['password' => 'password'])
            ->assertRedirect(route('admin.events.index'));
        $this->assertModelMissing($empty);
        $this->assertModelExists($event);
    }

    public function test_duplicate_copies_settings_groups_and_categories_only(): void
    {
        $event = $this->makeEvent(['name' => 'Pageant', 'code' => 'pg', 'finalists_per_group' => 5], ['Female', 'Male'], [[1, 'A', 10], [2, 'B', 40]]);
        $this->addJudges($event, 1);
        $this->addCandidate($event, 'Female', 1);
        Event::factory()->create(['code' => 'pg-copy']);

        $this->as()->post(route('admin.events.duplicate', $event))->assertRedirect();

        $copy = Event::where('name', 'Copy of Pageant')->sole();
        $this->assertSame(['pg-copy-2', Event::SETUP, 5], [$copy->code, $copy->status, $copy->finalists_per_group]);
        $this->assertSame(['Female', 'Male'], $copy->groups->pluck('name')->all());
        $this->assertSame([[1, 'A', 10.0], [2, 'B', 40.0]], $copy->categories->map(fn (Category $c) => [$c->round, $c->name, $c->max_score])->all());
        $this->assertSame(0, $copy->candidates()->count());
        $this->assertSame(0, $copy->judges()->count());
    }

    public function test_index_and_edit_pages(): void
    {
        $event = $this->makeEvent(['name' => 'Pageant']);

        $this->as()->get(route('admin.events.index'))
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Events/Index')->where('events.0.name', 'Pageant')->where('events.0.hasScores', false));
        $this->as()->get(route('admin.events.edit', $event))
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Events/Edit')->where('event.code', $event->code)->where('locks.hasScores', false));
    }

    public function test_judges_cannot_manage_events(): void
    {
        $event = $this->makeEvent();
        $judge = $this->addJudges($event, 1)->first();

        $this->actingAs($judge)->get(route('admin.events.index'))->assertForbidden();
        $this->actingAs($judge)->post(route('admin.events.start', $event), ['password' => 'password'])->assertForbidden();
    }
}
