<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\TopFiveCandidates;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Setting the Top 3 closes Round 1 for good, so only an admin can do it, and only
 * after re-entering their account password.
 */
class SetTopThreeSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function finalistIds(): array
    {
        return collect(['female', 'male'])->flatMap(fn ($gender) => collect(range(1, 3))
            ->map(fn ($n) => Candidate::create([
                'candidate_number' => $n,
                'profile_img' => 'x.jpg',
                'first_name' => ucfirst($gender),
                'last_name' => (string) $n,
                'course' => 'BSIT',
                'gender' => $gender,
            ])->id))->all();
    }

    public function test_admin_with_the_correct_password_sets_the_top_three(): void
    {
        $ids = $this->finalistIds();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post('/top-five', ['password' => 'password', 'candidate_ids' => $ids])
            ->assertSessionHasNoErrors();

        $this->assertSame(6, TopFiveCandidates::count());
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        $ids = $this->finalistIds();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post('/top-five', ['password' => 'not-my-password', 'candidate_ids' => $ids])
            ->assertSessionHasErrors('password');

        $this->assertSame(0, TopFiveCandidates::count());
    }

    public function test_a_missing_password_is_rejected(): void
    {
        $ids = $this->finalistIds();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post('/top-five', ['candidate_ids' => $ids])
            ->assertSessionHasErrors('password');

        $this->assertSame(0, TopFiveCandidates::count());
    }

    public function test_judges_cannot_set_the_top_three_even_with_their_own_password(): void
    {
        $ids = $this->finalistIds();
        $judge = User::factory()->create(['role' => 'judge']);

        $this->actingAs($judge)
            ->post('/top-five', ['password' => 'password', 'candidate_ids' => $ids])
            ->assertForbidden();

        $this->assertSame(0, TopFiveCandidates::count());
    }
}
