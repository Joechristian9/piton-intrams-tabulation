<?php

namespace Tests\Feature\MultiEvent;

use App\Results\EventResults;
use App\Results\Tabulator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventResultsTest extends TestCase
{
    use BuildsEvents, RefreshDatabase;

    private function ids(array $rows): array
    {
        return array_map(fn ($r) => $r['candidate']['id'], $rows);
    }

    public function test_category_results_are_split_by_group_and_use_the_events_judges_only(): void
    {
        $event = $this->makeEvent();
        $other = $this->makeEvent();
        [$j1, $j2] = $this->addJudges($event, 2)->all();
        $outsider = $this->addJudges($other, 1)->first();
        $cat = $this->category($event, 'Sports Wear');

        $f1 = $this->addCandidate($event, 'Female', 2);
        $f2 = $this->addCandidate($event, 'Female', 1);
        $m1 = $this->addCandidate($event, 'Male', 1);

        $this->score($cat, $f1, $j1, 20);
        $this->score($cat, $f1, $j2, 10);
        $this->score($cat, $f2, $j1, 25);
        $this->score($cat, $f2, $outsider, 25);   // not this event's judge: ignored
        $this->score($cat, $m1, $j2, 18);

        $result = (new EventResults($event))->category($cat);

        $this->assertSame([$j1->id, $j2->id], array_column($result['judges'], 'id'));
        $this->assertSame(['Female', 'Male'], array_column($result['groups'], 'name'));
        $female = $result['groups'][0]['rows'];
        $this->assertSame([$f1->id, $f2->id], $this->ids($female));
        $this->assertSame([15.0, 12.5], array_column($female, 'total'));
        $this->assertSame([$j1->id => 25.0, $j2->id => 0.0], $female[1]['scores']);
        $this->assertSame([9.0], array_column($result['groups'][1]['rows'], 'total'));
        $this->assertSame(
            ['id', 'candidate_number', 'first_name', 'last_name', 'course', 'profile_img'],
            array_keys($female[0]['candidate'])
        );
        $this->assertSame(['id' => $cat->id, 'name' => 'Sports Wear', 'max_score' => 25.0, 'round' => 1], $result['category']);
    }

    public function test_round_one_lists_candidates_by_number_when_tied(): void
    {
        $event = $this->makeEvent();
        $this->addJudges($event, 1);
        $second = $this->addCandidate($event, 'Female', 2);
        $first = $this->addCandidate($event, 'Female', 1);

        $rows = (new EventResults($event))->round(1)['groups'][0]['rows'];

        $this->assertSame([$first->id, $second->id], $this->ids($rows));
    }

    public function test_round_two_lists_only_finalists_in_the_order_they_were_set(): void
    {
        $event = $this->makeEvent();
        $this->addJudges($event, 1);
        $f1 = $this->addCandidate($event, 'Female', 1);
        $this->addCandidate($event, 'Female', 2);
        $f3 = $this->addCandidate($event, 'Female', 3);
        $this->setFinalists($event, $f3, $f1);

        $results = new EventResults($event->fresh());

        $this->assertSame([$f3->id, $f1->id], $this->ids($results->round(2)['groups'][0]['rows']));
        $this->assertSame([$f3->id, $f1->id], $this->ids($results->category($this->category($event, 'Delivery'))['groups'][0]['rows']));
        $this->assertSame([], $results->round(2)['groups'][1]['rows']);
    }

    public function test_round_results_list_the_rounds_categories(): void
    {
        $event = $this->makeEvent([], ['Female'], [[1, 'A', 10], [1, 'B', 25], [2, 'C', 50]]);
        $this->addJudges($event, 2);
        $f = $this->addCandidate($event, 'Female', 1);
        [$j1, $j2] = $event->judges->all();
        $this->score($this->category($event, 'A'), $f, $j1, 9);
        $this->score($this->category($event, 'A'), $f, $j2, 8);
        $this->score($this->category($event, 'B'), $f, $j1, 20);

        $round = (new EventResults($event))->round(1);

        $this->assertSame(['A', 'B'], array_column($round['categories'], 'name'));
        $row = $round['groups'][0]['rows'][0];
        $this->assertSame([$this->category($event, 'A')->id => 8.5, $this->category($event, 'B')->id => 10.0], $row['scores']);
        $this->assertSame(18.5, $row['total']);
    }

    public function test_standings_for_a_single_round_event(): void
    {
        $event = $this->makeEvent(['rounds' => 1, 'finalists_per_group' => null], ['Female'], [[1, 'A', 100]]);
        $judge = $this->addJudges($event, 1)->first();
        $f = $this->addCandidate($event, 'Female', 1);
        $this->score($this->category($event, 'A'), $f, $judge, 77.5);

        $standings = (new EventResults($event))->standings();

        $this->assertSame('single', $standings['mode']);
        $this->assertNull($standings['weights']);
        $this->assertSame([77.5], array_column($standings['groups'][0]['rows'], 'total'));
    }

    public function test_standings_for_finals_from_zero_are_the_finals_round(): void
    {
        $event = $this->makeEvent([], ['Female'], [[1, 'A', 100], [2, 'F', 100]]);
        $judge = $this->addJudges($event, 1)->first();
        $f = $this->addCandidate($event, 'Female', 1);
        $this->score($this->category($event, 'A'), $f, $judge, 90);
        $this->score($this->category($event, 'F'), $f, $judge, 60);
        $this->setFinalists($event, $f);

        $standings = (new EventResults($event->fresh()))->standings();

        $this->assertSame('finals', $standings['mode']);
        $this->assertSame([60.0], array_column($standings['groups'][0]['rows'], 'total'));
    }

    public function test_standings_with_carry_over_are_weighted(): void
    {
        $event = $this->makeEvent(['finals_from_zero' => false, 'round1_weight' => 40, 'finals_weight' => 60], ['Female'], [[1, 'A', 100], [2, 'F', 100]]);
        $judge = $this->addJudges($event, 1)->first();
        $f = $this->addCandidate($event, 'Female', 1);
        $this->score($this->category($event, 'A'), $f, $judge, 85.4);
        $this->score($this->category($event, 'F'), $f, $judge, 92.1);
        $this->setFinalists($event, $f);

        $results = new EventResults($event->fresh());
        $standings = $results->standings();

        $this->assertSame('weighted', $standings['mode']);
        $this->assertSame([40, 60], $standings['weights']);
        $expected = Tabulator::weighted($results->round(1)['groups'][0]['rows'], $results->round(2)['groups'][0]['rows'], 40, 60);
        $this->assertSame($expected, $standings['groups'][0]['rows']);
        $this->assertSame(89.42, $standings['groups'][0]['rows'][0]['total']);
    }
}
