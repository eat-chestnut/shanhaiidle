<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('main_stage_difficulties', function (Blueprint $table): void {
            $table->string('difficulty_id', 160)->primary();
            $table->string('chapter_id', 120);
            $table->string('difficulty_code', 32);
            $table->string('difficulty_name', 64);
            $table->string('normal_monster_pool_id', 160)->default('');
            $table->string('elite_monster_pool_id', 160)->default('');
            $table->string('boss_id', 160)->default('');
            $table->string('drop_preview_group_id', 160)->default('');
            $table->string('first_clear_reward_group_id', 160)->default('');
            $table->text('remark')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['chapter_id', 'difficulty_code']);
            $table->foreign('chapter_id')->references('chapter_id')->on('main_stage_chapters')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('main_stage_difficulties');
    }
};
