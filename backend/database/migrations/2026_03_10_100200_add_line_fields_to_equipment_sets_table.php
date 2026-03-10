<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_sets', function (Blueprint $table): void {
            if (! Schema::hasColumn('equipment_sets', 'set_line_id')) {
                $table->string('set_line_id', 64)->nullable()->after('id');
            }
            if (! Schema::hasColumn('equipment_sets', 'sect')) {
                $table->string('sect', 64)->nullable()->after('name');
            }
            if (! Schema::hasColumn('equipment_sets', 'flow_tag')) {
                $table->string('flow_tag', 64)->nullable()->after('sect');
            }
            if (! Schema::hasColumn('equipment_sets', 'stage')) {
                $table->unsignedInteger('stage')->nullable()->after('flow_tag');
            }
            if (! Schema::hasColumn('equipment_sets', 'piece_count')) {
                $table->unsignedInteger('piece_count')->nullable()->after('stage');
            }
            if (! Schema::hasColumn('equipment_sets', 'slot_ids')) {
                $table->json('slot_ids')->nullable()->after('piece_count');
            }
            if (! Schema::hasColumn('equipment_sets', 'description')) {
                $table->text('description')->nullable()->after('thresholds');
            }
        });

        Schema::table('equipment_sets', function (Blueprint $table): void {
            if (Schema::hasColumn('equipment_sets', 'set_line_id')) {
                $table->index('set_line_id');
            }
            if (Schema::hasColumn('equipment_sets', 'stage')) {
                $table->index('stage');
            }
        });
    }

    public function down(): void
    {
        Schema::table('equipment_sets', function (Blueprint $table): void {
            foreach (['set_line_id', 'stage'] as $column) {
                try {
                    $table->dropIndex([$column]);
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            foreach ([
                'description',
                'slot_ids',
                'piece_count',
                'stage',
                'flow_tag',
                'sect',
                'set_line_id',
            ] as $column) {
                if (Schema::hasColumn('equipment_sets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
