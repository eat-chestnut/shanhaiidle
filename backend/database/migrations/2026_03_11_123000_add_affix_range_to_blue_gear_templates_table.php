<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * @deprecated 历史迁移，仅服务于已淘汰的旧蓝装模板表结构。
 * 当前正式模块口径为 BlueEquipmentTemplate / BlueEquipmentTemplateBaseStat。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blue_gear_templates', function (Blueprint $table): void {
            $table->unsignedInteger('min_affix_count')->nullable()->after('affix_count');
            $table->unsignedInteger('max_affix_count')->nullable()->after('min_affix_count');
        });
    }

    public function down(): void
    {
        Schema::table('blue_gear_templates', function (Blueprint $table): void {
            $table->dropColumn(['min_affix_count', 'max_affix_count']);
        });
    }
};
