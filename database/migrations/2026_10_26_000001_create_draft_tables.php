<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->index();
            $table->string('name');
            $table->string('series');
            $table->string('image_path');
            $table->unsignedTinyInteger('power');
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['domain', 'name']);
        });
        Schema::create('games', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('domain');
            $table->json('state');
            $table->string('status')->index();
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
        Schema::dropIfExists('cards');
    }
};
