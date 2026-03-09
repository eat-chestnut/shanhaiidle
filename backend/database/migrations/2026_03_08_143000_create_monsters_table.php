<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monsters', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('kind');
            $table->unsignedInteger('hp');
            $table->unsignedInteger('atk');
            $table->unsignedInteger('def');
            $table->unsignedInteger('speed');
            $table->unsignedInteger('radius');
            $table->unsignedInteger('aggro_range');
            $table->decimal('attack_interval', 6, 2);
            $table->unsignedInteger('attack_range');
            $table->unsignedInteger('exp');
            $table->unsignedInteger('drop_bonus_percent')->default(0);
            $table->unsignedInteger('dex_gold')->default(5);
            $table->string('icon')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monsters');
    }
};
