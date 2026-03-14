<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_currencies', function (Blueprint $table): void {
            $table->id();
            $table->string('player_id', 64);
            $table->string('currency_id', 160);
            $table->unsignedBigInteger('amount')->default(0);
            $table->timestamps();

            $table->unique(['player_id', 'currency_id'], 'player_currency_unique');
            $table->index(['player_id']);
            $table->index(['currency_id']);
        });

        Schema::create('player_items', function (Blueprint $table): void {
            $table->id();
            $table->string('player_id', 64);
            $table->string('item_id', 160);
            $table->unsignedBigInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['player_id', 'item_id'], 'player_item_unique');
            $table->index(['player_id']);
            $table->index(['item_id']);
        });

        Schema::create('reward_grant_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('player_id', 64);
            $table->string('battle_id', 160);
            $table->string('item_id', 160);
            $table->unsignedBigInteger('count');
            $table->string('reward_source_type', 64);
            $table->string('reward_source_id', 160);
            $table->string('grant_batch_id', 160);
            $table->timestamps();

            $table->index(['battle_id']);
            $table->index(['grant_batch_id']);
            $table->index(['player_id', 'reward_source_type', 'reward_source_id'], 'reward_grant_log_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_grant_logs');
        Schema::dropIfExists('player_items');
        Schema::dropIfExists('player_currencies');
    }
};
