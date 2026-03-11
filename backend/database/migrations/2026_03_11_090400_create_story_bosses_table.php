<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('story_bosses', function (Blueprint $table): void {
            $table->id();
            $table->string('boss_id', 120)->unique();
            $table->string('boss_name', 255);
            $table->text('source_text')->nullable();
            $table->string('map_id', 120)->default('');
            $table->string('chapter_id', 120)->default('');
            $table->string('boss_type', 64);
            $table->unsignedInteger('recommend_level')->default(1);
            $table->unsignedInteger('recommend_power')->default(0);
            $table->text('lore_role')->nullable();
            $table->json('visual_tags')->nullable();
            $table->json('combat_tags')->nullable();
            $table->longText('intro_copy')->nullable();
            $table->longText('clear_copy')->nullable();
            $table->string('icon_path', 255)->default('');
            $table->string('portrait_path', 255)->default('');
            $table->string('banner_path', 255)->default('');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_bosses');
    }
};
