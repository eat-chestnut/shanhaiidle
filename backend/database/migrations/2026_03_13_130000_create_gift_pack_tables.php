<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_packs', function (Blueprint $table): void {
            $table->id();
            $table->string('pack_id', 64)->unique();
            $table->string('item_id', 64)->unique();
            $table->string('pack_name', 255);
            $table->string('display_name', 255);
            $table->string('pack_type', 32);
            $table->string('pack_mode', 32);
            $table->string('open_mode', 32);
            $table->unsignedInteger('select_count_min')->nullable();
            $table->unsignedInteger('select_count_max')->nullable();
            $table->text('desc')->nullable();
            $table->string('icon')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->index(['pack_type', 'is_enabled']);
            $table->index(['pack_mode', 'is_enabled']);
            $table->index(['open_mode', 'is_enabled']);
            $table->index('sort_order');
        });

        Schema::create('gift_pack_items', function (Blueprint $table): void {
            $table->id();
            $table->string('pack_id', 64);
            $table->string('item_id', 64);
            $table->string('content_mode', 32);
            $table->unsignedInteger('count_min')->default(1);
            $table->unsignedInteger('count_max')->default(1);
            $table->unsignedInteger('weight')->default(0);
            $table->string('recommended_sect', 32)->default('none');
            $table->string('display_note', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->foreign('pack_id')->references('pack_id')->on('gift_packs')->cascadeOnDelete();
            $table->index(['pack_id', 'content_mode']);
            $table->index(['item_id', 'is_enabled']);
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_pack_items');
        Schema::dropIfExists('gift_packs');
    }
};
