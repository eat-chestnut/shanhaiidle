<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $raw = DB::table('app_settings')
            ->where('key', 'equipment_growth_rules')
            ->value('value');

        if (! is_string($raw) || trim($raw) === '') {
            return;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return;
        }

        $rows = $decoded['star_material_stage'] ?? null;
        $isStructuredList = is_array($rows) && array_is_list($rows) && collect($rows)->every(
            fn ($row): bool => is_array($row) && array_key_exists('star_from', $row) && array_key_exists('star_to', $row) && array_key_exists('material_id', $row) && array_key_exists('material_count', $row)
        );

        if ($isStructuredList) {
            return;
        }

        $decoded['star_material_stage'] = [
            ['star_from' => 1, 'star_to' => 3, 'material_id' => 'star_stone_t1_common', 'material_count' => 1, 'sort' => 1],
            ['star_from' => 4, 'star_to' => 6, 'material_id' => 'star_stone_t2_common', 'material_count' => 1, 'sort' => 2],
            ['star_from' => 7, 'star_to' => 8, 'material_id' => 'star_stone_t3_common', 'material_count' => 1, 'sort' => 3],
            ['star_from' => 9, 'star_to' => 10, 'material_id' => 'star_stone_t4_common', 'material_count' => 1, 'sort' => 4],
        ];

        DB::table('app_settings')
            ->where('key', 'equipment_growth_rules')
            ->update([
                'value' => json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
    }
};
