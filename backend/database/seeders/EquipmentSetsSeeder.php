<?php

namespace Database\Seeders;

use App\Models\EquipmentSet;
use Illuminate\Database\Seeder;

class EquipmentSetsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'id' => 'set_zhaoyao',
                'name' => '招摇遗甲',
                'max_pieces' => 6,
                'thresholds' => [
                    [
                        'count' => 2,
                        'bonuses' => [
                            ['type' => 'stat', 'stat' => 'ATK', 'val' => 2],
                        ],
                    ],
                    [
                        'count' => 4,
                        'bonuses' => [
                            ['type' => 'stat', 'stat' => 'DEF', 'val' => 2],
                        ],
                    ],
                    [
                        'count' => 6,
                        'bonuses' => [
                            ['type' => 'stat', 'stat' => 'CRIT_PERCENT', 'val' => 2],
                        ],
                    ],
                ],
                'is_enabled' => true,
                'sort_order' => 10,
            ],
            [
                'id' => 'set_qingqiu',
                'name' => '青丘灵饰',
                'max_pieces' => 4,
                'thresholds' => [
                    [
                        'count' => 2,
                        'bonuses' => [
                            ['type' => 'stat', 'stat' => 'LOOT_BONUS_PERCENT', 'val' => 2],
                        ],
                    ],
                    [
                        'count' => 4,
                        'bonuses' => [
                            ['type' => 'stat', 'stat' => 'HP', 'val' => 4],
                        ],
                    ],
                ],
                'is_enabled' => true,
                'sort_order' => 20,
            ],
        ];

        foreach ($rows as $row) {
            EquipmentSet::query()->updateOrCreate(
                ['id' => $row['id']],
                [
                    'name' => $row['name'],
                    'max_pieces' => $row['max_pieces'],
                    'thresholds' => $row['thresholds'],
                    'is_enabled' => $row['is_enabled'],
                    'sort_order' => $row['sort_order'],
                ]
            );
        }
    }
}

