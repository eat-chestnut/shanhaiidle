<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purple_affix_pool', function (Blueprint $table): void {
            $table->id();
            $table->string('affix_id', 64)->unique();
            $table->string('affix_name', 128);
            $table->string('stat', 64);
            $table->json('slot_tags')->nullable();
            $table->json('flow_tags')->nullable();
            $table->string('rarity_tier', 16)->default('purple');
            $table->integer('min_value')->default(0);
            $table->integer('max_value')->default(0);
            $table->string('value_mode', 16)->default('flat');
            $table->unsignedInteger('weight')->default(1);
            $table->unsignedInteger('unlock_level')->default(50);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->index(['is_enabled', 'sort_order']);
            $table->index(['stat', 'unlock_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purple_affix_pool');
    }
};
