<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\TopFiveCandidates;
use App\Models\TopFiveScore;
use App\Models\TopFiveSelectionScore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Admins can delete a judge after re-entering their password. The judge's scores
 * go with them, so results are recalculated over the remaining judges.
 */
class JudgeDeletionTest extends TestCase
{
    use RefreshDatabase;

    /** A judge with one Round 1 and one finals score. */
    private function judgeWithScores(): User
    {
        $judge = User::factory()->create(['role' => 'judge']);
        $candidate = Candidate::create([
            'candidate_number' => $judge->id,
            'profile_img' => 'x.jpg',
            'first_name' => 'Candidate',
            'last_name' => (string) $judge->id,
            'course' => 'BSIT',
            'gender' => 'female',
        ]);
        $finalist = TopFiveCandidates::create(['candidate_id' => $candidate->id]);

        TopFiveSelectionScore::create(['candidate_id' => $candidate->id, 'judge_id' => $judge->id, 'swim_wear' => 20]);
        TopFiveScore::create(['top_five_id' => $finalist->id, 'judge_id' => $judge->id, 'delivery' => 30]);

        return $judge;
    }

    public function test_admin_deletes_a_judge_and_their_scores_with_the_correct_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $judge = $this->judgeWithScores();
        $other = $this->judgeWithScores();

        $this->actingAs($admin)
            ->delete(route('admin.judges.destroy', $judge), ['password' => 'password'])
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($judge);
        $this->assertSame(0, TopFiveSelectionScore::where('judge_id', $judge->id)->count());
        $this->assertSame(0, TopFiveScore::where('judge_id', $judge->id)->count());

        // Other judges and their scores are untouched.
        $this->assertModelExists($other);
        $this->assertSame(1, TopFiveSelectionScore::where('judge_id', $other->id)->count());
        $this->assertSame(1, TopFiveScore::where('judge_id', $other->id)->count());
    }

    public function test_a_wrong_password_deletes_nothing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $judge = $this->judgeWithScores();

        $this->actingAs($admin)
            ->delete(route('admin.judges.destroy', $judge), ['password' => 'wrong'])
            ->assertSessionHasErrors('password');

        $this->assertModelExists($judge);
        $this->assertSame(1, TopFiveSelectionScore::where('judge_id', $judge->id)->count());
    }

    public function test_judges_cannot_delete_judges(): void
    {
        $judge = User::factory()->create(['role' => 'judge']);
        $other = User::factory()->create(['role' => 'judge']);

        $this->actingAs($judge)
            ->delete(route('admin.judges.destroy', $other), ['password' => 'password'])
            ->assertForbidden();

        $this->assertModelExists($other);
    }

    public function test_admin_accounts_cannot_be_deleted_here(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->delete(route('admin.judges.destroy', $otherAdmin), ['password' => 'password'])
            ->assertNotFound();

        $this->assertModelExists($otherAdmin);
    }

    public function test_judges_page_shows_each_judges_score_count(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $judge = $this->judgeWithScores();
        User::factory()->create(['role' => 'judge']);

        $this->actingAs($admin)
            ->get(route('admin.judges.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('judges.0.id', $judge->id)
                ->where('judges.0.score_count', 2)
                ->where('judges.1.score_count', 0));
    }
}
