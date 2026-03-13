<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('monster_drop_bindings');

        Schema::create('monster_drop_items', function (Blueprint $table): void {
            $table->id();
            $table->string('monster_id', 160);
            $table->string('item_id', 160);
            $table->string('drop_type', 32);
            $table->unsignedInteger('count_min')->default(1);
            $table->unsignedInteger('count_max')->default(1);
            $table->decimal('drop_rate', 8, 4)->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->foreign('monster_id')->references('monster_id')->on('monsters')->cascadeOnDelete();
        });

        Schema::create('stage_difficulty_first_clear_rewards', function (Blueprint $table): void {
            $table->id();
            $table->string('difficulty_id', 160);
            $table->string('item_id', 160);
            $table->unsignedInteger('count')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->foreign('difficulty_id')->references('difficulty_id')->on('main_stage_difficulties')->cascadeOnDelete();
        });

        if (Schema::hasTable('monster_boss_profiles') && Schema::hasColumn('monster_boss_profiles', 'first_clear_reward_group_id')) {
            Schema::table('monster_boss_profiles', function (Blueprint $table): void {
                // Remove deprecated legacy residue from Boss 扩展正式链路。
                $table->dropColumn('first_clear_reward_group_id');
            });
        }

        if (Schema::hasTable('main_stage_difficulties')) {
            Schema::table('main_stage_difficulties', function (Blueprint $table): void {
                foreach (['drop_preview_group_id', 'first_clear_reward_group_id'] as $column) {
                    if (Schema::hasColumn('main_stage_difficulties', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_difficulty_first_clear_rewards');
        Schema::dropIfExists('monster_drop_items');

        if (Schema::hasTable('monster_boss_profiles') && ! Schema::hasColumn('monster_boss_profiles', 'first_clear_reward_group_id')) {
            Schema::table('monster_boss_profiles', function (Blueprint $table): void {
                // Legacy rollback only. 正式结构不再使用该字段。
                $table->string('first_clear_reward_group_id', 160)->nullable();
            });
        }

        if (Schema::hasTable('main_stage_difficulties')) {
            Schema::table('main_stage_difficulties', function (Blueprint $table): void {
                if (! Schema::hasColumn('main_stage_difficulties', 'drop_preview_group_id')) {
                    $table->string('drop_preview_group_id', 160)->default('');
                }
                if (! Schema::hasColumn('main_stage_difficulties', 'first_clear_reward_group_id')) {
                    $table->string('first_clear_reward_group_id', 160)->default('');
                }
            });
        }

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
    }
};
