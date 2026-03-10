<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equip_slots', function (Blueprint $table): void {
            $table->id();
            $table->string('slot_id', 64)->unique();
            $table->string('slot_name', 64);
            $table->string('slot_type', 32)->default('equipment');
            $table->unsignedInteger('unlock_level')->default(1);
            $table->unsignedInteger('equip_limit')->default(1);
            $table->boolean('is_set_slot')->default(false);
            $table->boolean('can_drop_blue_gear')->default(false);
            $table->boolean('can_craft')->default(true);
            $table->boolean('can_exchange')->default(false);
            $table->boolean('can_star_up')->default(true);
            $table->boolean('can_rank_up')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->index(['is_enabled', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equip_slots');
    }
};
