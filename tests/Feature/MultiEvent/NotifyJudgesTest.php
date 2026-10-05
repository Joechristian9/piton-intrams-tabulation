<?php

namespace Tests\Feature\MultiEvent;

use App\Models\Event;
use App\Models\User;
use App\Support\LiveVersions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotifyJudgesTest extends TestCase
{
    use BuildsEvents, RefreshDatabase;

    private User $admin;
    private Event $a;
    private Event $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->a = $this->makeEvent(['status' => Event::LIVE]);
        $this->b = $this->makeEvent(['status' => Event::LIVE]);
    }

    public function test_calls_reach_only_the_events_judges(): void
    {
        $mine = $this->addJudges($this->a, 1)->first();
        $theirs = $this->addJudges($this->b, 1)->first();
        $category = $this->category($this->a, 'Sports Wear');

        $this->actingAs($this->admin)
            ->post(route('admin.notify.send', $this->a), ['category' => $category->id])
            ->assertSessionHasNoErrors();

        $this->actingAs($mine)->getJson(route('judge.notifications'))
            ->assertOk()
            ->assertJsonPath('events.0.message', 'Please score the candidates for Sports Wear.')
            ->assertJsonPath('events.0.category_id', $category->id)
            ->assertJsonPath('live', LiveVersions::all($this->a->id));
        $this->actingAs($theirs)->getJson(route('judge.notifications'))
            ->assertJsonCount(0, 'events');
    }

    public function test_judges_and_categories_of_another_event_are_rejected(): void
    {
        $theirs = $this->addJudges($this->b, 1)->first();

        $this->actingAs($this->admin)
            ->post(route('admin.notify.send', $this->a), ['judge_ids' => [$theirs->id]])
            ->assertSessionHasErrors('judge_ids.0');
        $this->actingAs($this->admin)
            ->post(route('admin.notify.send', $this->a), ['category' => $this->category($this->b, 'Sports Wear')->id])
            ->assertSessionHasErrors('category');
    }

    public function test_page_shows_progress_per_category_and_judge(): void
    {
        [$j1, $j2] = $this->addJudges($this->a, 2)->all();
        $sports = $this->category($this->a, 'Sports Wear');
        $f1 = $this->addCandidate($this->a, 'Female', 1);
        $this->addCandidate($this->a, 'Female', 2);
        $this->score($sports, $f1, $j1, 20);

        $this->actingAs($this->admin)->get(route('admin.notify', $this->a))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/NotifyJudges')
                ->where('judges.0.id', $j1->id)
                ->where('categories.0.key', $sports->id)
                ->where('categories.0.round', 'Top 3 Selection')
                ->where("progress.{$sports->id}.total", 2)
                ->where("progress.{$sports->id}.scored.{$j1->id}", 1)
                ->where('sendUrl', route('admin.notify.send', $this->a)));
    }

    public function test_admin_submission_alerts_are_per_event(): void
    {
        $judge = $this->addJudges($this->a, 1)->first();
        $candidate = $this->addCandidate($this->a, 'Female', 1);

        $this->actingAs($judge)->post(route('score.store', $this->category($this->a, 'Sports Wear')), ['scores' => [$candidate->id => 20]]);

        $this->actingAs($this->admin)->getJson(route('admin.events.score_submissions', [$this->a, 'after' => 0]))
            ->assertJsonCount(1, 'events')
            ->assertJsonPath('live', LiveVersions::all($this->a->id));
        $this->actingAs($this->admin)->getJson(route('admin.events.score_submissions', [$this->b, 'after' => 0]))
            ->assertJsonCount(0, 'events');
    }

    public function test_judges_cannot_open_notify(): void
    {
        $judge = $this->addJudges($this->a, 1)->first();

        $this->actingAs($judge)->get(route('admin.notify', $this->a))->assertForbidden();
        $this->actingAs($judge)->getJson(route('admin.events.score_submissions', $this->a))->assertForbidden();
    }
}
