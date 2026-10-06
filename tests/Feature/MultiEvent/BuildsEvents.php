<?php

namespace Tests\Feature\MultiEvent;

use App\Models\Candidate;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventGroup;
use App\Models\Finalist;
use App\Models\Score;
use App\Models\User;
use Illuminate\Support\Collection;

/** Small builders for multi-event test data. */
trait BuildsEvents
{
    /**
     * @param  array<int, string>  $groups  names, in position order
     * @param  array<int, array{0:int,1:string,2:float}>  $categories  [round, name, max] in position order per round
     */
    protected function makeEvent(array $attributes = [], array $groups = ['Female', 'Male'], array $categories = [[1, 'Sports Wear', 25], [2, 'Delivery', 40]]): Event
    {
        $event = Event::factory()->create($attributes);

        foreach ($groups as $i => $name) {
            EventGroup::create(['event_id' => $event->id, 'name' => $name, 'position' => $i + 1]);
        }

        $positions = [];
        foreach ($categories as [$round, $name, $max]) {
            $positions[$round] = ($positions[$round] ?? 0) + 1;
            Category::create([
                'event_id' => $event->id, 'round' => $round, 'name' => $name,
                'max_score' => $max, 'position' => $positions[$round],
            ]);
        }

        return $event->fresh();
    }

    protected function group(Event $event, string $name): EventGroup
    {
        return $event->groups()->where('name', $name)->firstOrFail();
    }

    protected function category(Event $event, string $name): Category
    {
        return $event->categories()->where('name', $name)->firstOrFail();
    }

    protected function addCandidate(Event $event, string $groupName, int $number, array $attributes = []): Candidate
    {
        return Candidate::create([
            'event_id' => $event->id,
            'group_id' => $this->group($event, $groupName)->id,
            'candidate_number' => $number,
            'first_name' => 'Candidate',
            'last_name' => (string) $number,
            'course' => 'BSIT',
            'profile_img' => "candidates/test/{$number}.jpg",
            ...$attributes,
        ]);
    }

    /** @return Collection<int, User> */
    protected function addJudges(Event $event, int $count): Collection
    {
        return User::factory()->count($count)->create(['role' => 'judge', 'event_id' => $event->id]);
    }

    protected function score(Category $category, Candidate $candidate, User $judge, float $score): Score
    {
        return Score::create([
            'category_id' => $category->id, 'candidate_id' => $candidate->id,
            'judge_id' => $judge->id, 'score' => $score,
        ]);
    }

    /** Finalists in the given order (that order is kept in results). */
    protected function setFinalists(Event $event, Candidate ...$candidates): void
    {
        foreach ($candidates as $candidate) {
            Finalist::create(['event_id' => $event->id, 'candidate_id' => $candidate->id]);
        }
        $event->forceFill(['finalists_set_at' => now()])->save();
    }
}
