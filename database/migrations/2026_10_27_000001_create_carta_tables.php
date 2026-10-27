<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rooms, seats, lobby and Reverb channels are shared; game_type routes a room to its game.
        Schema::table('rooms', fn (Blueprint $table) => $table->string('game_type', 20)->default('draft')->after('code')->index());

        Schema::create('carta_games', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->string('status', 12)->default('WAITING');      // WAITING | PLAYING | FINISHED | CANCELLED
            $table->unsignedInteger('version')->default(0);
            $table->unsignedInteger('turn_number')->default(0);
            $table->unsignedTinyInteger('current_seat')->nullable();
            $table->string('active_suit', 8)->nullable();
            $table->string('pending_effect', 10)->default('NONE');  // NONE | SKIP | DRAW_TWO
            $table->unsignedTinyInteger('pending_draw')->default(0);
            $table->string('drawn_card', 12)->nullable();          // card drawn this turn that may still be played
            $table->json('draw_pile');                              // ordered card ids, last = next to draw
            $table->json('discard_pile');                           // ordered card ids, last = top card
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('carta_players', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('carta_game_id')->constrained('carta_games')->cascadeOnDelete();
            $table->unsignedTinyInteger('seat');
            $table->string('name', 20);
            $table->json('hand');
            $table->string('status', 10)->default('ACTIVE');       // ACTIVE | FINISHED | LOSER
            $table->unsignedTinyInteger('finish_rank')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->unique(['carta_game_id', 'seat']);
            $table->unique(['carta_game_id', 'finish_rank']);      // ranks can never repeat within a match
        });

        Schema::create('carta_moves', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('carta_game_id')->constrained('carta_games')->cascadeOnDelete();
            $table->uuid('action_id')->nullable();                 // client retry key: one action applies once
            $table->unsignedInteger('turn_number');
            $table->unsignedTinyInteger('seat')->nullable();
            $table->string('type', 16);                            // deal | play | draw | accept_skip | accept_draw | pass | finish | lose
            $table->string('card', 12)->nullable();
            $table->string('suit', 8)->nullable();
            $table->unsignedTinyInteger('amount')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['carta_game_id', 'action_id']);
            $table->index(['carta_game_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carta_moves');
        Schema::dropIfExists('carta_players');
        Schema::dropIfExists('carta_games');
        Schema::table('rooms', fn (Blueprint $table) => $table->dropColumn('game_type'));
    }
};
