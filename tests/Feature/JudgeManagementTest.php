<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\TopFiveSelectionScore;
use App\Models\User;
use App\Services\TopFiveSelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class JudgeManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_judges_cannot_open_judge_management(): void
    {
        $judge = User::factory()->create(['role' => 'judge']);

        $this->actingAs($judge)->get('/admin/judges')->assertForbidden();
    }

    public function test_admin_can_add_a_judge(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/judges', [
                'name' => 'Judge Maria',
                'email' => 'maria@example.com',
                'password' => 'secret123',
                'password_confirmation' => 'secret123',
            ])
            ->assertSessionHasNoErrors();

        $judge = User::where('email', 'maria@example.com')->first();
        $this->assertSame('judge', $judge->role);
        $this->assertTrue(Hash::check('secret123', $judge->password));
    }

    public function test_admin_can_rename_and_change_password(): void
    {
        $judge = User::factory()->create(['role' => 'judge', 'password' => Hash::make('old-pass')]);

        $this->actingAs($this->admin())
            ->put("/admin/judges/{$judge->id}", [
                'name' => 'Renamed',
                'email' => $judge->email,
                'password' => 'new-pass',
                'password_confirmation' => 'new-pass',
            ])
            ->assertSessionHasNoErrors();

        $judge->refresh();
        $this->assertSame('Renamed', $judge->name);
        $this->assertTrue(Hash::check('new-pass', $judge->password));
    }

    public function test_blank_password_keeps_the_current_one(): void
    {
        $judge = User::factory()->create(['role' => 'judge', 'password' => Hash::make('old-pass')]);

        $this->actingAs($this->admin())
            ->put("/admin/judges/{$judge->id}", [
                'name' => 'Renamed',
                'email' => $judge->email,
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('old-pass', $judge->fresh()->password));
    }

    public function test_renamed_judge_scores_still_appear_in_results(): void
    {
        $judge = User::factory()->create(['role' => 'judge', 'name' => 'Dr. Santos']);
        $candidate = Candidate::create([
            'candidate_number' => 1,
            'first_name' => 'Test',
            'last_name' => 'Candidate',
            'profile_img' => 'test.jpg',
            'course' => 'BSIT',
            'gender' => 'female',
        ]);
        TopFiveSelectionScore::create([
            'candidate_id' => $candidate->id,
            'judge_id' => $judge->id,
            'casual_wear' => 12,
        ]);

        $results = app(TopFiveSelectionService::class)->getResultsPerCategory('casual_wear');

        $this->assertSame([['id' => $judge->id, 'name' => 'Dr. Santos']], $results['judgeOrder']);
        $this->assertEquals(12, $results['femaleCandidates'][0]['scores'][$judge->id]);
    }
}
