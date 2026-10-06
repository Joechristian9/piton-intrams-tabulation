<?php

namespace Tests\Feature\MultiEvent;

use App\Models\Category;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Admin setup pages must not run one query per candidate, group, category or
 * event: the query count stays the same as an event grows.
 */
class AdminQueryCountTest extends TestCase
{
    use BuildsEvents, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function queriesFor(string $url): int
    {
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });
        $this->actingAs($this->admin)->get($url)->assertOk();

        return $count;
    }

    private function scoredEvent(int $candidatesPerGroup, int $extraCategories): Event
    {
        $event = $this->makeEvent(['status' => Event::LIVE]);
        $judge = $this->addJudges($event, 2)->first();
        foreach (range(1, $extraCategories) as $i) {
            Category::create(['event_id' => $event->id, 'round' => 1, 'name' => "Extra {$i}", 'max_score' => 10, 'position' => $i + 1]);
        }
        $sports = $this->category($event, 'Sports Wear');
        foreach (['Female', 'Male'] as $group) {
            foreach (range(1, $candidatesPerGroup) as $n) {
                $this->score($sports, $this->addCandidate($event, $group, $n), $judge, 20);
            }
        }

        return $event->fresh();
    }

    public function test_event_setup_page_query_count_does_not_grow_with_the_event(): void
    {
        $small = $this->scoredEvent(1, 1);
        $large = $this->scoredEvent(10, 6);

        $this->assertSame(
            $this->queriesFor(route('admin.events.edit', $small)),
            $this->queriesFor(route('admin.events.edit', $large)),
        );
    }

    public function test_events_list_query_count_does_not_grow_with_the_number_of_events(): void
    {
        $this->scoredEvent(2, 1);
        $few = $this->queriesFor(route('admin.events.index'));

        foreach (range(1, 4) as $_) {
            $this->scoredEvent(2, 1);
        }

        $this->assertSame($few, $this->queriesFor(route('admin.events.index')));
    }

    public function test_setup_page_still_reports_scores_and_counts(): void
    {
        $event = $this->scoredEvent(2, 1);
        $unscored = $this->addCandidate($event, 'Female', 99);

        $this->actingAs($this->admin)->get(route('admin.events.edit', $event))
            ->assertInertia(fn ($page) => $page
                ->where('groups.0.candidates', 3)
                ->where('categories', fn ($cats) => collect($cats)->firstWhere('name', 'Sports Wear')['hasScores'] === true
                    && collect($cats)->firstWhere('name', 'Extra 1')['hasScores'] === false)
                ->where('candidates', fn ($cands) => collect($cands)->firstWhere('id', $unscored->id)['hasScores'] === false
                    && collect($cands)->where('hasScores', true)->count() === 4)
                ->where('locks.hasScores', true)
                ->where('locks.roundHasScores.1', true)
                ->where('locks.roundHasScores.2', false));

        $this->actingAs($this->admin)->get(route('admin.events.index'))
            ->assertInertia(fn ($page) => $page
                ->where('events.0.candidates', 5)
                ->where('events.0.judges', 2)
                ->where('events.0.hasScores', true));
    }
}
