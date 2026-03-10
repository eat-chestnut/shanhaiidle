<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_dungeons', function (Blueprint $table): void {
            $table->id();
            $table->string('dungeon_id', 64)->unique();
            $table->string('name', 255);
            $table->string('dungeon_type', 32);
            $table->unsignedInteger('unlock_level')->default(1);
            $table->json('layer_config')->nullable();
            $table->json('drop_pools')->nullable();
            $table->unsignedInteger('stamina_cost')->default(10);
            $table->unsignedInteger('daily_limit')->default(10);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->index(['dungeon_type', 'unlock_level']);
            $table->index(['is_enabled', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_dungeons');
    }
};
