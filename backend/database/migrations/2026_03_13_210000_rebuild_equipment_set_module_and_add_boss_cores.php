<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('equipment_set_recipe_cost_items');
        Schema::dropIfExists('equipment_set_craft_recipes');
        Schema::dropIfExists('equipment_set_effects');
        Schema::dropIfExists('equipment_set_items');
        Schema::dropIfExists('boss_core_effects');
        Schema::dropIfExists('boss_cores');
        Schema::dropIfExists('equipment_sets');

        Schema::create('equipment_sets', function (Blueprint $table): void {
            $table->id();
            $table->string('set_id', 64)->unique();
            $table->string('set_name', 255);
            $table->string('display_name', 255);
            $table->unsignedInteger('set_level');
            $table->unsignedInteger('piece_total');
            $table->string('set_type', 32)->default('combat_set');
            $table->string('quality', 16)->default('white');
            $table->string('rarity', 16)->default('white');
            $table->unsignedInteger('unlock_level')->default(1);
            $table->string('icon', 255)->nullable();
            $table->text('summary')->nullable();
            $table->string('source_desc', 255)->default('');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();
        });

        Schema::create('equipment_set_items', function (Blueprint $table): void {
            $table->id();
            $table->string('set_id', 64);
            $table->string('item_id', 64);
            $table->string('slot_type', 32);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->foreign('set_id')->references('set_id')->on('equipment_sets')->cascadeOnDelete();
            $table->foreign('item_id')->references('item_id')->on('items')->restrictOnDelete();
            $table->unique('item_id');
            $table->unique(['set_id', 'slot_type']);
        });

        Schema::create('equipment_set_effects', function (Blueprint $table): void {
            $table->id();
            $table->string('set_id', 64);
            $table->unsignedInteger('piece_count');
            $table->string('effect_key', 64);
            $table->string('value_type', 16);
            $table->decimal('value', 12, 4)->default(0);
            $table->text('summary')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->foreign('set_id')->references('set_id')->on('equipment_sets')->cascadeOnDelete();
            $table->unique(['set_id', 'piece_count', 'effect_key']);
        });

        Schema::create('equipment_set_craft_recipes', function (Blueprint $table): void {
            $table->id();
            $table->string('recipe_id', 64)->unique();
            $table->string('set_id', 64);
            $table->string('slot_type', 32);
            $table->string('result_item_id', 64);
            $table->string('required_base_item_id', 64)->nullable();
            $table->string('required_blueprint_item_id', 64)->nullable();
            $table->unsignedInteger('unlock_level')->default(1);
            $table->text('summary')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->foreign('set_id')->references('set_id')->on('equipment_sets')->cascadeOnDelete();
            $table->foreign('result_item_id')->references('item_id')->on('items')->restrictOnDelete();
            $table->foreign('required_base_item_id')->references('item_id')->on('items')->nullOnDelete();
            $table->foreign('required_blueprint_item_id')->references('item_id')->on('items')->nullOnDelete();
            $table->unique(['set_id', 'slot_type']);
            $table->unique('result_item_id');
        });

        Schema::create('equipment_set_recipe_cost_items', function (Blueprint $table): void {
            $table->id();
            $table->string('recipe_id', 64);
            $table->string('item_id', 64);
            $table->unsignedInteger('count');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->foreign('recipe_id')->references('recipe_id')->on('equipment_set_craft_recipes')->cascadeOnDelete();
            $table->foreign('item_id')->references('item_id')->on('items')->restrictOnDelete();
            $table->unique(['recipe_id', 'item_id']);
        });

        Schema::create('boss_cores', function (Blueprint $table): void {
            $table->id();
            $table->string('core_id', 64)->unique();
            $table->string('item_id', 64)->unique();
            $table->string('core_name', 255);
            $table->string('display_name', 255);
            $table->string('source_boss_id', 64);
            $table->string('quality', 16)->default('white');
            $table->string('rarity', 16)->default('white');
            $table->string('recommended_sect', 32)->default('none');
            $table->string('recommended_build', 32)->default('burst');
            $table->string('icon', 255)->nullable();
            $table->text('summary')->nullable();
            $table->string('drop_rate_note', 255)->default('');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->foreign('item_id')->references('item_id')->on('items')->restrictOnDelete();
            $table->foreign('source_boss_id')->references('monster_id')->on('monsters')->restrictOnDelete();
        });

        Schema::create('boss_core_effects', function (Blueprint $table): void {
            $table->id();
            $table->string('core_id', 64);
            $table->string('effect_key', 64);
            $table->string('value_type', 16);
            $table->decimal('value', 12, 4)->default(0);
            $table->text('summary')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->foreign('core_id')->references('core_id')->on('boss_cores')->cascadeOnDelete();
            $table->unique('core_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boss_core_effects');
        Schema::dropIfExists('boss_cores');
        Schema::dropIfExists('equipment_set_recipe_cost_items');
        Schema::dropIfExists('equipment_set_craft_recipes');
        Schema::dropIfExists('equipment_set_effects');
        Schema::dropIfExists('equipment_set_items');
        Schema::dropIfExists('equipment_sets');

        Schema::create('equipment_sets', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('name', 255);
            $table->unsignedInteger('max_pieces')->default(2);
            $table->json('thresholds')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->string('set_line_id', 64)->nullable();
            $table->string('sect', 64)->nullable();
            $table->string('flow_tag', 64)->nullable();
            $table->unsignedInteger('stage')->nullable();
            $table->unsignedInteger('piece_count')->nullable();
            $table->json('slot_ids')->nullable();
            $table->text('description')->nullable();
        });
    }
};
