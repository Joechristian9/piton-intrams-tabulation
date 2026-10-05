<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\TopFiveCandidates;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetTopThreeTest extends TestCase
{
    use RefreshDatabase;

    private function candidates(string $gender, int $count): array
    {
        return collect(range(1, $count))->map(fn ($n) => Candidate::create([
            'candidate_number' => $n,
            'profile_img' => 'x.jpg',
            'first_name' => ucfirst($gender),
            'last_name' => (string) $n,
            'course' => 'BSIT',
            'gender' => $gender,
        ])->id)->all();
    }

    public function test_saves_three_male_and_three_female_finalists(): void
    {
        $female = $this->candidates('female', 4);
        $male = $this->candidates('male', 4);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post('/top-five', ['password' => 'password', 'candidate_ids' => [...array_slice($female, 0, 3), ...array_slice($male, 0, 3)]])
            ->assertSessionHasNoErrors();

        $this->assertSame(6, TopFiveCandidates::count());
    }

    public function test_rejects_a_tie_left_unresolved(): void
    {
        $female = $this->candidates('female', 4);
        $male = $this->candidates('male', 3);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post('/top-five', ['password' => 'password', 'candidate_ids' => [...$female, ...$male]])
            ->assertSessionHasErrors('candidate_ids');

        $this->assertSame(0, TopFiveCandidates::count());
    }

    public function test_resaving_keeps_finalists_who_stay_in(): void
    {
        $female = $this->candidates('female', 4);
        $male = $this->candidates('male', 3);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/top-five', ['password' => 'password', 'candidate_ids' => [...array_slice($female, 0, 3), ...$male]]);
        $kept = TopFiveCandidates::where('candidate_id', $female[0])->value('id');

        // Swap the 3rd female finalist for the 4th.
        $this->actingAs($admin)->post('/top-five', ['password' => 'password', 'candidate_ids' => [$female[0], $female[1], $female[3], ...$male]]);

        $this->assertSame($kept, TopFiveCandidates::where('candidate_id', $female[0])->value('id'));
        $this->assertFalse(TopFiveCandidates::where('candidate_id', $female[2])->exists());
        $this->assertTrue(TopFiveCandidates::where('candidate_id', $female[3])->exists());
    }
}
