<?php

namespace Tests\Feature\MultiEvent;

use App\Legacy\LegacyResults;
use App\Legacy\Snapshot;
use App\Services\TopFiveSelectionService;
use App\Services\TopFiveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The frozen copy of today's formulas must equal today's services exactly, so
 * the migration compares against the real current results.
 */
class LegacyResultsTest extends TestCase
{
    use RefreshDatabase, SeedsLegacyData;

    /** Today's service output → snapshot group shape. */
    private function fromService(array $result): array
    {
        $rows = fn ($list) => array_map(fn ($r) => [
            'candidate_id' => (int) (is_array($r['candidate']) ? $r['candidate']['id'] : $r['candidate']->id),
            'scores' => array_map('floatval', $r['scores']),
            'total' => (float) $r['total'],
            'rank' => (int) $r['rank'],
        ], $list);

        return ['female' => $rows($result['femaleCandidates']), 'male' => $rows($result['maleCandidates'])];
    }

    public function test_legacy_snapshot_matches_todays_services(): void
    {
        $this->seedLegacy();
        $snapshot = LegacyResults::snapshot();
        $selection = app(TopFiveSelectionService::class);
        $finals = app(TopFiveService::class);

        foreach (LegacyResults::CATEGORY_MAP as $key => $meta) {
            $service = $meta['round'] === 1 ? $selection : $finals;
            $this->assertSame(
                Snapshot::normalize(['x' => $this->fromService($service->getResultsPerCategory($key))])['x'],
                Snapshot::normalize($snapshot)["category:{$key}"],
                "category {$key}"
            );
        }

        $this->assertSame(
            Snapshot::normalize(['x' => $this->fromService($selection->getTopFiveSelectionResults())])['x'],
            Snapshot::normalize($snapshot)['round1']
        );
        $this->assertSame(
            Snapshot::normalize(['x' => $this->fromService($finals->getTotalResults())])['x'],
            Snapshot::normalize($snapshot)['finals']
        );
    }

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
