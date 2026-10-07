<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** The signed-in home (/home): the PITON hero, opened from the sidebar's logo header. */
class HomePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admins_and_judges_can_open_the_home_page(): void
    {
        foreach (['admin', 'judge'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('home'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component('Home'));
        }
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));
    }

    public function test_logging_in_still_lands_admins_on_events_not_home(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('dashboard'))
            ->assertRedirect(route('admin.events.index'));
    }
}
