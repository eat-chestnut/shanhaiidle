<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blue_equipment_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('template_id', 64)->unique();
            $table->string('template_name', 255);
            $table->string('display_name', 255);
            $table->string('slot_type', 32);
            $table->unsignedInteger('level_band');
            $table->string('result_item_id', 64);
            $table->unsignedInteger('blue_affix_count_min')->default(1);
            $table->unsignedInteger('blue_affix_count_max')->default(1);
            $table->string('quality', 16)->default('blue');
            $table->string('rarity', 16)->default('blue');
            $table->unsignedInteger('unlock_level')->default(1);
            $table->string('icon', 255)->nullable();
            $table->text('summary')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->unique(['slot_type', 'level_band'], 'blue_equipment_templates_slot_level_unique');
            $table->unique('result_item_id', 'blue_equipment_templates_result_item_unique');
            $table->index(['is_enabled', 'sort_order'], 'blue_equipment_templates_enabled_sort_index');
            $table->foreign('result_item_id', 'blue_equipment_templates_result_item_fk')
                ->references('item_id')
                ->on('items')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        Schema::create('blue_equipment_template_base_stats', function (Blueprint $table): void {
            $table->id();
            $table->string('template_id', 64);
            $table->string('stat_key', 64);
            $table->string('value_type', 16);
            $table->decimal('value', 12, 4);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->index(['template_id', 'sort_order'], 'blue_equipment_template_base_stats_template_sort_index');
            $table->foreign('template_id', 'blue_equipment_template_base_stats_template_fk')
                ->references('template_id')
                ->on('blue_equipment_templates')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blue_equipment_template_base_stats');
        Schema::dropIfExists('blue_equipment_templates');
    }
};
