<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stages', function (Blueprint $table) {
            if (! Schema::hasColumn('stages', 'monsters_patch')) {
                $table->json('monsters_patch')->nullable()->after('drops_patch');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stages', function (Blueprint $table) {
            if (Schema::hasColumn('stages', 'monsters_patch')) {
                $table->dropColumn('monsters_patch');
            }
        });
    }
};
