<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_equipment_instances', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('player_id');
            $table->string('instance_id', 64)->unique();
            $table->string('item_id');
            $table->string('equipment_source_type', 32);
            $table->string('slot_type', 32);
            $table->string('set_id', 64)->nullable();
            $table->unsignedInteger('set_level')->nullable();
            $table->unsignedTinyInteger('star')->default(0);
            $table->unsignedTinyInteger('max_star')->default(0);
            $table->string('quality', 32);
            $table->string('rarity', 32);
            $table->boolean('is_locked')->default(false);
            $table->boolean('is_equipped')->default(false);
            $table->timestamp('obtained_at')->nullable();
            $table->timestamps();

            $table->foreign('item_id')
                ->references('item_id')
                ->on('items')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->unique(['player_id', 'instance_id'], 'player_equipment_instances_player_instance_unique');
            $table->index(['equipment_source_type', 'slot_type'], 'player_equipment_instances_source_slot_idx');
            $table->index(['set_level', 'is_equipped', 'is_locked'], 'player_equipment_instances_filters_idx');
            $table->index(['player_id', 'is_equipped'], 'player_equipment_instances_player_equipped_idx');
        });

        Schema::create('player_equipment_loadouts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('player_id');
            $table->string('slot_type', 32);
            $table->string('instance_id', 64);
            $table->timestamps();

            $table->unique(['player_id', 'slot_type'], 'player_equipment_loadouts_player_slot_unique');
            $table->unique('instance_id', 'player_equipment_loadouts_instance_unique');

            $table->foreign(['player_id', 'instance_id'], 'player_equipment_loadouts_player_instance_fk')
                ->references(['player_id', 'instance_id'])
                ->on('player_equipment_instances')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });

        Schema::create('player_equipment_gem_slots', function (Blueprint $table): void {
            $table->id();
            $table->string('instance_id', 64);
            $table->unsignedTinyInteger('slot_index');
            $table->string('slot_group', 32);
            $table->unsignedTinyInteger('required_star');
            $table->boolean('is_unlocked')->default(false);
            $table->string('gem_item_id')->nullable();
            $table->timestamps();

            $table->unique(['instance_id', 'slot_index'], 'player_equipment_gem_slots_instance_slot_unique');
            $table->index(['slot_group', 'required_star'], 'player_equipment_gem_slots_group_star_idx');
            $table->index(['is_unlocked', 'gem_item_id'], 'player_equipment_gem_slots_unlock_gem_idx');

            $table->foreign('instance_id')
                ->references('instance_id')
                ->on('player_equipment_instances')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign('gem_item_id')
                ->references('item_id')
                ->on('items')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_equipment_gem_slots');
        Schema::dropIfExists('player_equipment_loadouts');
        Schema::dropIfExists('player_equipment_instances');
    }
};
