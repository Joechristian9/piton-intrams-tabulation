<?php

namespace Tests\Feature\MultiEvent;

use App\Models\Candidate;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventGroup;
use App\Models\Score;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_has_ordered_groups_and_categories(): void
    {
        $event = Event::factory()->create();
        EventGroup::factory()->for($event)->create(['name' => 'Male', 'position' => 2]);
        EventGroup::factory()->for($event)->create(['name' => 'Female', 'position' => 1]);
        Category::factory()->for($event)->create(['round' => 2, 'position' => 1]);
        Category::factory()->for($event)->create(['round' => 1, 'position' => 2]);
        Category::factory()->for($event)->create(['round' => 1, 'position' => 1]);

        $event->refresh();

        $this->assertSame([1, 2], $event->groups->pluck('position')->all());
        $this->assertSame(
            [[1, 1], [1, 2], [2, 1]],
            $event->categories->map(fn ($c) => [$c->round, $c->position])->all()
        );
    }

    public function test_a_judge_score_is_unique_per_category_candidate_and_judge(): void
    {
        $event = Event::factory()->create();
        $group = EventGroup::factory()->for($event)->create();
        $category = Category::factory()->for($event)->create();
        $candidate = Candidate::factory()->create(['event_id' => $event->id, 'group_id' => $group->id, 'candidate_number' => 1]);
        $judge = User::factory()->create(['role' => 'judge', 'event_id' => $event->id]);

        $row = ['category_id' => $category->id, 'candidate_id' => $candidate->id, 'judge_id' => $judge->id, 'score' => 20];
        Score::create($row);

        $this->expectException(UniqueConstraintViolationException::class);
        Score::create($row);
    }

    /** Today's pageant numbers each group from 1 (Female #1–12, Male #1–10). */
    public function test_candidate_numbers_are_unique_per_group_but_reusable_across_groups_and_events(): void
    {
        [$a, $b] = Event::factory()->count(2)->create();
        $female = EventGroup::factory()->for($a)->create(['name' => 'Female']);
        $male = EventGroup::factory()->for($a)->create(['name' => 'Male']);
        $groupB = EventGroup::factory()->for($b)->create();

        Candidate::factory()->create(['event_id' => $a->id, 'group_id' => $female->id, 'candidate_number' => 1]);
        Candidate::factory()->create(['event_id' => $a->id, 'group_id' => $male->id, 'candidate_number' => 1]);
        Candidate::factory()->create(['event_id' => $b->id, 'group_id' => $groupB->id, 'candidate_number' => 1]);
        $this->assertSame(3, Candidate::count());

        $this->expectException(UniqueConstraintViolationException::class);
        Candidate::factory()->create(['event_id' => $a->id, 'group_id' => $female->id, 'candidate_number' => 1]);
    }

    public function test_new_event_candidates_need_no_gender_or_course(): void
    {
        $event = Event::factory()->create();
        $group = EventGroup::factory()->for($event)->create();

        $candidate = Candidate::create([
            'event_id' => $event->id, 'group_id' => $group->id, 'candidate_number' => 7,
            'first_name' => 'A', 'last_name' => 'B', 'profile_img' => 'x.jpg',
        ]);

        $this->assertNull($candidate->fresh()->gender);
        $this->assertSame($group->id, $candidate->group->id);
        $this->assertSame($event->id, $candidate->event->id);
    }
}
