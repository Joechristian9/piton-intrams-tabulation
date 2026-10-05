<?php

namespace Tests\Feature\MultiEvent;

use App\Models\Event;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A fresh install (in-memory only — never seed the real database) gets Event #1. */
class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_builds_event_one(): void
    {
        $this->seed(DatabaseSeeder::class);

        $event = Event::sole();
        $this->assertSame(['PITON Pageant', 'piton', Event::LIVE, 2, 3], [$event->name, $event->code, $event->status, $event->rounds, $event->finalists_per_group]);
        $this->assertSame(['Female', 'Male'], $event->groups->pluck('name')->all());
        $this->assertSame(
            [[1, 'Production Number', 10.0], [1, 'Sports Wear', 25.0], [1, 'Swim Wear', 25.0], [1, 'Formal Wear', 25.0], [1, 'Casual Interview', 15.0],
                [2, 'Beauty of the Face and Figure', 50.0], [2, 'Delivery', 40.0], [2, 'Over-all Appeal / X-factor', 10.0]],
            $event->categories->map(fn ($c) => [$c->round, $c->name, $c->max_score])->all()
        );
        $this->assertSame([12, 10], $event->groups->map(fn ($g) => $g->candidates()->count())->all());
        $this->assertSame('candidates/female/1.JPEG', $event->groups[0]->candidates()->orderBy('candidate_number')->first()->profile_img);
        $this->assertSame(['piton-judge1', 'piton-judge2', 'piton-judge3', 'piton-judge4', 'piton-judge5'], $event->judges()->pluck('username')->all());
        $this->assertSame(1, User::where('role', 'admin')->count());
    }
}
