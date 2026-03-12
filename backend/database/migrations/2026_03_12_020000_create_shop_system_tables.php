<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_goods', function (Blueprint $table): void {
            $table->id();
            $table->string('goods_id', 64)->unique();
            $table->string('shop_type', 32);
            $table->string('title', 255);
            $table->string('subtitle', 255)->nullable();
            $table->text('desc')->nullable();
            $table->string('reward_item_id', 64);
            $table->unsignedInteger('reward_count')->default(1);
            $table->string('cost_currency_type', 32);
            $table->unsignedInteger('cost_amount')->default(0);
            $table->unsignedInteger('unlock_level')->default(1);
            $table->unsignedInteger('daily_limit')->nullable();
            $table->unsignedInteger('weekly_limit')->nullable();
            $table->unsignedInteger('lifetime_limit')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('icon')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['shop_type', 'is_enabled']);
            $table->index(['shop_type', 'sort_order']);
            $table->index('reward_item_id');
        });

        Schema::create('shop_player_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('player_id', 64)->unique();
            $table->unsignedInteger('level')->default(1);
            $table->unsignedBigInteger('gold')->default(0);
            $table->unsignedBigInteger('crystal')->default(0);
            $table->unsignedBigInteger('contribution')->default(0);
            $table->json('inventory')->nullable();
            $table->timestamps();
        });

        Schema::create('shop_purchase_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('player_id', 64);
            $table->string('goods_id', 64);
            $table->string('shop_type', 32);
            $table->string('reward_item_id', 64);
            $table->unsignedInteger('reward_count')->default(1);
            $table->string('cost_currency_type', 32);
            $table->unsignedInteger('cost_amount')->default(0);
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamp('purchased_at');
            $table->date('reset_bucket_date')->nullable();
            $table->string('reset_bucket_week', 16)->nullable();
            $table->string('operator_type', 16)->default('player');
            $table->timestamp('created_at')->nullable();

            $table->index(['player_id', 'goods_id']);
            $table->index(['player_id', 'shop_type']);
            $table->index(['goods_id', 'reset_bucket_date']);
            $table->index(['goods_id', 'reset_bucket_week']);
            $table->index('purchased_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_purchase_logs');
        Schema::dropIfExists('shop_player_profiles');
        Schema::dropIfExists('shop_goods');
    }
};
