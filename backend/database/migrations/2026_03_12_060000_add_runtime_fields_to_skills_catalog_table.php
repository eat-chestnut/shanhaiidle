<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skills_catalog', function (Blueprint $table): void {
            if (! Schema::hasColumn('skills_catalog', 'type')) {
                $table->string('type', 32)->nullable()->after('class');
            }
            if (! Schema::hasColumn('skills_catalog', 'min_level')) {
                $table->unsignedInteger('min_level')->default(1)->after('type');
            }
            if (! Schema::hasColumn('skills_catalog', 'max_level')) {
                $table->unsignedInteger('max_level')->default(1)->after('min_level');
            }
            if (! Schema::hasColumn('skills_catalog', 'target_rule')) {
                $table->string('target_rule', 32)->nullable()->after('max_level');
            }
            if (! Schema::hasColumn('skills_catalog', 'cd_sec')) {
                $table->float('cd_sec')->default(0)->after('target_rule');
            }
            if (! Schema::hasColumn('skills_catalog', 'cost_qi')) {
                $table->unsignedInteger('cost_qi')->default(0)->after('cd_sec');
            }
            if (! Schema::hasColumn('skills_catalog', 'cast_range')) {
                $table->float('cast_range')->nullable()->after('cost_qi');
            }
            if (! Schema::hasColumn('skills_catalog', 'tags')) {
                $table->json('tags')->nullable()->after('cast_range');
            }
            if (! Schema::hasColumn('skills_catalog', 'runtime_blocks')) {
                $table->json('runtime_blocks')->nullable()->after('tags');
            }
        });
    }

    public function down(): void
    {
        Schema::table('skills_catalog', function (Blueprint $table): void {
            $columns = [
                'type',
                'min_level',
                'max_level',
                'target_rule',
                'cd_sec',
                'cost_qi',
                'cast_range',
                'tags',
                'runtime_blocks',
            ];

            $existing = array_values(array_filter(
                $columns,
                static fn (string $column): bool => Schema::hasColumn('skills_catalog', $column)
            ));

            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });
    }
};
