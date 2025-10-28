<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('achievement_steps', function (Blueprint $table) {
            $table->unsignedInteger('points')->default(0)->after('threshold');
        });

        Schema::table('achievements', function (Blueprint $table) {
            $table->unsignedInteger('event_points')->default(0)->after('is_tiered');
        });

        Schema::create('levels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('min_points')->default(0);
            $table->string('name');
            $table->unsignedInteger('sort_index')->default(0);
            $table->timestamps();
            $table->unique('min_points');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('total_achievement_points')->default(0);
            $table->foreignId('current_level_id')->nullable()->constrained('levels')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_level_id');
            $table->dropColumn('total_achievement_points');
        });
        Schema::dropIfExists('levels');
        Schema::table('achievements', function (Blueprint $table) {
            $table->dropColumn('event_points');
        });
        Schema::table('achievement_steps', function (Blueprint $table) {
            $table->dropColumn('points');
        });
    }
};
