<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the single-pageant tables once Event #1 is verified.
 *
 * Lives in database/migrations/pending/ so `php artisan migrate` never runs it by
 * accident. Only with the organizer's go-ahead, between events:
 *   1. php artisan db:backup
 *   2. php artisan migrate --path=database/migrations/pending
 * There is no way back except restoring that backup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('top_five_scores');
        Schema::dropIfExists('top_five_selection_scores');
        Schema::dropIfExists('top_five_candidates');

        if (Schema::hasColumn('candidates', 'gender')) {
            Schema::table('candidates', function (Blueprint $table) {
                $table->dropColumn('gender');
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('The old score tables are gone; restore them from a db:backup copy.');
    }
};
