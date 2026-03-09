<?php

namespace Database\Seeders;

use App\Models\ItemCatalog;
use Illuminate\Database\Seeder;

class ItemsCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $rows = [
            ['id' => '桂枝', 'name' => '桂枝', 'type' => 'item', 'rarity' => 'white'],
            ['id' => '玉屑', 'name' => '玉屑', 'type' => 'item', 'rarity' => 'white'],
            ['id' => '白玉碎', 'name' => '白玉碎', 'type' => 'item', 'rarity' => 'blue'],
            ['id' => '妖核', 'name' => '妖核', 'type' => 'item', 'rarity' => 'gold'],
            ['id' => '宗门令', 'name' => '宗门令', 'type' => 'item', 'rarity' => 'white'],
            ['id' => '打孔石', 'name' => '打孔石', 'type' => 'item', 'rarity' => 'blue'],
            ['id' => '赤晶石', 'name' => '赤晶石', 'type' => 'gem', 'rarity' => 'blue'],
            ['id' => '沧澜石', 'name' => '沧澜石', 'type' => 'gem', 'rarity' => 'blue'],
            ['id' => '青木石', 'name' => '青木石', 'type' => 'gem', 'rarity' => 'blue'],
            ['id' => '妖王核心', 'name' => '妖王核心', 'type' => 'gem', 'rarity' => 'gold'],
        ];

        foreach ($rows as &$row) {
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
        }

        ItemCatalog::query()->upsert(
            $rows,
            ['id'],
            ['name', 'type', 'rarity', 'updated_at']
        );
    }
}
