<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->unsignedSmallInteger('power')->change();
            $table->string('position', 4)->nullable()->index();
            $table->boolean('is_icon')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropIndex(['position']);
            $table->dropColumn(['position', 'is_icon']);
            // Keep the wider power column to avoid truncating existing anime cards.
        });
    }
};
