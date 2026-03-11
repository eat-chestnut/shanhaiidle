<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('starter_gifts', function (Blueprint $table): void {
            $table->id();
            $table->string('gift_id', 120)->unique();
            $table->unsignedInteger('unlock_level')->default(1);
            $table->string('gift_name', 255);
            $table->string('gift_role', 120)->default('');
            $table->longText('open_copy')->nullable();
            $table->json('rewards')->nullable();
            $table->boolean('must_claim')->default(true);
            $table->boolean('is_free')->default(true);
            $table->string('icon_path', 255)->default('');
            $table->string('banner_path', 255)->default('');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('starter_gifts');
    }
};
