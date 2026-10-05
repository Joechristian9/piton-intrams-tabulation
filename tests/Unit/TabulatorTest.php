<?php

namespace Tests\Unit;

use App\Results\Tabulator;
use PHPUnit\Framework\TestCase;

/**
 * The scoring formulas (spec §7). Numbers are hand-checked; these must keep
 * matching today's results exactly.
 */
class TabulatorTest extends TestCase
{
    public function test_category_averages_over_all_judges_and_ranks_ties_1_2_2_4(): void
    {
        $c = fn ($id) => ['id' => $id];
        $rows = Tabulator::category([$c(10), $c(11), $c(12), $c(13)], [1, 2, 3], [
            10 => [1 => 20.0, 2 => 22.5],             // judge 3 missing -> 0
            11 => [1 => 25.0, 2 => 25.0, 3 => 25.0],
            12 => [1 => 20.0, 2 => 22.5, 3 => 0.0],
        ]);

        $this->assertSame([11, 10, 12, 13], array_map(fn ($r) => $r['candidate']['id'], $rows));
        $this->assertSame([25.0, 14.17, 14.17, 0.0], array_column($rows, 'total'));
        $this->assertSame([1, 2, 2, 4], array_column($rows, 'rank'));
        $this->assertSame([1 => 20.0, 2 => 22.5, 3 => 0.0], $rows[1]['scores']);
    }

    public function test_round_total_rounds_only_at_the_end(): void
    {
        $rows = Tabulator::round([['id' => 1]], [100, 200], [1, 2, 3], [
            1 => [100 => [1 => 10.0, 2 => 10.0, 3 => 11.0], 200 => [1 => 10.0, 2 => 10.0, 3 => 11.0]],
        ]);

        $this->assertSame([100 => 10.33, 200 => 10.33], $rows[0]['scores']);
        $this->assertSame(20.67, $rows[0]['total']);   // not 20.66
    }

    public function test_round_counts_a_missing_category_as_zero_and_ranks(): void
    {
        $rows = Tabulator::round([['id' => 1], ['id' => 2]], [100], [1, 2], [
            2 => [100 => [1 => 8.0, 2 => 9.0]],
        ]);

        $this->assertSame([2, 1], array_map(fn ($r) => $r['candidate']['id'], $rows));
        $this->assertSame([8.5, 0.0], array_column($rows, 'total'));
        $this->assertSame([1, 2], array_column($rows, 'rank'));
        $this->assertSame([100 => 0.0], $rows[1]['scores']);
    }

    public function test_weighted_total_uses_unrounded_round_totals(): void
    {
        $r1 = [['candidate' => ['id' => 1], 'raw_total' => 85.4, 'total' => 85.4]];
        $f = [['candidate' => ['id' => 1], 'raw_total' => 92.1, 'total' => 92.1]];

        $rows = Tabulator::weighted($r1, $f, 40, 60);

        $this->assertSame(89.42, $rows[0]['total']);
        $this->assertSame([85.4, 92.1], [$rows[0]['round1'], $rows[0]['finals']]);
        $this->assertSame(1, $rows[0]['rank']);
    }

    public function test_weighted_keeps_only_finalists_and_ranks_them(): void
    {
        $r1 = [
            ['candidate' => ['id' => 1], 'raw_total' => 90.0, 'total' => 90.0],
            ['candidate' => ['id' => 2], 'raw_total' => 80.0, 'total' => 80.0],
            ['candidate' => ['id' => 3], 'raw_total' => 70.0, 'total' => 70.0],
        ];
        $f = [
            ['candidate' => ['id' => 3], 'raw_total' => 100.0, 'total' => 100.0],
            ['candidate' => ['id' => 1], 'raw_total' => 50.0, 'total' => 50.0],
        ];

        $rows = Tabulator::weighted($r1, $f, 50, 50);

        $this->assertSame([3, 1], array_map(fn ($r) => $r['candidate']['id'], $rows));
        $this->assertSame([85.0, 70.0], array_column($rows, 'total'));
    }

    public function test_no_judges_divides_by_one(): void
    {
        $rows = Tabulator::category([['id' => 1]], [], []);

        $this->assertSame(0.0, $rows[0]['total']);
    }

    public function test_rank_is_stable_for_equal_totals(): void
    {
        $rows = Tabulator::rank([
            ['candidate' => ['id' => 1], 'total' => 5.0],
            ['candidate' => ['id' => 2], 'total' => 7.0],
            ['candidate' => ['id' => 3], 'total' => 5.0],
        ]);

        $this->assertSame([2, 1, 3], array_map(fn ($r) => $r['candidate']['id'], $rows));
        $this->assertSame([1, 2, 2], array_column($rows, 'rank'));
    }
}
