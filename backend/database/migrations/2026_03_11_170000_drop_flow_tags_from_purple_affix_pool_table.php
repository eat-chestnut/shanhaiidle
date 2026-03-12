<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purple_affix_pool') || ! Schema::hasColumn('purple_affix_pool', 'flow_tags')) {
            return;
        }

        Schema::table('purple_affix_pool', function (Blueprint $table): void {
            $table->dropColumn('flow_tags');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('purple_affix_pool') || Schema::hasColumn('purple_affix_pool', 'flow_tags')) {
            return;
        }

        Schema::table('purple_affix_pool', function (Blueprint $table): void {
            $table->json('flow_tags')->nullable()->after('slot_tags');
        });
    }
};
