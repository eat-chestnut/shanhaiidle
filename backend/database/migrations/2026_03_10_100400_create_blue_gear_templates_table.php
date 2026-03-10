<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blue_gear_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('template_id', 64)->unique();
            $table->string('name', 255);
            $table->string('blue_pool_id', 64)->nullable();
            $table->string('slot_id', 64);
            $table->string('flow_tag', 64)->nullable();
            $table->unsignedInteger('required_level')->default(1);
            $table->json('white_stats')->nullable();
            $table->unsignedInteger('affix_count')->default(1);
            $table->json('affix_pool_tags')->nullable();
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->index(['slot_id', 'required_level']);
            $table->index(['is_enabled', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blue_gear_templates');
    }
};
