<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\TopFiveCandidates;
use App\Models\TopFiveScore;
use App\Models\TopFiveSelectionScore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Once the admin sets the Top 3 finalists, Round 1 (Top 3 Selection) scores are
 * final: judges can no longer submit or change them. Finals scoring still works.
 */
class RoundOneLockTest extends TestCase
{
    use RefreshDatabase;

    private function candidate(int $number = 1): Candidate
    {
        return Candidate::create([
            'candidate_number' => $number,
            'profile_img' => 'x.jpg',
            'first_name' => 'Candidate',
            'last_name' => (string) $number,
            'course' => 'BSIT',
            'gender' => 'female',
        ]);
    }

    public function test_round_one_scores_can_be_saved_before_finalists_are_set(): void
    {
        $candidate = $this->candidate();
        $judge = User::factory()->create(['role' => 'judge']);

        $this->actingAs($judge)
            ->post('/swim_wear/scores', ['scores' => [$candidate->id => 20]])
            ->assertSessionHasNoErrors();

        $this->assertSame(20.0, (float) TopFiveSelectionScore::first()->swim_wear);
    }

    public function test_round_one_scores_cannot_change_after_finalists_are_set(): void
    {
        $candidate = $this->candidate();
        $judge = User::factory()->create(['role' => 'judge']);

        $this->actingAs($judge)->post('/swim_wear/scores', ['scores' => [$candidate->id => 20]]);
        TopFiveCandidates::create(['candidate_id' => $candidate->id]);

        $this->actingAs($judge)
            ->post('/swim_wear/scores', ['scores' => [$candidate->id => 5]])
            ->assertSessionHasErrors('scores');

        $this->assertSame(20.0, (float) TopFiveSelectionScore::first()->swim_wear);
    }

    public function test_new_round_one_scores_are_rejected_after_finalists_are_set(): void
    {
        $finalist = $this->candidate(1);
        $other = $this->candidate(2);
        TopFiveCandidates::create(['candidate_id' => $finalist->id]);
        $judge = User::factory()->create(['role' => 'judge']);

        $this->actingAs($judge)
            ->post('/casual_wear/scores', ['scores' => [$other->id => 20]])
            ->assertSessionHasErrors('scores');

        $this->assertSame(0, TopFiveSelectionScore::count());
    }

    public function test_finals_scores_still_save_after_finalists_are_set(): void
    {
        $candidate = $this->candidate();
        TopFiveCandidates::create(['candidate_id' => $candidate->id]);
        $judge = User::factory()->create(['role' => 'judge']);

        $this->actingAs($judge)
            ->post('/delivery/store', ['scores' => [$candidate->id => 30]])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, TopFiveScore::count());
    }
}
