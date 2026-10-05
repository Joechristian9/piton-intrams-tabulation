<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\TopFiveCandidates;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The sidebar hides the Top 3 Finalist categories until the admin has set the
 * finalists; every page gets a shared `finalistsSet` flag for that.
 */
class FinalistsSetPropTest extends TestCase
{
    use RefreshDatabase;

    public function test_finalists_set_is_false_before_the_admin_sets_finalists(): void
    {
        $judge = User::factory()->create(['role' => 'judge']);

        $this->actingAs($judge)
            ->get(route('production_number'))
            ->assertInertia(fn (Assert $page) => $page->where('finalistsSet', false));
    }

    public function test_finalists_set_is_true_once_finalists_exist(): void
    {
        $judge = User::factory()->create(['role' => 'judge']);
        $candidate = Candidate::create([
            'candidate_number' => 1,
            'profile_img' => 'x.jpg',
            'first_name' => 'Finalist',
            'last_name' => 'One',
            'course' => 'BSIT',
            'gender' => 'female',
        ]);
        TopFiveCandidates::create(['candidate_id' => $candidate->id]);

        $this->actingAs($judge)
            ->get(route('production_number'))
            ->assertInertia(fn (Assert $page) => $page->where('finalistsSet', true));
    }
}
