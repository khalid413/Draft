<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // UNO rooms reuse the shared rooms table (game_type "uno"); the variant and stacking choice live in rooms.settings
        // and are copied onto the match when it starts, so a running match can never change rules.
        Schema::create('uno_games', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->string('variant', 20);
            $table->boolean('stacking')->default(false);
            $table->string('status', 12)->default('WAITING');      // WAITING | PLAYING | FINISHED | CANCELLED
            $table->unsignedInteger('version')->default(0);
            $table->unsignedInteger('turn_number')->default(0);
            $table->unsignedTinyInteger('current_seat')->nullable();
            $table->tinyInteger('direction')->default(1);          // 1 clockwise, -1 after a Reverse
            $table->string('active_color', 8)->nullable();
            $table->string('pending_effect', 10)->default('NONE');  // NONE | DRAW_TWO | DRAW_FOUR
            $table->unsignedSmallInteger('pending_draw')->default(0);
            $table->string('drawn_card', 20)->nullable();          // card drawn this turn that may still be played
            $table->json('challenge')->nullable();                 // last +4: who played it, its target, whether it was legal
            $table->unsignedTinyInteger('uno_exposed')->nullable(); // seat on one card that forgot to call UNO
            $table->json('draw_pile');                              // ordered card ids, last = next to draw
            $table->json('discard_pile');                           // ordered card ids, last = top card
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('uno_players', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('uno_game_id')->constrained('uno_games')->cascadeOnDelete();
            $table->unsignedTinyInteger('seat');
            $table->string('name', 20);
            $table->json('hand');
            $table->string('status', 10)->default('ACTIVE');       // ACTIVE | FINISHED | LOSER
            $table->boolean('uno_called')->default(false);
            $table->unsignedTinyInteger('finish_rank')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->unique(['uno_game_id', 'seat']);
            $table->unique(['uno_game_id', 'finish_rank']);        // ranks can never repeat within a match
        });

        Schema::create('uno_moves', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('uno_game_id')->constrained('uno_games')->cascadeOnDelete();
            $table->uuid('action_id')->nullable();                 // client retry key: one action applies once
            $table->unsignedInteger('turn_number');
            $table->unsignedTinyInteger('seat')->nullable();
            $table->string('type', 16);                            // deal | play | draw | pass | accept_draw | challenge_won | challenge_lost | uno | caught | finish | lose | forfeit | reshuffle
            $table->string('card', 20)->nullable();
            $table->string('color', 8)->nullable();
            $table->unsignedTinyInteger('target')->nullable();
            $table->unsignedSmallInteger('amount')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['uno_game_id', 'action_id']);
            $table->index(['uno_game_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uno_moves');
        Schema::dropIfExists('uno_players');
        Schema::dropIfExists('uno_games');
    }
};
