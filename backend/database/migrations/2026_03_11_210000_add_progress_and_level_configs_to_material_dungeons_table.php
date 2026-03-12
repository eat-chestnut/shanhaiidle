<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_dungeons', function (Blueprint $table): void {
            if (! Schema::hasColumn('material_dungeons', 'unlock_stage_id')) {
                $table->string('unlock_stage_id')->nullable()->after('unlock_level');
            }

            if (! Schema::hasColumn('material_dungeons', 'level_configs')) {
                $table->json('level_configs')->nullable()->after('layer_rules');
            }
        });
    }

    public function down(): void
    {
        Schema::table('material_dungeons', function (Blueprint $table): void {
            if (Schema::hasColumn('material_dungeons', 'level_configs')) {
                $table->dropColumn('level_configs');
            }

            if (Schema::hasColumn('material_dungeons', 'unlock_stage_id')) {
                $table->dropColumn('unlock_stage_id');
            }
        });
    }
};
