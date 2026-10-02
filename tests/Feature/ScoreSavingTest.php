<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\TopFiveCandidates;
use App\Models\TopFiveScore;
use App\Models\TopFiveSelectionScore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScoreSavingTest extends TestCase
{
    use RefreshDatabase;

    private function candidates(int $count): array
    {
        return collect(range(1, $count))->map(fn ($n) => Candidate::create([
            'candidate_number' => $n, 'profile_img' => 'x.jpg', 'first_name' => 'C',
            'last_name' => (string) $n, 'course' => 'BSIT', 'gender' => 'female',
        ])->id)->all();
    }

    /** Count queries that touch $table while $callback runs. */
    private function countQueries(string $table, callable $callback): int
    {
        $count = 0;
        DB::listen(function ($query) use (&$count, $table) {
            if (str_contains($query->sql, $table)) {
                $count++;
            }
        });
        $callback();

        return $count;
    }

    public function test_round_one_scores_save_with_one_lookup_and_keep_totals_right(): void
    {
        $judge = User::factory()->create(['role' => 'judge']);
        $ids = $this->candidates(4);

        // An earlier category already scored for the first candidate.
        TopFiveSelectionScore::create(['candidate_id' => $ids[0], 'judge_id' => $judge->id, 'production_number' => 8, 'total_scores' => 8]);

        $selects = 0;
        DB::listen(function ($q) use (&$selects) {
            if (str_starts_with(strtolower($q->sql), 'select') && str_contains($q->sql, 'top_five_selection_scores')) {
                $selects++;
            }
        });

        $this->actingAs($judge)
            ->post('/swim_wear/scores', ['judge_id' => $judge->id, 'scores' => array_fill_keys($ids, 20)])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $selects, 'existing rows should load in a single query');
        $this->assertSame(4, TopFiveSelectionScore::where('judge_id', $judge->id)->count());

        $first = TopFiveSelectionScore::where('candidate_id', $ids[0])->first();
        $this->assertEquals(20, $first->swim_wear);
        $this->assertEquals(8, $first->production_number);   // earlier category untouched
        $this->assertEquals(28, $first->total_scores);       // total recalculated
    }

    public function test_finals_scores_skip_non_finalists_and_look_finalists_up_once(): void
    {
        $judge = User::factory()->create(['role' => 'judge']);
        [$a, $b, $notFinalist] = $this->candidates(3);
        $fa = TopFiveCandidates::create(['candidate_id' => $a]);
        $fb = TopFiveCandidates::create(['candidate_id' => $b]);

        $finalistLookups = $this->countQueries('top_five_candidates', function () use ($judge, $a, $b, $notFinalist) {
            $this->actingAs($judge)
                ->post('/delivery/store', ['judge_id' => $judge->id, 'scores' => [$a => 30, $b => 25, $notFinalist => 10]])
                ->assertSessionHasNoErrors();
        });

        $this->assertSame(1, $finalistLookups);
        $this->assertEquals(30, TopFiveScore::where('top_five_id', $fa->id)->value('delivery'));
        $this->assertEquals(25, TopFiveScore::where('top_five_id', $fb->id)->value('total_score'));
        $this->assertSame(2, TopFiveScore::count());   // non-finalist ignored
    }
}
