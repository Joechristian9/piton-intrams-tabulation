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
 * Scores are always saved for the logged-in judge: the `judge_id` the browser
 * sends is ignored, and only judges can submit scores.
 */
class ScoreOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function candidate(): Candidate
    {
        return Candidate::create([
            'candidate_number' => 1,
            'profile_img' => 'x.jpg',
            'first_name' => 'Candidate',
            'last_name' => 'One',
            'course' => 'BSIT',
            'gender' => 'female',
        ]);
    }

    public function test_round_one_scores_are_saved_for_the_logged_in_judge_not_the_sent_judge_id(): void
    {
        $candidate = $this->candidate();
        $judge = User::factory()->create(['role' => 'judge']);
        $other = User::factory()->create(['role' => 'judge']);

        $this->actingAs($judge)
            ->post('/swim_wear/scores', ['judge_id' => $other->id, 'scores' => [$candidate->id => 20]])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, TopFiveSelectionScore::where('judge_id', $judge->id)->count());
        $this->assertSame(0, TopFiveSelectionScore::where('judge_id', $other->id)->count());
    }

    public function test_finals_scores_are_saved_for_the_logged_in_judge_not_the_sent_judge_id(): void
    {
        $candidate = $this->candidate();
        TopFiveCandidates::create(['candidate_id' => $candidate->id]);
        $judge = User::factory()->create(['role' => 'judge']);
        $other = User::factory()->create(['role' => 'judge']);

        $this->actingAs($judge)
            ->post('/delivery/store', ['judge_id' => $other->id, 'scores' => [$candidate->id => 30]])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, TopFiveScore::where('judge_id', $judge->id)->count());
        $this->assertSame(0, TopFiveScore::where('judge_id', $other->id)->count());
    }

    public function test_scores_can_be_submitted_without_a_judge_id(): void
    {
        $candidate = $this->candidate();
        $judge = User::factory()->create(['role' => 'judge']);

        $this->actingAs($judge)
            ->post('/casual_wear/scores', ['scores' => [$candidate->id => 20]])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, TopFiveSelectionScore::where('judge_id', $judge->id)->count());
    }

    public function test_admins_cannot_submit_scores(): void
    {
        $candidate = $this->candidate();
        TopFiveCandidates::create(['candidate_id' => $candidate->id]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post('/swim_wear/scores', ['judge_id' => $admin->id, 'scores' => [$candidate->id => 20]])
            ->assertForbidden();
        $this->actingAs($admin)
            ->post('/delivery/store', ['judge_id' => $admin->id, 'scores' => [$candidate->id => 30]])
            ->assertForbidden();

        $this->assertSame(0, TopFiveSelectionScore::count());
        $this->assertSame(0, TopFiveScore::count());
    }
}
