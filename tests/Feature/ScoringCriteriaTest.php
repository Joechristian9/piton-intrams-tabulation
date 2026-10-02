<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\TopFiveCandidates;
use App\Models\TopFiveScore;
use App\Models\TopFiveSelectionScore;
use App\Models\User;
use App\Support\Criteria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoringCriteriaTest extends TestCase
{
    use RefreshDatabase;

    private function candidate(int $number, string $gender = 'female'): Candidate
    {
        return Candidate::create([
            'candidate_number' => $number,
            'profile_img' => 'x.jpg',
            'first_name' => 'Cand',
            'last_name' => (string) $number,
            'course' => 'BSIT',
            'gender' => $gender,
        ]);
    }

    public function test_each_round_adds_up_to_100(): void
    {
        $this->assertSame(100, array_sum(Criteria::SELECTION));
        $this->assertSame(100, array_sum(Criteria::FINALS));
    }

    public function test_score_above_the_category_maximum_is_rejected(): void
    {
        $judge = User::factory()->create(['role' => 'judge']);
        $candidate = $this->candidate(1);

        // Sports Wear (casual_wear) is out of 25.
        $this->actingAs($judge)
            ->post('/casual_wear/scores', ['judge_id' => $judge->id, 'scores' => [$candidate->id => 26]])
            ->assertSessionHasErrors('scores.' . $candidate->id);

        $this->assertSame(0, TopFiveSelectionScore::count());
    }

    public function test_maximum_score_and_decimals_are_accepted(): void
    {
        $judge = User::factory()->create(['role' => 'judge']);
        $a = $this->candidate(1);
        $b = $this->candidate(2);

        $this->actingAs($judge)
            ->post('/casual_wear/scores', ['judge_id' => $judge->id, 'scores' => [$a->id => 25, $b->id => 17.5]])
            ->assertSessionHasNoErrors();

        $this->assertEquals(25, TopFiveSelectionScore::where('candidate_id', $a->id)->value('casual_wear'));
        $this->assertEquals(17.5, TopFiveSelectionScore::where('candidate_id', $b->id)->value('casual_wear'));
    }

    public function test_negative_and_non_numeric_scores_are_rejected(): void
    {
        $judge = User::factory()->create(['role' => 'judge']);
        $candidate = $this->candidate(1);

        $this->actingAs($judge)
            ->post('/production_number/scores', ['judge_id' => $judge->id, 'scores' => [$candidate->id => -1]])
            ->assertSessionHasErrors('scores.' . $candidate->id);

        $this->actingAs($judge)
            ->post('/production_number/scores', ['judge_id' => $judge->id, 'scores' => [$candidate->id => 'abc']])
            ->assertSessionHasErrors('scores.' . $candidate->id);
    }

    public function test_finals_limits_are_enforced_per_category(): void
    {
        $judge = User::factory()->create(['role' => 'judge']);
        $finalist = TopFiveCandidates::create(['candidate_id' => $this->candidate(1)->id]);
        $candidateId = $finalist->candidate_id;

        // Delivery is out of 40; Overall Appeal is out of 10.
        $this->actingAs($judge)
            ->post('/delivery/store', ['judge_id' => $judge->id, 'scores' => [$candidateId => 41]])
            ->assertSessionHasErrors('scores.' . $candidateId);

        $this->actingAs($judge)
            ->post('/overall_appeal/store', ['judge_id' => $judge->id, 'scores' => [$candidateId => 11]])
            ->assertSessionHasErrors('scores.' . $candidateId);

        $this->actingAs($judge)
            ->post('/delivery/store', ['judge_id' => $judge->id, 'scores' => [$candidateId => 40]])
            ->assertSessionHasNoErrors();

        $this->assertEquals(40, TopFiveScore::where('top_five_id', $finalist->id)->value('delivery'));
    }

    public function test_selection_totals_are_out_of_100_using_the_judges_average(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $judges = User::factory()->count(5)->create(['role' => 'judge']);
        $candidate = $this->candidate(1);

        // Every judge gives the maximum in every category = a perfect 100.
        foreach ($judges as $judge) {
            TopFiveSelectionScore::create(array_merge(
                ['candidate_id' => $candidate->id, 'judge_id' => $judge->id],
                Criteria::SELECTION
            ));
        }

        $page = $this->actingAs($admin)->get('/admin/top_five_selection_result')->assertOk();
        $row = collect($page->viewData('page')['props']['femaleCandidates'])->first();

        $this->assertEquals(100, $row['total']);
        $this->assertEquals(25, $row['scores']['casual_wear']);
        $this->assertEquals(10, $row['scores']['production_number']);
    }

    public function test_category_total_is_the_average_of_the_judges(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$j1, $j2] = User::factory()->count(2)->create(['role' => 'judge']);
        $candidate = $this->candidate(1);

        TopFiveSelectionScore::create(['candidate_id' => $candidate->id, 'judge_id' => $j1->id, 'swim_wear' => 20]);
        TopFiveSelectionScore::create(['candidate_id' => $candidate->id, 'judge_id' => $j2->id, 'swim_wear' => 10]);

        $page = $this->actingAs($admin)->get('/admin/swim_wear')->assertOk();
        $row = collect($page->viewData('page')['props']['femaleCandidates'])->first();

        $this->assertEquals(15, $row['total']);
        $this->assertEquals(20, $row['scores'][$j1->id]);   // each judge's own score is unchanged
    }

    public function test_finals_total_is_out_of_100(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $judges = User::factory()->count(5)->create(['role' => 'judge']);
        $finalist = TopFiveCandidates::create(['candidate_id' => $this->candidate(1)->id]);

        foreach ($judges as $judge) {
            TopFiveScore::create(array_merge(
                ['top_five_id' => $finalist->id, 'judge_id' => $judge->id],
                Criteria::FINALS
            ));
        }

        $page = $this->actingAs($admin)->get('/admin/total_results')->assertOk();
        $row = collect($page->viewData('page')['props']['femaleCandidates'])->first();

        $this->assertEquals(100, $row['total']);
        $this->assertEquals(50, $row['scores']['face_and_figure']);
    }
}
