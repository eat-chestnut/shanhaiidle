<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stages', function (Blueprint $table) {
            if (! Schema::hasColumn('stages', 'difficulties')) {
                $table->json('difficulties')->nullable()->after('monsters_patch');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stages', function (Blueprint $table) {
            if (Schema::hasColumn('stages', 'difficulties')) {
                $table->dropColumn('difficulties');
            }
        });
    }
};
