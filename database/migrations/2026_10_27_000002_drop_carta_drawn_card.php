<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Drawing now always ends the turn, so no drawn card waits to be played.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carta_games', fn (Blueprint $table) => $table->dropColumn('drawn_card'));
    }

    public function down(): void
    {
        Schema::table('carta_games', fn (Blueprint $table) => $table->string('drawn_card', 12)->nullable()->after('pending_draw'));
    }
};
