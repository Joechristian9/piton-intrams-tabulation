<?php

namespace Tests\Feature\MultiEvent;

use App\Support\LiveVersions;
use App\Support\ScoreSubmissionFeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LiveVersionsTest extends TestCase
{
    use BuildsEvents, RefreshDatabase;

    public function test_bumping_one_event_does_not_change_another(): void
    {
        LiveVersions::bump(1, LiveVersions::FINALISTS);

        $this->assertNotNull(LiveVersions::all(1)['finalists']);
        $this->assertNull(LiveVersions::all(2)['finalists']);
        $this->assertSame(['event', 'finalists', 'scores'], array_keys(LiveVersions::all(2)));
    }

    public function test_a_judges_pages_carry_their_own_events_stamps(): void
    {
        $event = $this->makeEvent();
        $judge = $this->addJudges($event, 1)->first();
        LiveVersions::bump($event->id, LiveVersions::EVENT);

        $this->actingAs($judge)->get(route('dashboard'))   // a setup event: the waiting page
            ->assertInertia(fn (Assert $page) => $page->where('live', LiveVersions::all($event->id)));
    }

    public function test_score_submissions_are_kept_per_event(): void
    {
        $a = $this->makeEvent();
        $b = $this->makeEvent();
        $judge = $this->addJudges($a, 1)->first();
        $candidate = $this->addCandidate($a, 'Female', 1);

        ScoreSubmissionFeed::push($this->category($a, 'Sports Wear'), $judge, [$candidate->id]);

        $this->assertSame(
            ["{$judge->name} submitted Female Sports Wear scores"],
            array_column(ScoreSubmissionFeed::since($a->id, 0)['events'], 'message')
        );
        $this->assertSame([], ScoreSubmissionFeed::since($b->id, 0)['events']);
        $this->assertSame([], ScoreSubmissionFeed::since($a->id, null)['events']);   // first poll: no backlog
    }
}
