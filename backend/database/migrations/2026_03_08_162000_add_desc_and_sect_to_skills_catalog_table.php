<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skills_catalog', function (Blueprint $table): void {
            if (! Schema::hasColumn('skills_catalog', 'desc')) {
                $table->text('desc')->nullable()->after('name');
            }
            if (! Schema::hasColumn('skills_catalog', 'sect')) {
                $table->string('sect')->nullable()->after('desc');
            }
        });

        if (Schema::hasColumn('skills_catalog', 'class') && Schema::hasColumn('skills_catalog', 'sect')) {
            DB::table('skills_catalog')
                ->whereNull('sect')
                ->update(['sect' => DB::raw('"class"')]);
        }
    }

    public function down(): void
    {
        Schema::table('skills_catalog', function (Blueprint $table): void {
            if (Schema::hasColumn('skills_catalog', 'sect')) {
                $table->dropColumn('sect');
            }
            if (Schema::hasColumn('skills_catalog', 'desc')) {
                $table->dropColumn('desc');
            }
        });
    }
};
