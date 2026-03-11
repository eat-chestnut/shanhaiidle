<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('story_maps', function (Blueprint $table): void {
            $table->id();
            $table->string('map_id', 120)->unique();
            $table->string('map_name', 255);
            $table->unsignedInteger('map_order')->default(0);
            $table->string('map_type', 64);
            $table->string('volume_name', 120)->default('');
            $table->text('source_text')->nullable();
            $table->unsignedInteger('level_min')->default(1);
            $table->unsignedInteger('level_max')->default(1);
            $table->json('theme_tags')->nullable();
            $table->text('atmosphere_desc')->nullable();
            $table->unsignedInteger('recommend_power')->default(0);
            $table->text('unlock_condition')->nullable();
            $table->string('chapter_id', 120)->default('');
            $table->string('boss_id', 120)->default('');
            $table->string('normal_drop_pool', 120)->default('');
            $table->string('elite_drop_pool', 120)->default('');
            $table->string('boss_drop_pool', 120)->default('');
            $table->string('icon_path', 255)->default('');
            $table->string('banner_path', 255)->default('');
            $table->string('bg_path', 255)->default('');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_maps');
    }
};
