<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equip_templates', function (Blueprint $table): void {
            if (! Schema::hasColumn('equip_templates', 'equip_type')) {
                $table->string('equip_type')->default('set')->after('slot');
            }
            if (! Schema::hasColumn('equip_templates', 'set_line_id')) {
                $table->string('set_line_id', 64)->nullable()->after('set_id');
            }
            if (! Schema::hasColumn('equip_templates', 'set_stage')) {
                $table->unsignedInteger('set_stage')->nullable()->after('set_line_id');
            }
            if (! Schema::hasColumn('equip_templates', 'flow_tag')) {
                $table->string('flow_tag', 64)->nullable()->after('set_stage');
            }
            if (! Schema::hasColumn('equip_templates', 'required_level')) {
                $table->unsignedInteger('required_level')->default(1)->after('flow_tag');
            }
            if (! Schema::hasColumn('equip_templates', 'white_stats')) {
                $table->json('white_stats')->nullable()->after('required_level');
            }
            if (! Schema::hasColumn('equip_templates', 'star_growth')) {
                $table->json('star_growth')->nullable()->after('white_stats');
            }
            if (! Schema::hasColumn('equip_templates', 'star_enabled')) {
                $table->boolean('star_enabled')->default(true)->after('star_growth');
            }
            if (! Schema::hasColumn('equip_templates', 'star_cap')) {
                $table->unsignedInteger('star_cap')->default(10)->after('star_enabled');
            }
            if (! Schema::hasColumn('equip_templates', 'can_attach_blue_affix')) {
                $table->boolean('can_attach_blue_affix')->default(false)->after('star_cap');
            }
            if (! Schema::hasColumn('equip_templates', 'can_roll_purple_affix')) {
                $table->boolean('can_roll_purple_affix')->default(false)->after('can_attach_blue_affix');
            }
            if (! Schema::hasColumn('equip_templates', 'socket_rule_ref')) {
                $table->string('socket_rule_ref', 64)->nullable()->after('can_roll_purple_affix');
            }
            if (! Schema::hasColumn('equip_templates', 'slot_group')) {
                $table->string('slot_group', 32)->nullable()->after('socket_rule_ref');
            }
            if (! Schema::hasColumn('equip_templates', 'theme_key')) {
                $table->string('theme_key', 32)->nullable()->after('slot_group');
            }
            if (! Schema::hasColumn('equip_templates', 'quality_tier')) {
                $table->string('quality_tier', 16)->default('normal')->after('theme_key');
            }
            if (! Schema::hasColumn('equip_templates', 'forge_enabled')) {
                $table->boolean('forge_enabled')->default(false)->after('quality_tier');
            }
            if (! Schema::hasColumn('equip_templates', 'forge_tier')) {
                $table->string('forge_tier', 8)->nullable()->after('forge_enabled');
            }
            if (! Schema::hasColumn('equip_templates', 'forge_family_id')) {
                $table->string('forge_family_id', 64)->nullable()->after('forge_tier');
            }
            if (! Schema::hasColumn('equip_templates', 'upgrade_from_template_id')) {
                $table->string('upgrade_from_template_id', 64)->nullable()->after('forge_family_id');
            }
            if (! Schema::hasColumn('equip_templates', 'upgrade_to_template_id')) {
                $table->string('upgrade_to_template_id', 64)->nullable()->after('upgrade_from_template_id');
            }
            if (! Schema::hasColumn('equip_templates', 'blueprint_item_id')) {
                $table->string('blueprint_item_id', 64)->nullable()->after('upgrade_to_template_id');
            }
        });

        Schema::table('equip_templates', function (Blueprint $table): void {
            if (Schema::hasColumn('equip_templates', 'set_line_id')) {
                $table->index('set_line_id');
            }
            if (Schema::hasColumn('equip_templates', 'forge_family_id')) {
                $table->index('forge_family_id');
            }
            if (Schema::hasColumn('equip_templates', 'forge_tier')) {
                $table->index('forge_tier');
            }
            if (Schema::hasColumn('equip_templates', 'theme_key')) {
                $table->index('theme_key');
            }
            if (Schema::hasColumn('equip_templates', 'quality_tier')) {
                $table->index('quality_tier');
            }
        });
    }

    public function down(): void
    {
        Schema::table('equip_templates', function (Blueprint $table): void {
            foreach ([
                'set_line_id',
                'forge_family_id',
                'forge_tier',
                'theme_key',
                'quality_tier',
            ] as $column) {
                try {
                    $table->dropIndex([$column]);
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            foreach ([
                'blueprint_item_id',
                'upgrade_to_template_id',
                'upgrade_from_template_id',
                'forge_family_id',
                'forge_tier',
                'forge_enabled',
                'quality_tier',
                'theme_key',
                'slot_group',
                'socket_rule_ref',
                'can_roll_purple_affix',
                'can_attach_blue_affix',
                'star_cap',
                'star_enabled',
                'star_growth',
                'white_stats',
                'required_level',
                'flow_tag',
                'set_stage',
                'set_line_id',
                'equip_type',
            ] as $column) {
                if (Schema::hasColumn('equip_templates', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
