<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talismans', function (Blueprint $table): void {
            $table->id();
            $table->string('talisman_id')->unique();
            $table->string('item_id')->unique();
            $table->string('talisman_name');
            $table->string('display_name');
            $table->string('talisman_type');
            $table->string('recommended_sect', 32)->default('none');
            $table->string('quality');
            $table->string('rarity');
            $table->unsignedInteger('unlock_level')->default(1);
            $table->string('icon')->nullable();
            $table->text('summary')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->foreign('item_id')
                ->references('item_id')
                ->on('items')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->index(['talisman_type', 'recommended_sect', 'quality', 'is_enabled'], 'talismans_admin_filters_idx');
        });

        Schema::create('talisman_tiers', function (Blueprint $table): void {
            $table->id();
            $table->string('talisman_id');
            $table->unsignedTinyInteger('tier_no');
            $table->string('tier_name');
            $table->string('effect_key');
            $table->string('value_type');
            $table->decimal('value', 12, 4);
            $table->string('trigger_rule');
            $table->unsignedInteger('cooldown_sec')->default(0);
            $table->text('summary')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->foreign('talisman_id')
                ->references('talisman_id')
                ->on('talismans')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->unique(['talisman_id', 'tier_no'], 'talisman_tiers_unique_tier');
        });

        Schema::create('talisman_tier_upgrade_costs', function (Blueprint $table): void {
            $table->id();
            $table->string('talisman_id');
            $table->unsignedTinyInteger('tier_no');
            $table->unsignedTinyInteger('target_tier_no');
            $table->string('item_id');
            $table->unsignedInteger('count');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->foreign('talisman_id')
                ->references('talisman_id')
                ->on('talismans')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign('item_id')
                ->references('item_id')
                ->on('items')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->index(['talisman_id', 'tier_no', 'target_tier_no'], 'talisman_upgrade_costs_tier_idx');
        });

        Schema::create('talisman_star_links', function (Blueprint $table): void {
            $table->id();
            $table->string('talisman_id');
            $table->unsignedTinyInteger('tier_no');
            $table->unsignedTinyInteger('required_equipment_star');
            $table->string('effect_key');
            $table->string('value_type');
            $table->decimal('value', 12, 4);
            $table->text('summary')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->foreign('talisman_id')
                ->references('talisman_id')
                ->on('talismans')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->unique(['talisman_id', 'tier_no', 'required_equipment_star'], 'talisman_star_links_unique_threshold');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talisman_star_links');
        Schema::dropIfExists('talisman_tier_upgrade_costs');
        Schema::dropIfExists('talisman_tiers');
        Schema::dropIfExists('talismans');
    }
};
