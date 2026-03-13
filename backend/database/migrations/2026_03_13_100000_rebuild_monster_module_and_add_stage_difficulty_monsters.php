<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('monster_skill_bindings');
        Schema::dropIfExists('monster_drop_bindings');
        Schema::dropIfExists('monster_boss_profiles');
        Schema::dropIfExists('stage_difficulty_monsters');
        Schema::dropIfExists('monsters');

        Schema::create('monsters', function (Blueprint $table): void {
            $table->id();
            $table->string('monster_id', 160)->unique();
            $table->string('monster_name', 255);
            $table->string('display_name', 255);
            $table->string('monster_type', 32);
            $table->string('chapter_id', 120);
            $table->string('display_stage_id', 120)->nullable();
            $table->string('family', 120)->nullable();
            $table->string('title', 120)->nullable();
            $table->text('desc')->nullable();
            $table->string('icon')->nullable();
            $table->string('sprite')->nullable();
            $table->string('prefab_key', 160)->nullable();
            $table->unsignedInteger('level')->default(1);
            $table->unsignedInteger('hp')->default(1);
            $table->unsignedInteger('atk')->default(0);
            $table->unsignedInteger('def')->default(0);
            $table->unsignedInteger('speed')->default(0);
            $table->unsignedInteger('move_speed')->default(0);
            $table->unsignedInteger('attack_range')->default(0);
            $table->decimal('attack_interval', 8, 2)->default(1.00);
            $table->unsignedInteger('aggro_range')->default(0);
            $table->string('ai_type', 64);
            $table->string('rarity_tag', 32)->default('common');
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('remark')->nullable();
            $table->timestamps();
        });

        Schema::create('monster_skill_bindings', function (Blueprint $table): void {
            $table->id();
            $table->string('monster_id', 160);
            $table->string('skill_id', 160);
            $table->string('slot_type', 32);
            $table->unsignedInteger('trigger_priority')->default(10);
            $table->string('phase_limit', 64)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->foreign('monster_id')->references('monster_id')->on('monsters')->cascadeOnDelete();
        });

        Schema::create('monster_drop_bindings', function (Blueprint $table): void {
            $table->id();
            $table->string('monster_id', 160);
            $table->string('drop_group_id', 160);
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->foreign('monster_id')->references('monster_id')->on('monsters')->cascadeOnDelete();
        });

        Schema::create('monster_boss_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('monster_id', 160)->unique();
            $table->unsignedInteger('phase_count')->default(1);
            $table->json('phase_rules')->nullable();
            $table->json('summon_rules')->nullable();
            $table->json('rage_rules')->nullable();
            $table->json('weak_point_rules')->nullable();
            $table->text('intro_text')->nullable();
            $table->string('battle_bgm_id', 160)->nullable();
            $table->string('camera_rule', 120)->nullable();
            $table->string('entry_fx_key', 160)->nullable();
            $table->string('death_fx_key', 160)->nullable();
            $table->string('first_clear_reward_group_id', 160)->nullable();
            $table->string('story_flag_on_clear', 160)->nullable();
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->foreign('monster_id')->references('monster_id')->on('monsters')->cascadeOnDelete();
        });

        Schema::create('stage_difficulty_monsters', function (Blueprint $table): void {
            $table->id();
            $table->string('difficulty_id', 160);
            $table->string('monster_id', 160);
            $table->string('spawn_type', 32);
            $table->unsignedInteger('weight')->default(100);
            $table->unsignedInteger('min_count')->default(1);
            $table->unsignedInteger('max_count')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->foreign('difficulty_id')->references('difficulty_id')->on('main_stage_difficulties')->cascadeOnDelete();
            $table->foreign('monster_id')->references('monster_id')->on('monsters')->cascadeOnDelete();
        });

        if (Schema::hasTable('main_stage_difficulties')) {
            Schema::table('main_stage_difficulties', function (Blueprint $table): void {
                foreach (['normal_monster_pool_id', 'elite_monster_pool_id', 'boss_id'] as $column) {
                    if (Schema::hasColumn('main_stage_difficulties', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_difficulty_monsters');
        Schema::dropIfExists('monster_boss_profiles');
        Schema::dropIfExists('monster_drop_bindings');
        Schema::dropIfExists('monster_skill_bindings');
        Schema::dropIfExists('monsters');

        if (Schema::hasTable('main_stage_difficulties')) {
            Schema::table('main_stage_difficulties', function (Blueprint $table): void {
                if (! Schema::hasColumn('main_stage_difficulties', 'normal_monster_pool_id')) {
                    $table->string('normal_monster_pool_id', 160)->default('');
                }
                if (! Schema::hasColumn('main_stage_difficulties', 'elite_monster_pool_id')) {
                    $table->string('elite_monster_pool_id', 160)->default('');
                }
                if (! Schema::hasColumn('main_stage_difficulties', 'boss_id')) {
                    $table->string('boss_id', 160)->default('');
                }
            });
        }
    }
};
