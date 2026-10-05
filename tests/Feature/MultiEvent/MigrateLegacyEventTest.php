<?php

namespace Tests\Feature\MultiEvent;

use App\Legacy\LegacyImporter;
use App\Legacy\LegacyResults;
use App\Legacy\Snapshot;
use App\Models\Event;
use App\Models\Finalist;
use App\Models\Score;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MigrateLegacyEventTest extends TestCase
{
    use RefreshDatabase, SeedsLegacyData;

    private array $backupsBefore = [];

    /**
     * The command runs db:backup (VACUUM), which SQLite refuses inside the
     * transaction RefreshDatabase normally wraps each test in. So no wrapping
     * transaction here; the shared in-memory schema is reset around each test.
     */
    protected function connectionsToTransact(): array
    {
        return [];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
        $this->backupsBefore = File::glob(database_path('backups/*.sqlite'));
    }

    protected function tearDown(): void
    {
        // The command writes a real backup file; remove the ones this test made.
        foreach (array_diff(File::glob(database_path('backups/*.sqlite')), $this->backupsBefore) as $file) {
            File::delete($file);
        }
        $this->artisan('migrate:fresh');
        parent::tearDown();
    }

    private function nonNullLegacyCells(): int
    {
        $count = 0;
        foreach (DB::table('top_five_selection_scores')->get() as $row) {
            foreach (['production_number', 'casual_wear', 'swim_wear', 'formal_wear', 'closed_door_interview'] as $c) {
                $count += $row->{$c} !== null ? 1 : 0;
            }
        }
        foreach (DB::table('top_five_scores')->get() as $row) {
            foreach (['face_and_figure', 'delivery', 'overall_appeal'] as $c) {
                $count += $row->{$c} !== null ? 1 : 0;
            }
        }

        return $count;
    }

    public function test_migrates_messy_legacy_data_with_identical_results(): void
    {
        $ids = $this->seedLegacy(tieAtCutoff: true, withAdminRow: false);
        $legacy = Snapshot::normalize(LegacyResults::snapshot());

        $this->artisan('events:migrate-legacy')
            ->expectsOutputToContain('results identical')
            ->assertSuccessful();

        $event = Event::sole();
        $this->assertSame(['PITON Pageant', 'piton', Event::LIVE, 2, 3, true], [
            $event->name, $event->code, $event->status, $event->rounds, $event->finalists_per_group, $event->finals_from_zero,
        ]);
        $this->assertSame(['Female', 'Male'], $event->groups->pluck('name')->all());
        $this->assertSame(
            ['Production Number', 'Sports Wear', 'Swim Wear', 'Formal Wear', 'Casual Interview', 'Beauty of the Face and Figure', 'Delivery', 'Over-all Appeal / X-factor'],
            $event->categories->pluck('name')->all()
        );
        $this->assertSame($this->nonNullLegacyCells(), Score::count());
        $this->assertSame([$ids['female'][1], $ids['female'][0], $ids['male'][0]], Finalist::orderBy('id')->pluck('candidate_id')->all());
        $this->assertTrue($event->finalistsSet());
        $this->assertSame(['piton-judge1', 'piton-judge2', 'piton-judge3'], User::whereIn('id', $ids['judges'])->orderBy('id')->pluck('username')->all());
        $this->assertSame(0, DB::table('candidates')->whereNull('event_id')->count());

        $this->assertSame([], Snapshot::diff($legacy, Snapshot::normalize(app(LegacyImporter::class)->newSnapshot($event))));
    }

    public function test_refuses_to_run_twice(): void
    {
        $this->seedLegacy(withAdminRow: false);
        $this->artisan('events:migrate-legacy')->assertSuccessful();

        $this->artisan('events:migrate-legacy')
            ->expectsOutputToContain('Event #1 already exists.')
            ->assertFailed();
        $this->assertSame(1, Event::count());
    }

    public function test_a_mismatch_rolls_everything_back(): void
    {
        $this->seedLegacy(withAdminRow: false);
        $this->app->bind(LegacyImporter::class, fn () => new class extends LegacyImporter
        {
            public function newSnapshot(Event $event): array
            {
                $snapshot = parent::newSnapshot($event);
                $snapshot['round1']['female'][0]['total'] += 0.01;

                return $snapshot;
            }
        });

        $this->artisan('events:migrate-legacy')
            ->expectsOutputToContain('round1.female[0].total')
            ->expectsOutputToContain('Nothing was changed.')
            ->assertFailed();

        $this->assertSame(0, Event::count());
        $this->assertSame(0, Score::count());
        $this->assertSame(0, User::whereNotNull('event_id')->count());
        $this->assertSame(0, DB::table('candidates')->whereNotNull('event_id')->count());
    }

    public function test_refuses_when_non_judge_accounts_entered_scores(): void
    {
        $this->seedLegacy(withAdminRow: true);

        $this->artisan('events:migrate-legacy')
            ->expectsOutputToContain('entered by accounts that are not judges')
            ->assertFailed();

        $this->assertSame(0, Event::count());
    }

    /** Review Focus 5: a tie stored in the opposite order to candidate numbers. */
    public function test_tied_rows_stored_in_a_different_order_still_match(): void
    {
        $ids = $this->seedLegacy(tieAtCutoff: true, withAdminRow: false);
        // Swap numbers so id order and number order disagree for the tied pair.
        DB::table('candidates')->where('id', $ids['female'][1])->update(['candidate_number' => 3]);
        DB::table('candidates')->where('id', $ids['female'][2])->update(['candidate_number' => 2]);

        $this->artisan('events:migrate-legacy')->assertSuccessful();
    }

    public function test_migrates_an_event_with_no_scores_yet(): void
    {
        DB::table('candidates')->insert([
            'candidate_number' => 1, 'profile_img' => 'candidates/female/1.JPEG', 'first_name' => 'A',
            'last_name' => 'B', 'course' => 'BSIT', 'gender' => 'female', 'created_at' => now(), 'updated_at' => now(),
        ]);
        User::factory()->create(['role' => 'judge']);

        $this->artisan('events:migrate-legacy')->assertSuccessful();

        $this->assertFalse(Event::sole()->finalistsSet());
    }
}
