<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('top_five_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('top_five_id')
                ->constrained('top_five_candidates', 'id')
                ->onDelete('cascade');
            $table->foreignId('judge_id')->constrained('users')->onDelete('cascade');
            $table->decimal('face_and_figure', 5, 2)->nullable();
            $table->decimal('delivery', 5, 2)->nullable();
            $table->decimal('overall_appeal', 5, 2)->nullable();
            $table->decimal('total_score', 5, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('top_five_scores');
    }
};
