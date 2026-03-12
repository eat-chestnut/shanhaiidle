<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_player_profiles', function (Blueprint $table): void {
            $table->unsignedBigInteger('exp')->default(0)->after('level');
            $table->unsignedInteger('free_attr_points')->default(0)->after('contribution');
            $table->unsignedInteger('skill_points')->default(0)->after('free_attr_points');
            $table->json('equipment')->nullable()->after('inventory');
            $table->json('claimed_milestones')->nullable()->after('equipment');
        });

        Schema::create('gm_operation_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('operator_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('target_player_id', 64);
            $table->string('action_type', 64);
            $table->json('action_payload')->nullable();
            $table->json('result_snapshot_before')->nullable();
            $table->json('result_snapshot_after')->nullable();
            $table->string('status', 32);
            $table->text('status_message')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('target_player_id');
            $table->index('action_type');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gm_operation_logs');

        Schema::table('shop_player_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'exp',
                'free_attr_points',
                'skill_points',
                'equipment',
                'claimed_milestones',
            ]);
        });
    }
};
