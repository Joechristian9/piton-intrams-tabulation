<?php

namespace Database\Seeders;

use App\Legacy\LegacyResults;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventGroup;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * A fresh install: one admin and Event #1 (the PITON pageant) with its groups,
 * categories, judges and candidates. Never run against the live database.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Darryl',
            'email' => 'darryl@gmail.com',
            'password' => Hash::make('123'),
            'role' => 'admin',
        ]);

        $event = Event::create([
            'name' => 'PITON Pageant',
            'code' => 'piton',
            'status' => Event::LIVE,
            'rounds' => 2,
            'finalists_per_group' => 3,
            'finals_from_zero' => true,
        ]);
        $event->forceFill(['started_at' => now()])->save();

        EventGroup::create(['event_id' => $event->id, 'name' => 'Female', 'position' => 1]);
        EventGroup::create(['event_id' => $event->id, 'name' => 'Male', 'position' => 2]);

        foreach (LegacyResults::CATEGORY_MAP as $meta) {
            Category::create([
                'event_id' => $event->id, 'round' => $meta['round'], 'name' => $meta['name'],
                'max_score' => $meta['max'], 'position' => $meta['position'],
            ]);
        }

        // 5 judges: log in as piton-judge1 … piton-judge5 (or judge1@gmail.com …), password 123.
        User::factory()->count(5)->sequence(fn ($sequence) => [
            'name' => 'judge_' . ($sequence->index + 1),
            'email' => 'judge' . ($sequence->index + 1) . '@gmail.com',
            'username' => 'piton-judge' . ($sequence->index + 1),
            'password' => Hash::make('123'),
            'role' => 'judge',
            'event_id' => $event->id,
        ])->create();

        $this->call(CandidateSeeder::class);
    }
}
