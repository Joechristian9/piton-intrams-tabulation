<?php

namespace App\Results;

/**
 * Every scoring formula, with no database access (spec §7). The arithmetic
 * mirrors the original services exactly so migrated results stay identical:
 * a missing score counts as 0, averages divide by the whole judge panel, and
 * totals are rounded only at the end.
 *
 * Candidates are passed through untouched as `candidate`; only `id` is read.
 */
class Tabulator
{
    /**
     * One category: each judge's raw score and their average.
     *
     * @param  array<int, array>  $candidates
     * @param  array<int, int>  $judgeIds  in column order
     * @param  array<int, array<int, float>>  $scores  [candidateId][judgeId] => score
     */
    public static function category(array $candidates, array $judgeIds, array $scores): array
    {
        $rows = [];

        foreach ($candidates as $candidate) {
            $byJudge = [];
            foreach ($judgeIds as $judgeId) {
                $byJudge[$judgeId] = (float) ($scores[$candidate['id']][$judgeId] ?? 0);
            }

            $rows[] = [
                'candidate' => $candidate,
                'scores' => $byJudge,
                'total' => round(array_sum($byJudge) / max(1, count($judgeIds)), 2),
                'rank' => 0,
            ];
        }

        return self::rank($rows);
    }

    /**
     * A whole round: each category's average and the round total.
     *
     * @param  array<int, array>  $candidates
     * @param  array<int, int>  $categoryIds  in column order
     * @param  array<int, int>  $judgeIds
     * @param  array<int, array<int, array<int, float>>>  $scores  [candidateId][categoryId][judgeId] => score
     */
    public static function round(array $candidates, array $categoryIds, array $judgeIds, array $scores): array
    {
        $judgeCount = max(1, count($judgeIds));
        $rows = [];

        foreach ($candidates as $candidate) {
            $averages = [];
            foreach ($categoryIds as $categoryId) {
                $sum = 0;
                foreach ($scores[$candidate['id']][$categoryId] ?? [] as $score) {
                    $sum += $score;
                }
                $averages[$categoryId] = $sum / $judgeCount;
            }

            $raw = array_sum($averages);

            $rows[] = [
                'candidate' => $candidate,
                'scores' => array_map(fn ($avg) => round($avg, 2), $averages),
                'total' => round($raw, 2),
                'raw_total' => $raw,
                'rank' => 0,
            ];
        }

        return self::rank($rows);
    }

    /**
     * Final standings when finals don't start from zero: Round 1 and Finals totals
     * weighted by percentage. Only candidates in $finalsRows are included.
     *
     * @param  array<int, array>  $round1Rows  rows from round() for round 1
     * @param  array<int, array>  $finalsRows  rows from round() for the finals
     */
    public static function weighted(array $round1Rows, array $finalsRows, int $round1Weight, int $finalsWeight): array
    {
        $round1 = [];
        foreach ($round1Rows as $row) {
            $round1[$row['candidate']['id']] = $row['raw_total'];
        }

        $rows = [];
        foreach ($finalsRows as $row) {
            $r1 = $round1[$row['candidate']['id']] ?? 0.0;
            $rows[] = [
                'candidate' => $row['candidate'],
                'round1' => round($r1, 2),
                'finals' => round($row['raw_total'], 2),
                'total' => round($r1 * $round1Weight / 100 + $row['raw_total'] * $finalsWeight / 100, 2),
                'rank' => 0,
            ];
        }

        return self::rank($rows);
    }

    /**
     * Highest total first (stable); equal totals share a rank and the next rank
     * skips: 1, 2, 2, 4.
     */
    public static function rank(array $rows): array
    {
        usort($rows, fn ($a, $b) => $b['total'] <=> $a['total']);

        $rank = 1;
        $lastTotal = null;

        foreach ($rows as $index => &$row) {
            if ($lastTotal === null || $row['total'] !== $lastTotal) {
                $rank = $index + 1;
                $lastTotal = $row['total'];
            }
            $row['rank'] = $rank;
        }

        return $rows;
    }
}
