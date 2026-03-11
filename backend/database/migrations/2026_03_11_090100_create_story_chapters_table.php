<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('story_chapters', function (Blueprint $table): void {
            $table->id();
            $table->string('chapter_id', 120)->unique();
            $table->string('volume_name', 120)->default('');
            $table->integer('chapter_no')->default(0);
            $table->string('chapter_name', 255);
            $table->string('chapter_role', 64);
            $table->string('map_id', 120)->nullable();
            $table->unsignedInteger('level_min')->default(1);
            $table->unsignedInteger('level_max')->default(1);
            $table->longText('intro_copy')->nullable();
            $table->longText('objective_copy')->nullable();
            $table->longText('boss_intro_copy')->nullable();
            $table->string('boss_id', 120)->nullable();
            $table->longText('clear_copy')->nullable();
            $table->longText('next_hook_copy')->nullable();
            $table->string('icon_path', 255)->default('');
            $table->string('banner_path', 255)->default('');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_chapters');
    }
};
