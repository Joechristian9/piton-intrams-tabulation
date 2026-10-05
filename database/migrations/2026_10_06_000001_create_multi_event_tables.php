<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-event schema (docs/superpowers/specs/2026-10-05-multi-event-design.md §3).
 * The old score tables stay untouched; `php artisan events:migrate-legacy` copies
 * their data into these tables as Event #1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('status')->default('setup'); // setup | live | closed
            $table->unsignedTinyInteger('rounds')->default(2);
            $table->unsignedSmallInteger('finalists_per_group')->nullable();
            $table->boolean('finals_from_zero')->default(true);
            $table->unsignedTinyInteger('round1_weight')->nullable();
            $table->unsignedTinyInteger('finals_weight')->nullable();
            $table->timestamp('finalists_set_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('event_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('position')->default(1);
            $table->timestamps();
            $table->unique(['event_id', 'name']);
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('round')->default(1);
            $table->string('name');
            $table->decimal('max_score', 5, 2);
            $table->unsignedSmallInteger('position')->default(1);
            $table->timestamps();
        });

        Schema::table('candidates', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('event_groups')->cascadeOnDelete();
            // New-event candidates belong to a group instead of a gender, and a course
            // label is optional.
            $table->string('gender')->nullable()->change();
            $table->string('course')->nullable()->change();
            $table->unique(['group_id', 'candidate_number']); // each group numbers from 1
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('username')->nullable()->unique();
            $table->text('password_plain_encrypted')->nullable();
        });

        Schema::create('finalists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['event_id', 'candidate_id']);
        });

        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('judge_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('score', 5, 2);
            $table->timestamps();
            $table->unique(['category_id', 'candidate_id', 'judge_id']);
            $table->index('judge_id');
            $table->index('candidate_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scores');
        Schema::dropIfExists('finalists');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_id');
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'password_plain_encrypted']);
        });

        Schema::table('candidates', function (Blueprint $table) {
            $table->dropUnique(['group_id', 'candidate_number']);
            $table->dropConstrainedForeignId('group_id');
            $table->dropConstrainedForeignId('event_id');
        });

        Schema::dropIfExists('categories');
        Schema::dropIfExists('event_groups');
        Schema::dropIfExists('events');
    }
};
