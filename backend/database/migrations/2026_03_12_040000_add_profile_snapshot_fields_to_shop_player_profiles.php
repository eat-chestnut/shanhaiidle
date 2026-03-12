<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_player_profiles', function (Blueprint $table): void {
            $table->string('nickname', 64)->nullable()->after('player_id');
            $table->string('current_stage_id', 64)->nullable()->after('contribution');
            $table->unsignedInteger('current_difficulty')->default(0)->after('current_stage_id');
            $table->string('highest_cleared_stage_id', 64)->nullable()->after('current_difficulty');
            $table->unsignedInteger('highest_cleared_difficulty')->default(0)->after('highest_cleared_stage_id');
            $table->string('current_sect_id', 64)->nullable()->after('skill_points');
            $table->json('attrs_json')->nullable()->after('current_sect_id');
            $table->json('patrol_summary')->nullable()->after('claimed_milestones');
            $table->json('task_summary')->nullable()->after('patrol_summary');

            $table->index('nickname');
            $table->index('current_stage_id');
            $table->index('highest_cleared_stage_id');
        });
    }

    public function down(): void
    {
        Schema::table('shop_player_profiles', function (Blueprint $table): void {
            $table->dropIndex(['nickname']);
            $table->dropIndex(['current_stage_id']);
            $table->dropIndex(['highest_cleared_stage_id']);

            $table->dropColumn([
                'nickname',
                'current_stage_id',
                'current_difficulty',
                'highest_cleared_stage_id',
                'highest_cleared_difficulty',
                'current_sect_id',
                'attrs_json',
                'patrol_summary',
                'task_summary',
            ]);
        });
    }
};
