<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_dungeons', function (Blueprint $table): void {
            $table->id();
            $table->string('dungeon_id', 120)->unique();
            $table->string('title', 255);
            $table->string('display_name', 255);
            $table->string('dungeon_type', 64);
            $table->unsignedInteger('unlock_level')->default(1);
            $table->string('entry_cost_item_id', 160)->nullable();
            $table->unsignedInteger('entry_cost_count')->default(0);
            $table->unsignedInteger('daily_limit')->default(2);
            $table->boolean('sweep_enabled')->default(false);
            $table->string('icon', 255)->nullable();
            $table->text('summary')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->index(['dungeon_type', 'is_enabled']);
            $table->index(['sort_order', 'is_enabled']);
        });

        Schema::create('daily_dungeon_levels', function (Blueprint $table): void {
            $table->id();
            $table->string('dungeon_level_id', 160)->unique();
            $table->string('dungeon_id', 120);
            $table->unsignedTinyInteger('level_no');
            $table->string('level_name', 64);
            $table->unsignedInteger('recommended_level')->nullable();
            $table->unsignedInteger('recommended_power')->nullable();
            $table->boolean('is_max_level')->default(false);
            $table->text('summary')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->unique(['dungeon_id', 'level_no']);
            $table->foreign('dungeon_id')
                ->references('dungeon_id')
                ->on('daily_dungeons')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->index(['dungeon_id', 'sort_order']);
        });

        Schema::create('daily_dungeon_level_monsters', function (Blueprint $table): void {
            $table->id();
            $table->string('dungeon_level_id', 160);
            $table->string('monster_id', 160);
            $table->string('spawn_type', 32);
            $table->unsignedInteger('weight')->default(100);
            $table->unsignedInteger('min_count')->default(1);
            $table->unsignedInteger('max_count')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->foreign('dungeon_level_id')
                ->references('dungeon_level_id')
                ->on('daily_dungeon_levels')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreign('monster_id')
                ->references('monster_id')
                ->on('monsters')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->index(['dungeon_level_id', 'spawn_type', 'sort_order'], 'dd_level_monsters_level_spawn_sort_idx');
        });

        Schema::create('daily_dungeon_upgrade_costs', function (Blueprint $table): void {
            $table->id();
            $table->string('dungeon_level_id', 160);
            $table->unsignedTinyInteger('target_level_no');
            $table->string('item_id', 160);
            $table->unsignedInteger('count')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->foreign('dungeon_level_id')
                ->references('dungeon_level_id')
                ->on('daily_dungeon_levels')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->index(['dungeon_level_id', 'target_level_no', 'sort_order'], 'dd_upgrade_costs_level_target_sort_idx');
        });

        Schema::create('daily_dungeon_first_clear_rewards', function (Blueprint $table): void {
            $table->id();
            $table->string('dungeon_level_id', 160);
            $table->string('item_id', 160);
            $table->unsignedInteger('count')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->foreign('dungeon_level_id')
                ->references('dungeon_level_id')
                ->on('daily_dungeon_levels')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->index(['dungeon_level_id', 'sort_order'], 'dd_first_clear_rewards_level_sort_idx');
        });

        Schema::dropIfExists('material_dungeon_drop_groups');
        Schema::dropIfExists('material_dungeons');
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_dungeon_first_clear_rewards');
        Schema::dropIfExists('daily_dungeon_upgrade_costs');
        Schema::dropIfExists('daily_dungeon_level_monsters');
        Schema::dropIfExists('daily_dungeon_levels');
        Schema::dropIfExists('daily_dungeons');
    }
};
