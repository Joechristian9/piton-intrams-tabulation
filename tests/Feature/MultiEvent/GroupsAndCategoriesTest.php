<?php

namespace Tests\Feature\MultiEvent;

use App\Models\Category;
use App\Models\Event;
use App\Models\EventGroup;
use App\Models\User;
use App\Support\LiveVersions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupsAndCategoriesTest extends TestCase
{
    use BuildsEvents, RefreshDatabase;

    private User $admin;
    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->event = $this->makeEvent([], ['Female'], [[1, 'Sports Wear', 25], [2, 'Delivery', 40]]);
    }

    private function as(): static
    {
        return $this->actingAs($this->admin);
    }

    private function scoreIn(string $categoryName): void
    {
        $judge = $this->addJudges($this->event, 1)->first();
        $this->score($this->category($this->event, $categoryName), $this->addCandidate($this->event, 'Female', 99), $judge, 10);
    }

    public function test_admin_adds_renames_and_reorders_groups(): void
    {
        $before = LiveVersions::all($this->event->id)['event'];

        $this->as()->post(route('admin.groups.store', $this->event), ['name' => 'Male'])->assertSessionHasNoErrors();
        $male = $this->group($this->event, 'Male');
        $this->assertSame(2, $male->position);
        $this->assertNotSame($before, LiveVersions::all($this->event->id)['event']);

        $this->as()->put(route('admin.groups.update', $male), ['name' => 'Gentlemen', 'position' => 1])->assertSessionHasNoErrors();
        $this->assertSame(['Gentlemen', 1], [$male->fresh()->name, $male->fresh()->position]);
    }

    /** Review Focus 3: duplicate group names are a field error, not a crash. */
    public function test_duplicate_group_name_is_a_field_error(): void
    {
        $this->as()->post(route('admin.groups.store', $this->event), ['name' => 'Female'])
            ->assertSessionHasErrors('name');

        // The same name in another event is fine.
        $other = $this->makeEvent([], []);
        $this->as()->post(route('admin.groups.store', $other), ['name' => 'Female'])->assertSessionHasNoErrors();
    }

    public function test_group_with_candidates_cannot_be_deleted(): void
    {
        $female = $this->group($this->event, 'Female');
        $this->addCandidate($this->event, 'Female', 1);

        $this->as()->delete(route('admin.groups.destroy', $female))
            ->assertSessionHasErrors(['group' => 'Move or delete its candidates first.']);

        $empty = EventGroup::create(['event_id' => $this->event->id, 'name' => 'Empty', 'position' => 2]);
        $this->as()->delete(route('admin.groups.destroy', $empty))->assertSessionHasNoErrors();
        $this->assertModelMissing($empty);
    }

    public function test_admin_adds_and_edits_categories(): void
    {
        $this->as()->post(route('admin.categories.store', $this->event), ['name' => 'Swim Wear', 'round' => 1, 'max_score' => 25])
            ->assertSessionHasNoErrors();
        $swim = $this->category($this->event, 'Swim Wear');
        $this->assertSame([1, 25.0, 2], [$swim->round, $swim->max_score, $swim->position]);

        $this->as()->put(route('admin.categories.update', $swim), ['name' => 'Swimsuit', 'round' => 1, 'max_score' => 30, 'position' => 1])
            ->assertSessionHasNoErrors();
        $this->assertSame(['Swimsuit', 30.0, 1], [$swim->fresh()->name, $swim->fresh()->max_score, $swim->fresh()->position]);
    }

    public function test_category_values_are_validated(): void
    {
        $this->as()->post(route('admin.categories.store', $this->event), ['name' => '', 'round' => 1, 'max_score' => 25])->assertSessionHasErrors('name');
        $this->as()->post(route('admin.categories.store', $this->event), ['name' => 'X', 'round' => 1, 'max_score' => 0])->assertSessionHasErrors('max_score');
        $this->as()->post(route('admin.categories.store', $this->event), ['name' => 'X', 'round' => 1, 'max_score' => 1000])->assertSessionHasErrors('max_score');

        $single = $this->makeEvent(['rounds' => 1, 'finalists_per_group' => null], [], []);
        $this->as()->post(route('admin.categories.store', $single), ['name' => 'X', 'round' => 2, 'max_score' => 10])->assertSessionHasErrors('round');
    }

    public function test_scored_category_keeps_its_max_and_round_and_cannot_be_deleted(): void
    {
        $this->scoreIn('Sports Wear');
        $sports = $this->category($this->event, 'Sports Wear');

        $this->as()->put(route('admin.categories.update', $sports), ['name' => 'Sports Wear', 'round' => 1, 'max_score' => 30, 'position' => 1])
            ->assertSessionHasErrors(['max_score' => "This can't change after scoring has started."]);
        $this->as()->put(route('admin.categories.update', $sports), ['name' => 'Sports Wear', 'round' => 2, 'max_score' => 25, 'position' => 1])
            ->assertSessionHasErrors('round');
        $this->as()->delete(route('admin.categories.destroy', $sports))->assertSessionHasErrors('category');

        // Renaming is fine.
        $this->as()->put(route('admin.categories.update', $sports), ['name' => 'Sportswear', 'round' => 1, 'max_score' => 25, 'position' => 1])
            ->assertSessionHasNoErrors();
        $this->assertModelExists($sports);
    }

    public function test_no_new_category_in_a_round_that_has_scores(): void
    {
        $this->scoreIn('Sports Wear');

        $this->as()->post(route('admin.categories.store', $this->event), ['name' => 'Late', 'round' => 1, 'max_score' => 10])
            ->assertSessionHasErrors(['round' => "Round 1 already has scores, so a new category would change everyone's totals."]);
        // Round 2 has no scores yet: allowed.
        $this->as()->post(route('admin.categories.store', $this->event), ['name' => 'Q&A', 'round' => 2, 'max_score' => 10])
            ->assertSessionHasNoErrors();
    }

    public function test_moving_an_empty_category_into_a_scored_round_is_blocked(): void
    {
        $this->scoreIn('Sports Wear');
        $delivery = $this->category($this->event, 'Delivery');   // round 2, no scores

        $this->as()->put(route('admin.categories.update', $delivery), ['name' => 'Delivery', 'round' => 1, 'max_score' => 40, 'position' => 2])
            ->assertSessionHasErrors(['round' => "Round 1 already has scores, so a new category would change everyone's totals."]);

        $this->assertSame(2, $delivery->fresh()->round);
    }

    public function test_unscored_category_can_be_deleted(): void
    {
        $delivery = $this->category($this->event, 'Delivery');

        $this->as()->delete(route('admin.categories.destroy', $delivery))->assertSessionHasNoErrors();
        $this->assertModelMissing($delivery);
    }

    public function test_judges_cannot_edit_groups_or_categories(): void
    {
        $judge = $this->addJudges($this->event, 1)->first();

        $this->actingAs($judge)->post(route('admin.groups.store', $this->event), ['name' => 'X'])->assertForbidden();
        $this->actingAs($judge)->delete(route('admin.categories.destroy', $this->category($this->event, 'Delivery')))->assertForbidden();
        $this->assertSame(2, Category::count());
    }
}
