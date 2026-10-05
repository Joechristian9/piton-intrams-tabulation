<?php

namespace Tests\Feature\MultiEvent;

use App\Models\Event;
use App\Models\Score;
use App\Models\User;
use App\Support\LiveVersions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class JudgeScoringTest extends TestCase
{
    use BuildsEvents, RefreshDatabase;

    private Event $event;
    private User $judge;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();   // asserts server data, not built assets
        $this->event = $this->makeEvent(['status' => Event::LIVE]);
        $this->judge = $this->addJudges($this->event, 1)->first();
    }

    private function submit(string $categoryName, array $scores, ?User $as = null)
    {
        $category = $this->category($this->event, $categoryName);

        return $this->actingAs($as ?? $this->judge)->post(route('score.store', $category), ['scores' => $scores]);
    }

    public function test_judge_saves_scores_for_their_live_event(): void
    {
        $f = $this->addCandidate($this->event, 'Female', 1);
        $other = $this->addJudges($this->event, 1)->first();
        $before = LiveVersions::all($this->event->id)['scores'];

        $this->actingAs($this->judge)
            ->post(route('score.store', $this->category($this->event, 'Sports Wear')), [
                'judge_id' => $other->id,
                'scores' => [$f->id => 22.5],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(22.5, Score::sole()->score);
        $this->assertSame($this->judge->id, Score::sole()->judge_id);
        $this->assertNotSame($before, LiveVersions::all($this->event->id)['scores']);
    }

    public function test_resubmitting_updates_the_same_score_row(): void
    {
        $f = $this->addCandidate($this->event, 'Female', 1);

        $this->submit('Sports Wear', [$f->id => 20])->assertSessionHasNoErrors();
        $this->submit('Sports Wear', [$f->id => 21])->assertSessionHasNoErrors();

        $this->assertSame(21.0, Score::sole()->score);
    }

    /** Review Focus 1: a stale page submits after the admin closed the event. */
    public function test_event_not_live_is_rejected(): void
    {
        $f = $this->addCandidate($this->event, 'Female', 1);
        $this->event->update(['status' => Event::CLOSED]);

        $this->submit('Sports Wear', [$f->id => 20])
            ->assertSessionHasErrors(['scores' => "This event isn't running right now."]);
        $this->assertSame(0, Score::count());
    }

    public function test_category_of_another_event_is_404(): void
    {
        $other = $this->makeEvent(['status' => Event::LIVE]);
        $f = $this->addCandidate($other, 'Female', 1);

        $this->actingAs($this->judge)
            ->post(route('score.store', $this->category($other, 'Sports Wear')), ['scores' => [$f->id => 20]])
            ->assertNotFound();
        $this->actingAs($this->judge)->get(route('score.show', $this->category($other, 'Sports Wear')))->assertNotFound();
        $this->assertSame(0, Score::count());
    }

    public function test_candidate_of_another_event_is_rejected(): void
    {
        $other = $this->makeEvent();
        $stranger = $this->addCandidate($other, 'Female', 1);

        $this->submit('Sports Wear', [$stranger->id => 20])->assertSessionHasErrors('scores');
        $this->assertSame(0, Score::count());
    }

    public function test_non_finalist_in_round_two_is_rejected(): void
    {
        $finalist = $this->addCandidate($this->event, 'Female', 1);
        $out = $this->addCandidate($this->event, 'Female', 2);
        $this->setFinalists($this->event, $finalist);

        $this->submit('Delivery', [$finalist->id => 30, $out->id => 30])->assertSessionHasErrors('scores');
        $this->assertSame(0, Score::count());

        $this->submit('Delivery', [$finalist->id => 30])->assertSessionHasNoErrors();
        $this->assertSame(1, Score::count());
    }

    public function test_score_above_max_or_negative_is_rejected(): void
    {
        $f = $this->addCandidate($this->event, 'Female', 1);

        $this->submit('Sports Wear', [$f->id => 25.5])->assertSessionHasErrors("scores.{$f->id}");
        $this->submit('Sports Wear', [$f->id => -1])->assertSessionHasErrors("scores.{$f->id}");
        $this->submit('Sports Wear', [$f->id => 'abc'])->assertSessionHasErrors("scores.{$f->id}");
        $this->assertSame(0, Score::count());

        $this->submit('Sports Wear', [$f->id => 25])->assertSessionHasNoErrors();
    }

    public function test_round_one_locked_after_finalists(): void
    {
        $f = $this->addCandidate($this->event, 'Female', 1);
        $this->setFinalists($this->event, $f);

        $this->submit('Sports Wear', [$f->id => 20])->assertSessionHasErrors([
            'scores' => 'The Top 3 finalists have been set, so Top 3 Selection scores can no longer be changed.',
        ]);
        $this->assertSame(0, Score::count());
    }

    public function test_admins_get_403(): void
    {
        $f = $this->addCandidate($this->event, 'Female', 1);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->submit('Sports Wear', [$f->id => 20], $admin)->assertForbidden();
        $this->actingAs($admin)->get(route('score.show', $this->category($this->event, 'Sports Wear')))->assertForbidden();
    }

    public function test_show_lists_groups_in_order_with_existing_scores(): void
    {
        $f2 = $this->addCandidate($this->event, 'Female', 2);
        $f1 = $this->addCandidate($this->event, 'Female', 1);
        $m1 = $this->addCandidate($this->event, 'Male', 1);
        $cat = $this->category($this->event, 'Sports Wear');
        $this->score($cat, $f2, $this->judge, 19.5);

        $this->actingAs($this->judge)->get(route('score.show', $cat))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Judge/Score')
                ->where('event.id', $this->event->id)
                ->where('category', ['id' => $cat->id, 'name' => 'Sports Wear', 'max_score' => 25, 'round' => 1])
                ->where('roundClosed', false)
                ->where('groups.0.name', 'Female')
                ->where('groups.0.candidates.0.id', $f1->id)
                ->where('groups.0.candidates.0.existing_score', null)
                ->where('groups.0.candidates.1.id', $f2->id)
                ->where('groups.0.candidates.1.existing_score', 19.5)
                ->where('groups.1.candidates.0.id', $m1->id));
    }

    public function test_show_round_two_lists_only_finalists(): void
    {
        $f1 = $this->addCandidate($this->event, 'Female', 1);
        $this->addCandidate($this->event, 'Female', 2);
        $this->setFinalists($this->event, $f1);

        $this->actingAs($this->judge)->get(route('score.show', $this->category($this->event, 'Delivery')))
            ->assertInertia(fn (Assert $page) => $page
                ->has('groups.0.candidates', 1)
                ->where('groups.0.candidates.0.id', $f1->id)
                ->has('groups.1.candidates', 0));
    }

    public function test_show_redirects_to_the_landing_page_when_the_event_is_not_live(): void
    {
        $this->event->update(['status' => Event::SETUP]);

        $this->actingAs($this->judge)->get(route('score.show', $this->category($this->event, 'Sports Wear')))
            ->assertRedirect(route('dashboard'));
    }
}
