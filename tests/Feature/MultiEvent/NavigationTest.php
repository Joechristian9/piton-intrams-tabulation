<?php

namespace Tests\Feature\MultiEvent;

use App\Models\Event;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use BuildsEvents, RefreshDatabase;

    private function liveEvent(array $attributes = [], ?array $categories = null): Event
    {
        return $this->makeEvent(
            ['status' => Event::LIVE, ...$attributes],
            ['Female', 'Male'],
            $categories ?? [[1, 'Production Number', 10], [1, 'Swim Wear', 25], [2, 'Delivery', 40]],
        );
    }

    public function test_judge_sees_round_one_categories_in_order_before_finalists(): void
    {
        $event = $this->liveEvent();
        $judge = $this->addJudges($event, 1)->first();

        $nav = Navigation::for($judge, null);

        $this->assertSame(['id' => $event->id, 'name' => $event->name, 'status' => Event::LIVE], $nav['event']);
        $this->assertSame(['Top 3 Selection'], array_column($nav['sections'], 'label'));
        $this->assertSame([
            ['label' => 'Production Number', 'href' => route('score.show', $this->category($event, 'Production Number'), false), 'icon' => 'category'],
            ['label' => 'Swim Wear', 'href' => route('score.show', $this->category($event, 'Swim Wear'), false), 'icon' => 'category'],
        ], $nav['sections'][0]['items']);
    }

    public function test_finals_section_appears_once_finalists_are_set(): void
    {
        $event = $this->liveEvent(['finalists_per_group' => 5]);
        $judge = $this->addJudges($event, 1)->first();
        $this->setFinalists($event, $this->addCandidate($event, 'Female', 1));

        $nav = Navigation::for($judge->fresh(), null);

        $this->assertSame(['Top 5 Selection', 'Top 5 Finalist'], array_column($nav['sections'], 'label'));
        $this->assertSame(['Delivery'], array_column($nav['sections'][1]['items'], 'label'));
    }

    public function test_single_round_event_lists_categories(): void
    {
        $event = $this->liveEvent(['rounds' => 1, 'finalists_per_group' => null], [[1, 'Talent', 50], [1, 'Q&A', 50]]);
        $judge = $this->addJudges($event, 1)->first();

        $nav = Navigation::for($judge, null);

        $this->assertSame(['Categories'], array_column($nav['sections'], 'label'));
        $this->assertSame(['Talent', 'Q&A'], array_column($nav['sections'][0]['items'], 'label'));
    }

    public function test_event_that_is_not_live_has_no_scoring_links(): void
    {
        $event = $this->makeEvent(['status' => Event::SETUP]);
        $judge = $this->addJudges($event, 1)->first();

        $nav = Navigation::for($judge, null);

        $this->assertSame(Event::SETUP, $nav['event']['status']);
        $this->assertSame([], $nav['sections']);
    }

    public function test_judge_never_sees_another_events_categories(): void
    {
        $this->liveEvent();
        $mine = $this->liveEvent([], [[1, 'Mine Only', 10]]);
        $judge = $this->addJudges($mine, 1)->first();

        $labels = collect(Navigation::for($judge, null)['sections'])->flatMap(fn ($s) => array_column($s['items'], 'label'))->all();

        $this->assertSame(['Mine Only'], $labels);
    }

    public function test_pages_share_the_nav(): void
    {
        $this->withoutVite();
        $event = $this->liveEvent();
        $judge = $this->addJudges($event, 1)->first();

        $this->actingAs($judge)->get('/profile')
            ->assertInertia(fn (Assert $page) => $page->where('nav', Navigation::for($judge, null)));
    }

    public function test_admin_without_any_event_still_gets_the_events_link(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertSame(['event' => null, 'sections' => [[
            'label' => 'Management',
            'items' => [['label' => 'Events', 'href' => route('admin.events.index', [], false), 'icon' => 'events']],
        ]]], Navigation::for($admin, null));
    }

    public function test_guests_and_judges_without_an_event_get_an_empty_nav(): void
    {
        $this->assertSame(['event' => null, 'sections' => []], Navigation::for(null, null));
        $this->assertSame(['event' => null, 'sections' => []], Navigation::for(User::factory()->create(['role' => 'judge']), null));
    }
}
