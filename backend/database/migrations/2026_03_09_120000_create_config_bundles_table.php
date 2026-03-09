<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('config_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('bundle_id', 64)->unique();
            $table->string('manifest_path', 255);
            $table->string('sha256', 64);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('config_bundles');
    }
};

