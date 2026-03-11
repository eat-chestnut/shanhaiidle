<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('story_boss_drops', function (Blueprint $table): void {
            $table->id();
            $table->string('boss_drop_id', 160)->unique();
            $table->string('boss_id', 120);
            $table->string('drop_type', 64);
            $table->string('item_id', 120);
            $table->string('item_name', 255);
            $table->string('item_type', 64);
            $table->unsignedInteger('count_min')->default(1);
            $table->unsignedInteger('count_max')->default(1);
            $table->decimal('probability', 8, 4)->default(1);
            $table->boolean('first_clear_only')->default(false);
            $table->text('use_desc')->nullable();
            $table->string('icon_path', 255)->default('');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_boss_drops');
    }
};
