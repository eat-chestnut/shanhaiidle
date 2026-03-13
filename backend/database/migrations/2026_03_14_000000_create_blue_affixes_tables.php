<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blue_affixes', function (Blueprint $table): void {
            $table->id();
            $table->string('affix_id', 64)->unique();
            $table->string('affix_name', 255);
            $table->string('display_name', 255);
            $table->string('effect_key', 64);
            $table->string('value_type', 16);
            $table->decimal('value_min', 12, 4);
            $table->decimal('value_max', 12, 4);
            $table->unsignedInteger('weight')->default(1);
            $table->unsignedInteger('level_band');
            $table->string('quality', 16)->default('blue');
            $table->string('rarity', 16)->default('blue');
            $table->text('summary')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->index(['effect_key', 'level_band'], 'blue_affixes_effect_level_index');
            $table->index(['is_enabled', 'sort_order'], 'blue_affixes_enabled_sort_index');
        });

        Schema::create('blue_affix_slot_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('affix_id', 64);
            $table->string('slot_type', 32);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->unique(['affix_id', 'slot_type'], 'blue_affix_slot_rules_affix_slot_unique');
            $table->index(['affix_id', 'sort_order'], 'blue_affix_slot_rules_affix_sort_index');
            $table->foreign('affix_id', 'blue_affix_slot_rules_affix_fk')
                ->references('affix_id')
                ->on('blue_affixes')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blue_affix_slot_rules');
        Schema::dropIfExists('blue_affixes');
    }
};
