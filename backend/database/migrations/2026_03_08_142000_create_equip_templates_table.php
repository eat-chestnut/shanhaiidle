<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equip_templates', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('slot');
            $table->string('rarity');
            $table->string('main_stat');
            $table->unsignedInteger('main_min');
            $table->unsignedInteger('main_max');
            $table->string('icon')->nullable();
            $table->json('effects')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equip_templates');
    }
};
