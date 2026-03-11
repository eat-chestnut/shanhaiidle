<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('world_names', function (Blueprint $table): void {
            $table->id();
            $table->string('name_id', 120)->unique();
            $table->string('category', 64);
            $table->string('sub_category', 64)->default('');
            $table->string('display_name', 255);
            $table->text('source_text')->nullable();
            $table->boolean('from_original')->default(false);
            $table->text('naming_note')->nullable();
            $table->json('visual_tags')->nullable();
            $table->json('system_usage')->nullable();
            $table->string('icon_path', 255)->default('');
            $table->string('image_path', 255)->default('');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('world_names');
    }
};
