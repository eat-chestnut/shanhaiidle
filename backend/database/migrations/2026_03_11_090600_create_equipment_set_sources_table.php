<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('equipment_set_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('set_source_id', 160)->unique();
            $table->string('set_line_id', 120);
            $table->string('set_name', 255);
            $table->string('flow_tag', 64)->default('');
            $table->unsignedInteger('set_stage')->default(20);
            $table->string('main_mat_1', 120)->default('');
            $table->string('main_mat_2', 120)->default('');
            $table->json('sub_materials')->nullable();
            $table->json('source_maps')->nullable();
            $table->json('source_bosses')->nullable();
            $table->boolean('need_blueprint')->default(false);
            $table->string('blueprint_source', 255)->default('');
            $table->text('craft_desc')->nullable();
            $table->string('icon_path', 255)->default('');
            $table->string('image_path', 255)->default('');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_set_sources');
    }
};
