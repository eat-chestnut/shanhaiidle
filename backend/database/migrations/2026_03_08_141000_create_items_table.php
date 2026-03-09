<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('type');
            $table->string('rarity');
            $table->string('icon')->nullable();
            $table->text('trait')->nullable();
            $table->json('gem_effect')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        if (! Schema::hasTable('items_catalog')) {
            return;
        }

        $rows = DB::table('items_catalog')
            ->orderBy('id')
            ->get();

        $now = now();
        $insert = [];
        foreach ($rows as $index => $row) {
            $insert[] = [
                'id' => (string) $row->id,
                'name' => (string) $row->name,
                'type' => (string) $row->type,
                'rarity' => (string) $row->rarity,
                'icon' => null,
                'trait' => null,
                'gem_effect' => null,
                'is_enabled' => true,
                'sort_order' => $index,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($insert !== []) {
            DB::table('items')->upsert(
                $insert,
                ['id'],
                [
                    'name',
                    'type',
                    'rarity',
                    'icon',
                    'trait',
                    'gem_effect',
                    'is_enabled',
                    'sort_order',
                    'updated_at',
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
