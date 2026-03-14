<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * @deprecated 历史迁移，仅服务于已淘汰的旧蓝装模板 / 蓝词条表结构。
 * 当前正式蓝装与蓝词条模块不再使用这些旧表。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blue_gear_templates', function (Blueprint $table): void {
            $table->json('affix_entries')->nullable()->after('affix_pool_tags');
        });

        Schema::table('blue_affix_pool', function (Blueprint $table): void {
            $table->text('notes')->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('blue_gear_templates', function (Blueprint $table): void {
            $table->dropColumn('affix_entries');
        });

        Schema::table('blue_affix_pool', function (Blueprint $table): void {
            $table->dropColumn('notes');
        });
    }
};
