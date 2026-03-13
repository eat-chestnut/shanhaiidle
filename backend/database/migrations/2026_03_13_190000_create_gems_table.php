<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gems', function (Blueprint $table): void {
            $table->id();
            $table->string('gem_id')->unique();
            $table->string('item_id')->unique();
            $table->string('gem_name');
            $table->string('display_name');
            $table->string('gem_type');
            $table->string('stat_key');
            $table->string('value_type');
            $table->decimal('value', 12, 4);
            $table->string('quality');
            $table->string('rarity');
            $table->string('slot_group');
            $table->unsignedInteger('unlock_level')->default(1);
            $table->string('icon')->nullable();
            $table->text('summary')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->foreign('item_id')
                ->references('item_id')
                ->on('items')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->index(['gem_type', 'quality', 'slot_group', 'is_enabled'], 'gems_admin_filters_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gems');
    }
};
