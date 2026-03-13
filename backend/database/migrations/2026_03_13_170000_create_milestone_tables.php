<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('milestones', function (Blueprint $table): void {
            $table->id();
            $table->string('milestone_id', 64)->unique();
            $table->string('title', 255);
            $table->string('display_name', 255);
            $table->string('condition_type', 32);
            $table->string('condition_value', 64);
            $table->string('pre_milestone_id', 64)->nullable();
            $table->string('reward_item_id', 64);
            $table->unsignedInteger('reward_count')->default(1);
            $table->string('icon')->nullable();
            $table->text('summary')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->index(['condition_type', 'is_enabled']);
            $table->index(['pre_milestone_id', 'is_enabled']);
            $table->index(['reward_item_id', 'is_enabled']);
            $table->index('sort_order');
        });

        Schema::create('player_milestones', function (Blueprint $table): void {
            $table->id();
            $table->string('player_id', 64);
            $table->string('milestone_id', 64);
            $table->boolean('is_unlocked')->default(false);
            $table->boolean('is_claimed')->default(false);
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            $table->foreign('milestone_id')->references('milestone_id')->on('milestones')->cascadeOnDelete();
            $table->unique(['player_id', 'milestone_id']);
            $table->index(['player_id', 'is_unlocked']);
            $table->index(['player_id', 'is_claimed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_milestones');
        Schema::dropIfExists('milestones');
    }
};
