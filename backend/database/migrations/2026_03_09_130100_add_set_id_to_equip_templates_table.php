<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equip_templates', function (Blueprint $table): void {
            if (! Schema::hasColumn('equip_templates', 'set_id')) {
                $table->string('set_id', 64)->nullable()->index()->after('icon');
            }
        });
    }

    public function down(): void
    {
        Schema::table('equip_templates', function (Blueprint $table): void {
            if (Schema::hasColumn('equip_templates', 'set_id')) {
                $table->dropIndex(['set_id']);
                $table->dropColumn('set_id');
            }
        });
    }
};

