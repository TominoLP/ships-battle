<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('player_game_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->enum('result', ['win', 'loss'])->index();
            $table->unsignedInteger('ships_destroyed')->default(0);
            $table->unsignedInteger('ships_lost')->default(0);
            $table->unsignedInteger('shots_fired')->default(0);
            $table->unsignedInteger('hits')->default(0);
            $table->unsignedInteger('abilities_used')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'game_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_game_histories');
    }
};
