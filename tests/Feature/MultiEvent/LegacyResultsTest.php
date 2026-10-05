<?php

namespace Tests\Feature\MultiEvent;

use App\Legacy\LegacyResults;
use App\Legacy\Snapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The frozen copy of the old formulas. It was proven equal to the old services
 * (TopFiveSelectionService / TopFiveService) before they were deleted; it must
 * not change, because events:migrate-legacy compares against it.
 */
class LegacyResultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_map_matches_the_current_pageant(): void
    {
        $this->assertSame([
            'production_number' => ['round' => 1, 'name' => 'Production Number', 'max' => 10, 'position' => 1],
            'casual_wear' => ['round' => 1, 'name' => 'Sports Wear', 'max' => 25, 'position' => 2],
            'swim_wear' => ['round' => 1, 'name' => 'Swim Wear', 'max' => 25, 'position' => 3],
            'formal_wear' => ['round' => 1, 'name' => 'Formal Wear', 'max' => 25, 'position' => 4],
            'closed_door_interview' => ['round' => 1, 'name' => 'Casual Interview', 'max' => 15, 'position' => 5],
            'face_and_figure' => ['round' => 2, 'name' => 'Beauty of the Face and Figure', 'max' => 50, 'position' => 1],
            'delivery' => ['round' => 2, 'name' => 'Delivery', 'max' => 40, 'position' => 2],
            'overall_appeal' => ['round' => 2, 'name' => 'Over-all Appeal / X-factor', 'max' => 10, 'position' => 3],
        ], LegacyResults::CATEGORY_MAP);
    }

    public function test_normalize_orders_tied_rows_by_candidate_id(): void
    {
        $g = ['female' => [
            ['candidate_id' => 9, 'scores' => [], 'total' => 5.0, 'rank' => 1],
            ['candidate_id' => 4, 'scores' => [], 'total' => 5.0, 'rank' => 1],
            ['candidate_id' => 7, 'scores' => [], 'total' => 1.0, 'rank' => 3],
        ], 'male' => []];

        $this->assertSame([4, 9, 7], array_column(Snapshot::normalize(['k' => $g])['k']['female'], 'candidate_id'));
    }

    public function test_diff_reports_a_changed_total(): void
    {
        $row = ['candidate_id' => 1, 'scores' => [3 => 10.0], 'total' => 81.25, 'rank' => 1];
        $a = ['round1' => ['female' => [$row], 'male' => []]];
        $b = ['round1' => ['female' => [[...$row, 'total' => 81.24]], 'male' => []]];

        $this->assertSame([], Snapshot::diff($a, $a));
        $this->assertSame(['round1.female[0].total: 81.25 vs 81.24'], Snapshot::diff($a, $b));
    }
}
