<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

// No RefreshDatabase here: SQLite can't run VACUUM INTO inside the
// transaction that trait wraps each test in.
class DatabaseBackupTest extends TestCase
{
    public function test_backup_command_writes_a_copy(): void
    {
        $before = File::glob(database_path('backups/piton-*.sqlite'));

        $this->artisan('db:backup')->assertSuccessful();

        $created = array_values(array_diff(File::glob(database_path('backups/piton-*.sqlite')), $before));
        $this->assertCount(1, $created);
        $this->assertGreaterThan(0, filesize($created[0]));

        File::delete($created);   // don't leave test backups behind
    }
}
