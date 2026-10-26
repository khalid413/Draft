<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('is_admin')->default(false));
        Schema::create('rooms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 8)->unique();
            $table->string('status')->default('lobby');
            $table->json('settings');
            $table->json('members');
            $table->unsignedInteger('version')->default(0);
            $table->uuid('game_uuid')->nullable()->unique();
            $table->json('playback')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_admin'));
    }
};
