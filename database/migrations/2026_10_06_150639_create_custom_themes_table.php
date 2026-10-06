<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Color themes an admin made: two colors, from which every shade is worked out
// (App\Support\ThemeColors). Picked like a preset, as theme key "custom-{id}".
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_themes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique();
            $table->string('accent', 7);    // #rrggbb: buttons, highlights
            $table->string('surface', 7);   // #rrggbb: page background
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_themes');
    }
};
