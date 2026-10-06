<?php

namespace App\Legacy;

/**
 * Compares results snapshots: ['page key' => ['group key' => Row[]]] with
 * Row = ['candidate_id' => int, 'scores' => array, 'total' => float, 'rank' => int].
 */
class Snapshot
{
    /**
     * Orders tied rows (same rank) by candidate id. Ranks, totals and scores are
     * compared strictly; the display order inside a tie is not.
     */
    public static function normalize(array $snapshot): array
    {
        foreach ($snapshot as $page => $groups) {
            foreach ($groups as $group => $rows) {
                usort($rows, fn ($a, $b) => [$a['rank'], $a['candidate_id']] <=> [$b['rank'], $b['candidate_id']]);
                $snapshot[$page][$group] = $rows;
            }
        }

        return $snapshot;
    }

    /** @return array<int, string> one line per difference; empty when identical */
    public static function diff(array $expected, array $actual): array
    {
        $differences = [];

        foreach (array_unique([...array_keys($expected), ...array_keys($actual)]) as $page) {
            foreach (array_unique([...array_keys($expected[$page] ?? []), ...array_keys($actual[$page] ?? [])]) as $group) {
                $a = $expected[$page][$group] ?? null;
                $b = $actual[$page][$group] ?? null;
                $path = "{$page}.{$group}";

                if ($a === null || $b === null || count($a) !== count($b)) {
                    $differences[] = sprintf('%s: %s rows vs %s rows', $path, $a === null ? 'no' : count($a), $b === null ? 'no' : count($b));
                    continue;
                }

                foreach ($a as $i => $rowA) {
                    $rowB = $b[$i];
                    foreach (['candidate_id', 'rank', 'total'] as $field) {
                        if ($rowA[$field] !== $rowB[$field]) {
                            $differences[] = "{$path}[{$i}].{$field}: {$rowA[$field]} vs {$rowB[$field]}";
                        }
                    }
                    if ($rowA['scores'] !== $rowB['scores']) {
                        $differences[] = "{$path}[{$i}].scores: " . json_encode($rowA['scores']) . ' vs ' . json_encode($rowB['scores']);
                    }
                }
            }
        }

        return $differences;
    }
}
