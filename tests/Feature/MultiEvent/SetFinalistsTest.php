<?php

namespace Tests\Feature\MultiEvent;

use App\Models\Event;
use App\Models\Finalist;
use App\Models\Score;
use App\Models\User;
use App\Support\LiveVersions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetFinalistsTest extends TestCase
{
    use BuildsEvents, RefreshDatabase;

    private User $admin;
    private Event $event;
    /** @var array<string, array<int, \App\Models\Candidate>> */
    private array $c = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->event = $this->makeEvent(['status' => Event::LIVE, 'finalists_per_group' => 2], ['Female', 'Male', 'Teen']);
        foreach (['Female', 'Male', 'Teen'] as $group) {
            foreach ([1, 2, 3] as $n) {
                $this->c[$group][$n] = $this->addCandidate($this->event, $group, $n);
            }
        }
    }

    private function pick(array $picks): array
    {
        return collect($picks)->flatMap(fn ($numbers, $group) => collect($numbers)->map(fn ($n) => $this->c[$group][$n]->id))->all();
    }

    private function setTop(array $ids, string $password = 'password', ?Event $event = null)
    {
        return $this->actingAs($this->admin)->post(route('admin.finalists.set', $event ?? $this->event), [
            'candidate_ids' => $ids,
            'password' => $password,
        ]);
    }

    public function test_admin_sets_n_per_group_with_password(): void
    {
        $before = LiveVersions::all($this->event->id);

        $this->setTop($this->pick(['Female' => [1, 2], 'Male' => [3, 1], 'Teen' => [2, 3]]))->assertSessionHasNoErrors();

        $this->assertSame(6, Finalist::count());
        $this->assertTrue($this->event->fresh()->finalistsSet());
        $after = LiveVersions::all($this->event->id);
        $this->assertNotSame($before['finalists'], $after['finalists']);
        $this->assertNotSame($before['scores'], $after['scores']);
    }

    public function test_wrong_or_missing_password_sets_nothing(): void
    {
        $ids = $this->pick(['Female' => [1, 2], 'Male' => [1, 2], 'Teen' => [1, 2]]);

        $this->setTop($ids, 'wrong')->assertSessionHasErrors('password');
        $this->actingAs($this->admin)->post(route('admin.finalists.set', $this->event), ['candidate_ids' => $ids])
            ->assertSessionHasErrors('password');

        $this->assertSame(0, Finalist::count());
    }

    public function test_each_group_needs_exactly_n(): void
    {
        $this->setTop($this->pick(['Female' => [1, 2], 'Male' => [1, 2], 'Teen' => [1]]))
            ->assertSessionHasErrors(['candidate_ids' => 'Select exactly 2 per group.']);

        $this->assertSame(0, Finalist::count());
    }

    public function test_candidates_of_another_event_are_rejected(): void
    {
        $other = $this->makeEvent();
        $stranger = $this->addCandidate($other, 'Female', 1);
        $ids = [...$this->pick(['Female' => [1], 'Male' => [1, 2], 'Teen' => [1, 2]]), $stranger->id];

        $this->setTop($ids)->assertSessionHasErrors('candidate_ids');
        $this->assertSame(0, Finalist::count());
    }

    public function test_single_round_events_have_no_finalists(): void
    {
        $event = $this->makeEvent(['rounds' => 1, 'finalists_per_group' => null], ['Female']);
        $c = $this->addCandidate($event, 'Female', 1);

        $this->setTop([$c->id], 'password', $event)->assertSessionHasErrors('candidate_ids');
        $this->assertSame(0, Finalist::count());
    }

    public function test_resaving_keeps_finals_scores_of_those_who_stay_and_drops_the_rest(): void
    {
        $judge = $this->addJudges($this->event, 1)->first();
        $delivery = $this->category($this->event, 'Delivery');
        $sports = $this->category($this->event, 'Sports Wear');
        $this->setTop($this->pick(['Female' => [1, 2], 'Male' => [1, 2], 'Teen' => [1, 2]]));
        $this->score($delivery, $this->c['Female'][1], $judge, 30);
        $this->score($delivery, $this->c['Female'][2], $judge, 35);
        $this->score($sports, $this->c['Female'][2], $judge, 20);   // round 1 score stays

        $this->setTop($this->pick(['Female' => [1, 3], 'Male' => [1, 2], 'Teen' => [1, 2]]))->assertSessionHasNoErrors();

        $this->assertSame([$this->c['Female'][1]->id], Score::where('category_id', $delivery->id)->pluck('candidate_id')->all());
        $this->assertSame(1, Score::where('category_id', $sports->id)->count());
        $this->assertFalse(Finalist::where('candidate_id', $this->c['Female'][2]->id)->exists());
    }

    public function test_judges_get_403(): void
    {
        $judge = $this->addJudges($this->event, 1)->first();

        $this->actingAs($judge)->post(route('admin.finalists.set', $this->event), [
            'candidate_ids' => $this->pick(['Female' => [1, 2], 'Male' => [1, 2], 'Teen' => [1, 2]]),
            'password' => 'password',
        ])->assertForbidden();
    }
}
