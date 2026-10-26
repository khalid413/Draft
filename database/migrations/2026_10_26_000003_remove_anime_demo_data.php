<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            // Demo games also contain frozen copies of demo cards.
            DB::table('games')->where('domain', 'anime')->delete();
            DB::table('cards')->where('domain', 'anime')->delete();
        });
    }

    public function down(): void
    {
        // Intentionally do not recreate removed demo content.
    }
};
