<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The +4 challenge is gone, and stacking becomes a three-way rule: off, same (+2 on +2, +4 on +4) or mixed.
    public function up(): void
    {
        Schema::table('uno_games', fn (Blueprint $table) => $table->string('stacking_rule', 8)->default('off')->after('stacking'));
        DB::table('uno_games')->where('stacking', true)->update(['stacking_rule' => 'same']);
        Schema::table('uno_games', fn (Blueprint $table) => $table->dropColumn(['stacking', 'challenge']));
        Schema::table('uno_games', fn (Blueprint $table) => $table->renameColumn('stacking_rule', 'stacking'));
    }

    public function down(): void
    {
        Schema::table('uno_games', function (Blueprint $table) {
            $table->renameColumn('stacking', 'stacking_rule');
            $table->json('challenge')->nullable();
        });
        Schema::table('uno_games', fn (Blueprint $table) => $table->boolean('stacking')->default(false));
        DB::table('uno_games')->where('stacking_rule', '!=', 'off')->update(['stacking' => true]);
        Schema::table('uno_games', fn (Blueprint $table) => $table->dropColumn('stacking_rule'));
    }
};
