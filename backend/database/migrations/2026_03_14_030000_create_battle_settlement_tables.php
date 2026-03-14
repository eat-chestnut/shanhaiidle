<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('battle_results', function (Blueprint $table): void {
            $table->id();
            $table->string('battle_id', 160)->unique();
            $table->string('player_id', 64);
            $table->string('battle_type', 64);
            $table->string('stage_id', 160)->nullable();
            $table->string('difficulty_id', 160)->nullable();
            $table->enum('battle_result', ['victory', 'defeat']);
            $table->unsignedInteger('elapsed_ticks')->default(0);
            $table->unsignedInteger('remaining_player_hp')->default(0);
            $table->unsignedInteger('remaining_enemy_count')->default(0);
            $table->unsignedInteger('cleared_wave_count')->default(0);
            $table->boolean('is_settled')->default(false);
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['player_id', 'battle_type']);
            $table->index(['player_id', 'stage_id', 'difficulty_id']);
        });

        Schema::create('player_main_stage_first_clear_claims', function (Blueprint $table): void {
            $table->id();
            $table->string('player_id', 64);
            $table->string('stage_id', 160);
            $table->string('difficulty_id', 160);
            $table->unsignedBigInteger('battle_result_id')->nullable();
            $table->timestamp('claimed_at');
            $table->timestamps();

            $table->unique(['player_id', 'difficulty_id'], 'player_stage_first_clear_unique');
            $table->index(['player_id', 'stage_id']);
            $table->foreign('battle_result_id')->references('id')->on('battle_results')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_main_stage_first_clear_claims');
        Schema::dropIfExists('battle_results');
    }
};
