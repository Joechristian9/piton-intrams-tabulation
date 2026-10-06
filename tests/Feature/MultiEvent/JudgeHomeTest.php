<?php

namespace Tests\Feature\MultiEvent;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class JudgeHomeTest extends TestCase
{
    use BuildsEvents, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_judge_of_a_live_event_lands_on_the_first_round_one_category(): void
    {
        $event = $this->makeEvent(['status' => Event::LIVE], ['Female'], [[1, 'Production Number', 10], [1, 'Swim Wear', 25], [2, 'Delivery', 40]]);
        $judge = $this->addJudges($event, 1)->first();

        $this->actingAs($judge)->get(route('dashboard'))
            ->assertRedirect(route('score.show', $this->category($event, 'Production Number')));
    }

    public function test_after_finalists_judges_land_on_the_first_finals_category(): void
    {
        $event = $this->makeEvent(['status' => Event::LIVE], ['Female'], [[1, 'Production Number', 10], [2, 'Delivery', 40]]);
        $judge = $this->addJudges($event, 1)->first();
        $this->setFinalists($event, $this->addCandidate($event, 'Female', 1));

        $this->actingAs($judge)->get(route('dashboard'))
            ->assertRedirect(route('score.show', $this->category($event, 'Delivery')));
    }

    public function test_event_not_started_shows_the_waiting_page(): void
    {
        $event = $this->makeEvent(['status' => Event::SETUP]);
        $judge = $this->addJudges($event, 1)->first();

        $this->actingAs($judge)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Judge/Waiting')
                ->where('state', 'not_started')
                ->where('eventName', $event->name));
    }

    public function test_closed_event_shows_that_it_ended(): void
    {
        $event = $this->makeEvent(['status' => Event::CLOSED]);
        $judge = $this->addJudges($event, 1)->first();

        $this->actingAs($judge)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->component('Judge/Waiting')->where('state', 'ended'));
    }

    public function test_admins_land_on_the_events_list(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertRedirect(route('admin.events.index'));
    }
}
