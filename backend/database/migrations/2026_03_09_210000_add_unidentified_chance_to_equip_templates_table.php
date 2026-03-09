<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equip_templates', function (Blueprint $table): void {
            if (! Schema::hasColumn('equip_templates', 'unidentified_chance')) {
                $table->decimal('unidentified_chance', 6, 3)
                    ->default(0)
                    ->after('main_max');
            }
        });
    }

    public function down(): void
    {
        Schema::table('equip_templates', function (Blueprint $table): void {
            if (Schema::hasColumn('equip_templates', 'unidentified_chance')) {
                $table->dropColumn('unidentified_chance');
            }
        });
    }
};

