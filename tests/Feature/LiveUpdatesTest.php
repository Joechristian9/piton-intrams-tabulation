<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\User;
use App\Support\LiveVersions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Open pages refresh themselves when data they show changes: the server bumps a
 * version stamp, and pages and pollers compare stamps (resources/js/lib/liveVersions.js).
 */
class LiveUpdatesTest extends TestCase
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

    public function test_setting_finalists_changes_the_finalists_stamp_judges_poll(): void
    {
        $female = $this->candidates('female', 3);
        $male = $this->candidates('male', 3);
        $admin = User::factory()->create(['role' => 'admin']);
        $judge = User::factory()->create(['role' => 'judge']);

        $before = $this->actingAs($judge)->getJson(route('judge.notifications'))
            ->assertOk()->json('live.finalists');

        $this->actingAs($admin)
            ->post('/top-five', ['password' => 'password', 'candidate_ids' => [...$female, ...$male]])
            ->assertSessionHasNoErrors();

        $after = $this->actingAs($judge)->getJson(route('judge.notifications'))
            ->json('live.finalists');

        $this->assertNotNull($after);
        $this->assertNotSame($before, $after);
    }

    public function test_saving_scores_changes_the_scores_stamp_admins_poll(): void
    {
        $ids = $this->candidates('female', 2);
        $admin = User::factory()->create(['role' => 'admin']);
        $judge = User::factory()->create(['role' => 'judge']);

        $before = $this->actingAs($admin)->getJson(route('admin.score_submissions'))
            ->assertOk()->json('live.scores');

        $this->actingAs($judge)
            ->post('/swim_wear/scores', ['judge_id' => $judge->id, 'scores' => array_fill_keys($ids, 20)])
            ->assertSessionHasNoErrors();

        $after = $this->actingAs($admin)->getJson(route('admin.score_submissions'))
            ->json('live.scores');

        $this->assertNotNull($after);
        $this->assertNotSame($before, $after);
    }

    public function test_pages_get_the_stamps_they_were_built_with(): void
    {
        LiveVersions::bump(LiveVersions::FINALISTS);
        $stamps = LiveVersions::all();
        $judge = User::factory()->create(['role' => 'judge']);

        $this->actingAs($judge)
            ->get(route('production_number'))
            ->assertInertia(fn (Assert $page) => $page->where('live', $stamps));
    }
}
