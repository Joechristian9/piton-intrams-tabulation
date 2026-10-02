<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Judge pages look scores up by judge, and the results pages by
     * finalist; SQLite doesn't index foreign keys automatically.
     */
    public function up(): void
    {
        Schema::table('top_five_selection_scores', function (Blueprint $table) {
            $table->index('judge_id');
        });

        Schema::table('top_five_scores', function (Blueprint $table) {
            $table->index(['judge_id', 'top_five_id']);
            $table->index('top_five_id');
        });
    }

    public function down(): void
    {
        Schema::table('top_five_selection_scores', function (Blueprint $table) {
            $table->dropIndex(['judge_id']);
        });

        Schema::table('top_five_scores', function (Blueprint $table) {
            $table->dropIndex(['judge_id', 'top_five_id']);
            $table->dropIndex(['top_five_id']);
        });
    }
};
