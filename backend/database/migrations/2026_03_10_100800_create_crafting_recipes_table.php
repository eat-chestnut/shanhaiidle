<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crafting_recipes', function (Blueprint $table): void {
            $table->id();
            $table->string('recipe_id', 64)->unique();
            $table->string('recipe_type', 32);
            $table->string('output_type', 32);
            $table->string('output_id', 64);
            $table->unsignedInteger('output_count')->default(1);
            $table->unsignedInteger('unlock_level')->default(1);
            $table->json('cost_items')->nullable();
            $table->unsignedInteger('cost_gold')->default(0);
            $table->string('cost_currency', 32)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->index(['recipe_type', 'unlock_level']);
            $table->index(['is_enabled', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crafting_recipes');
    }
};
