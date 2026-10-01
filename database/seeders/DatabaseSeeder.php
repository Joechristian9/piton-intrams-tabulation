<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\CandidateSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
public function run(): void
    {
        // Create 1 admin
        User::factory()->create([
            'name' => 'Darryl',
            'email' => 'darryl@gmail.com',
            'password' => Hash::make('123'),
            'role' => 'admin',
        ]);

        // Create 5 judges
        User::factory()->count(5)->sequence(
            fn($sequence) => [
                'name' => 'judge_' . ($sequence->index + 1),
                'email' => 'judge' . ($sequence->index + 1) . '@gmail.com',
                'password' => Hash::make('123'),
                'role' => 'judge',
            ]
        )->create();

        $this->call(CandidateSeeder::class);

        // $this->call(TopFiveSelectionScoreSeeder::class);
    }

}