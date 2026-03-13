<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('main_stage_chapters', function (Blueprint $table): void {
            $table->string('chapter_id', 120)->primary();
            $table->string('chapter_name', 255);
            $table->string('chapter_type', 32);
            $table->string('chapter_flow_type', 32);
            $table->boolean('is_functional_chapter')->default(false);
            $table->boolean('has_combat')->default(false);
            $table->boolean('has_sect_selection')->default(false);
            $table->boolean('has_shanshen_ritual')->default(false);
            $table->unsignedInteger('suggested_level_min')->default(1);
            $table->unsignedInteger('suggested_level_max')->default(1);
            $table->unsignedInteger('suggested_power')->default(0);
            $table->string('mountain_name', 255)->default('');
            $table->string('boss_display_name', 255)->default('');
            $table->unsignedInteger('unlock_level')->default(1);
            $table->string('unlock_prev_chapter_id', 120)->nullable();
            $table->boolean('sect_selection_enabled')->default(false);
            $table->string('sect_selection_pool_id', 120)->nullable();
            $table->boolean('shanshen_ritual_enabled')->default(false);
            $table->string('next_version_teaser_title', 255)->nullable();
            $table->string('next_world_key', 120)->nullable();
            $table->text('teaser_desc')->nullable();
            $table->text('remark')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('main_stage_chapters');
    }
};
