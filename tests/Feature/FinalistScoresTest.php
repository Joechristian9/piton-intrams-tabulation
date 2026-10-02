<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\TopFiveCandidates;
use App\Models\TopFiveScore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinalistScoresTest extends TestCase
{
    use RefreshDatabase;

    private function finalists(int $count): array
    {
        return collect(range(1, $count))->map(function ($n) {
            $candidate = Candidate::create([
                'candidate_number' => $n,
                'profile_img' => 'x.jpg',
                'first_name' => 'Finalist',
                'last_name' => (string) $n,
                'course' => 'BSIT',
                'gender' => 'female',
            ]);

            return TopFiveCandidates::create(['candidate_id' => $candidate->id]);
        })->all();
    }

    public function test_judge_sees_their_own_finalist_scores_with_one_score_query(): void
    {
        [$a, $b, $c] = $this->finalists(3);
        $judge = User::factory()->create(['role' => 'judge']);
        $other = User::factory()->create(['role' => 'judge']);

        TopFiveScore::create(['top_five_id' => $b->id, 'judge_id' => $judge->id, 'delivery' => 30]);
        TopFiveScore::create(['top_five_id' => $a->id, 'judge_id' => $other->id, 'delivery' => 99]);

        $scoreQueries = 0;
        DB::listen(function ($query) use (&$scoreQueries) {
            if (str_contains($query->sql, 'top_five_scores')) {
                $scoreQueries++;
            }
        });

        $response = $this->actingAs($judge)->get('/delivery')->assertOk();

        $deliveries = collect($response->viewData('page')['props']['candidates'])->pluck('delivery', 'id');

        $this->assertEquals(30, $deliveries[$b->id]);
        $this->assertNull($deliveries[$a->id]);   // another judge's score is not shown
        $this->assertNull($deliveries[$c->id]);
        $this->assertSame(1, $scoreQueries);
    }

    public function test_admin_results_sum_scores_per_finalist(): void
    {
        [$a, $b] = $this->finalists(2);
        $admin = User::factory()->create(['role' => 'admin']);
        $j1 = User::factory()->create(['role' => 'judge']);
        $j2 = User::factory()->create(['role' => 'judge']);

        TopFiveScore::create(['top_five_id' => $a->id, 'judge_id' => $j1->id, 'delivery' => 30]);
        TopFiveScore::create(['top_five_id' => $a->id, 'judge_id' => $j2->id, 'delivery' => 20]);
        TopFiveScore::create(['top_five_id' => $b->id, 'judge_id' => $j1->id, 'delivery' => 10]);

        $response = $this->actingAs($admin)->get('/admin/delivery')->assertOk();
        $rows = collect($response->viewData('page')['props']['femaleCandidates']);

        $first = $rows->firstWhere('candidate.id', $a->candidate_id);
        $second = $rows->firstWhere('candidate.id', $b->candidate_id);

        $this->assertEquals(50, $first['total']);
        $this->assertEquals(1, $first['rank']);
        $this->assertEquals(10, $second['total']);
        $this->assertEquals(2, $second['rank']);
        $this->assertEquals(30, $first['scores'][$j1->id]);
        $this->assertEquals(20, $first['scores'][$j2->id]);
    }
}
