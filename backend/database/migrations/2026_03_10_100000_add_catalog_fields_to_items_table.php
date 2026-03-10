<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table): void {
            if (! Schema::hasColumn('items', 'sub_type')) {
                $table->string('sub_type')->nullable()->after('type');
            }
            if (! Schema::hasColumn('items', 'material_type')) {
                $table->string('material_type')->nullable()->after('sub_type');
            }
            if (! Schema::hasColumn('items', 'desc')) {
                $table->text('desc')->nullable()->after('trait');
            }
            if (! Schema::hasColumn('items', 'effect_type')) {
                $table->string('effect_type')->nullable()->after('gem_effect');
            }
            if (! Schema::hasColumn('items', 'target_scope')) {
                $table->string('target_scope')->nullable()->after('effect_type');
            }
            if (! Schema::hasColumn('items', 'effect_payload')) {
                $table->json('effect_payload')->nullable()->after('target_scope');
            }
            if (! Schema::hasColumn('items', 'drop_unlock_level')) {
                $table->unsignedInteger('drop_unlock_level')->default(1)->after('effect_payload');
            }
            if (! Schema::hasColumn('items', 'socket_limit')) {
                $table->json('socket_limit')->nullable()->after('drop_unlock_level');
            }
            if (! Schema::hasColumn('items', 'source_tags')) {
                $table->json('source_tags')->nullable()->after('socket_limit');
            }
            if (! Schema::hasColumn('items', 'use_tags')) {
                $table->json('use_tags')->nullable()->after('source_tags');
            }
            if (! Schema::hasColumn('items', 'stack_limit')) {
                $table->unsignedInteger('stack_limit')->default(9999)->after('use_tags');
            }
            if (! Schema::hasColumn('items', 'can_compose')) {
                $table->boolean('can_compose')->default(false)->after('stack_limit');
            }
            if (! Schema::hasColumn('items', 'can_reforge')) {
                $table->boolean('can_reforge')->default(false)->after('can_compose');
            }
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table): void {
            foreach ([
                'can_reforge',
                'can_compose',
                'stack_limit',
                'use_tags',
                'source_tags',
                'socket_limit',
                'drop_unlock_level',
                'effect_payload',
                'target_scope',
                'effect_type',
                'desc',
                'material_type',
                'sub_type',
            ] as $column) {
                if (Schema::hasColumn('items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
