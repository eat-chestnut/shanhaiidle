<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_star_rules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('set_level')->unique();
            $table->unsignedTinyInteger('max_star');
            $table->text('summary')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->index(['set_level', 'is_enabled'], 'equipment_star_rules_admin_idx');
        });

        Schema::create('equipment_star_upgrade_costs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('set_level');
            $table->unsignedTinyInteger('from_star');
            $table->unsignedTinyInteger('to_star');
            $table->string('item_id');
            $table->unsignedInteger('count');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->foreign('item_id')
                ->references('item_id')
                ->on('items')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->index(['set_level', 'from_star', 'to_star'], 'equipment_star_upgrade_costs_transition_idx');
            $table->index(['item_id', 'is_enabled'], 'equipment_star_upgrade_costs_item_idx');
        });

        Schema::create('equipment_star_slot_unlocks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('required_star')->unique();
            $table->unsignedTinyInteger('slot_index')->unique();
            $table->string('slot_group', 32);
            $table->text('summary')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->index(['required_star', 'slot_group', 'is_enabled'], 'equipment_star_slot_unlocks_admin_idx');
        });

        Schema::create('equipment_stage_progression_rules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('from_set_level')->unique();
            $table->unsignedInteger('to_set_level')->unique();
            $table->unsignedTinyInteger('required_max_star');
            $table->string('star_keep_mode', 64);
            $table->text('summary')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->unique(['from_set_level', 'to_set_level'], 'equipment_stage_progression_unique_stage');
            $table->index(['from_set_level', 'to_set_level', 'is_enabled'], 'equipment_stage_progression_admin_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_stage_progression_rules');
        Schema::dropIfExists('equipment_star_slot_unlocks');
        Schema::dropIfExists('equipment_star_upgrade_costs');
        Schema::dropIfExists('equipment_star_rules');
    }
};
