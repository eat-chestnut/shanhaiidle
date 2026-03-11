<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
