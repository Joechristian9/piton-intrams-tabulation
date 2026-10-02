<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup';

    protected $description = 'Save a consistent copy of the SQLite database to database/backups';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->error('db:backup only supports the SQLite database.');

            return self::FAILURE;
        }

        $directory = database_path('backups');
        File::ensureDirectoryExists($directory);

        $path = $directory . DIRECTORY_SEPARATOR . 'piton-' . now()->format('Y-m-d_His') . '.sqlite';

        // VACUUM INTO writes a complete, consistent copy — including changes still
        // in the WAL file — without stopping the app. A plain file copy may miss them.
        DB::statement('VACUUM INTO ?', [$path]);

        $this->info('Backup saved: ' . $path . ' (' . number_format(filesize($path) / 1024) . ' KB)');

        return self::SUCCESS;
    }
}
