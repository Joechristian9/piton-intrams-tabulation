<?php

namespace App\Console\Commands;

use App\Legacy\LegacyImporter;
use App\Legacy\LegacyResults;
use App\Legacy\Snapshot;
use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * One-time move of the original pageant into Event #1. Backs up first, then
 * imports inside a transaction and keeps the result only if every results page
 * (each judge's scores, averages, totals and ranks) matches the old app exactly.
 */
class MigrateLegacyEvent extends Command
{
    protected $signature = 'events:migrate-legacy';

    protected $description = 'Move the original pageant into Event #1, verifying identical results';

    public function handle(LegacyImporter $importer): int
    {
        if (Event::exists()) {
            $this->error('Event #1 already exists. Nothing was changed.');

            return self::FAILURE;
        }

        $nonJudgeRows = $this->nonJudgeScoreRows();
        if ($nonJudgeRows > 0) {
            $this->error("{$nonJudgeRows} old score row(s) were entered by accounts that are not judges. "
                . 'The old app counted them in round totals but not on category pages, so results '
                . "can't be reproduced. Remove those rows (after a backup) and run this again. Nothing was changed.");

            return self::FAILURE;
        }

        if (Artisan::call('db:backup') !== self::SUCCESS) {
            $this->error('The backup failed, so nothing was changed: ' . trim(Artisan::output()));

            return self::FAILURE;
        }
        $this->line(trim(Artisan::output()));

        $legacy = Snapshot::normalize(LegacyResults::snapshot());
        $differences = [];

        try {
            $event = DB::transaction(function () use ($importer, $legacy, &$differences) {
                $event = $importer->import();
                $differences = Snapshot::diff($legacy, Snapshot::normalize($importer->newSnapshot($event)));

                if ($differences !== []) {
                    throw new RuntimeException('results differ');
                }

                return $event;
            });
        } catch (RuntimeException $e) {
            if ($differences === []) {
                throw $e;
            }
            $this->error('The new results differ from the old ones:');
            foreach ($differences as $line) {
                $this->line("  {$line}");
            }
            $this->error('Nothing was changed.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Event #1 migrated: %d candidates, %d judges, %d scores — results identical.',
            $event->candidates()->count(),
            $event->judges()->count(),
            DB::table('scores')->count(),
        ));

        return self::SUCCESS;
    }

    private function nonJudgeScoreRows(): int
    {
        $count = 0;
        foreach (['top_five_selection_scores', 'top_five_scores'] as $table) {
            $count += DB::table($table)
                ->leftJoin('users', 'users.id', '=', "{$table}.judge_id")
                ->where(fn ($q) => $q->whereNull('users.id')->orWhere('users.role', '!=', 'judge'))
                ->count();
        }

        return $count;
    }
}
