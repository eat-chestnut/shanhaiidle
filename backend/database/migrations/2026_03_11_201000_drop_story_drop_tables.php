<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('story_map_drops');
        Schema::dropIfExists('story_boss_drops');
    }

    public function down(): void
    {
        if (! Schema::hasTable('story_map_drops')) {
            Schema::create('story_map_drops', function (Blueprint $table): void {
                $table->id();
                $table->string('drop_id')->unique();
                $table->string('map_id');
                $table->string('drop_tier');
                $table->string('item_id');
                $table->string('item_name');
                $table->string('item_type')->nullable();
                $table->unsignedInteger('count_min')->default(1);
                $table->unsignedInteger('count_max')->default(1);
                $table->decimal('probability', 5, 4)->default(1);
                $table->boolean('first_clear_only')->default(false);
                $table->string('source_desc')->nullable();
                $table->string('icon_path')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_enabled')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('story_boss_drops')) {
            Schema::create('story_boss_drops', function (Blueprint $table): void {
                $table->id();
                $table->string('drop_id')->unique();
                $table->string('boss_id');
                $table->string('drop_tier');
                $table->string('item_id');
                $table->string('item_name');
                $table->string('item_type')->nullable();
                $table->unsignedInteger('count_min')->default(1);
                $table->unsignedInteger('count_max')->default(1);
                $table->decimal('probability', 5, 4)->default(1);
                $table->boolean('first_clear_only')->default(false);
                $table->string('source_desc')->nullable();
                $table->string('icon_path')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_enabled')->default(true);
                $table->timestamps();
            });
        }
    }
};
