<?php

namespace Tests\Feature\MultiEvent;

use Illuminate\Support\Facades\DB;

/**
 * Fills the old (pre-multi-event) tables with realistic, messy data: decimal
 * scores, a missing cell, a tie, an admin row, finalists set out of number
 * order, and finals scores. Uses DB::table so it keeps working after the old
 * models are deleted.
 */
trait SeedsLegacyData
{
    /** @return array{judges: array<int,int>, female: array<int,int>, male: array<int,int>} ids */
    protected function seedLegacy(bool $tieAtCutoff = false, bool $withAdminRow = true): array
    {
        $now = now();
        $user = fn ($name, $role) => DB::table('users')->insertGetId([
            'name' => $name, 'email' => "{$name}@example.test", 'password' => bcrypt('password'),
            'role' => $role, 'email_verified_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $admin = $user('admin', 'admin');
        $judges = [$user('judge_1', 'judge'), $user('judge_2', 'judge'), $user('judge_3', 'judge')];

        $candidate = fn ($gender, $number) => DB::table('candidates')->insertGetId([
            'candidate_number' => $number, 'profile_img' => "candidates/{$gender}/{$number}.JPEG",
            'first_name' => ucfirst($gender), 'last_name' => (string) $number, 'course' => 'BSIT',
            'gender' => $gender, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $female = [$candidate('female', 1), $candidate('female', 2), $candidate('female', 3), $candidate('female', 4)];
        $male = [$candidate('male', 1), $candidate('male', 2), $candidate('male', 3)];

        // [candidate, judge, production 10, casual 25, swim 25, formal 25, interview 15]
        $selection = [
            [$female[0], $judges[0], 9.5, 22.25, 20, 23, 14],
            [$female[0], $judges[1], 8, 21, 19.5, null, 13],      // missing formal_wear
            [$female[0], $judges[2], 10, 24, 22, 24.5, 15],
            [$female[1], $judges[0], 7, 18, 17.5, 20, 11],
            [$female[1], $judges[1], 9, 23, 24, 22, 14.5],
            [$female[2], $judges[0], 7, 18, 17.5, 20, 11],        // ties female[1] on judge 1 rows
            [$female[2], $judges[1], 9, 23, 24, 22, 14.5],
            [$female[3], $judges[2], 6.25, 15, 16, 17, 10],
            [$male[0], $judges[0], 8, 20, 21, 22, 12],
            [$male[0], $judges[2], 9, 22, 23, 21.75, 13],
            [$male[1], $judges[1], 10, 25, 25, 25, 15],
            [$male[2], $admin, 10, 25, 25, 25, 15],               // admin row (pre-lockdown data)
        ];
        if (! $withAdminRow) {
            array_pop($selection);
        }
        if ($tieAtCutoff) {
            // Make female[3] tie female[1] and female[2] exactly.
            $selection[7] = [$female[3], $judges[0], 7, 18, 17.5, 20, 11];
            $selection[] = [$female[3], $judges[1], 9, 23, 24, 22, 14.5];
        }
        foreach ($selection as [$c, $j, $pn, $cw, $sw, $fw, $cdi]) {
            DB::table('top_five_selection_scores')->insert([
                'candidate_id' => $c, 'judge_id' => $j, 'production_number' => $pn, 'casual_wear' => $cw,
                'swim_wear' => $sw, 'formal_wear' => $fw, 'closed_door_interview' => $cdi,
                'total_scores' => ($pn ?? 0) + ($cw ?? 0) + ($sw ?? 0) + ($fw ?? 0) + ($cdi ?? 0),
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // Finalists set out of number order: female 2 before female 1.
        $finalist = fn ($c) => DB::table('top_five_candidates')->insertGetId(['candidate_id' => $c, 'created_at' => $now, 'updated_at' => $now]);
        $f2 = $finalist($female[1]);
        $f1 = $finalist($female[0]);
        $m1 = $finalist($male[0]);

        // [top_five_id, judge, face_and_figure 50, delivery 40, overall_appeal 10]
        foreach ([
            [$f2, $judges[0], 45, 35.5, 9], [$f2, $judges[1], 40, 30, null],
            [$f1, $judges[0], 47.25, 38, 10], [$f1, $judges[2], 44, 36, 8.5],
            [$m1, $judges[1], 42, 33, 7],
        ] as [$t, $j, $ff, $d, $oa]) {
            DB::table('top_five_scores')->insert([
                'top_five_id' => $t, 'judge_id' => $j, 'face_and_figure' => $ff, 'delivery' => $d,
                'overall_appeal' => $oa, 'total_score' => ($ff ?? 0) + ($d ?? 0) + ($oa ?? 0),
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        return ['judges' => $judges, 'female' => $female, 'male' => $male];
    }
}
