<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Seats stay fixed during a match, so the host is stored rather than assumed to be seat 0.
    public function up(): void
    {
        Schema::table('rooms', fn (Blueprint $table) => $table->unsignedTinyInteger('host')->default(0)->after('members'));
    }

    public function down(): void
    {
        Schema::table('rooms', fn (Blueprint $table) => $table->dropColumn('host'));
    }
};
