<?php

namespace Database\Seeders;

use App\Models\Monster;
use Illuminate\Database\Seeder;

class MonstersSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'id' => 'mob_a',
                'name' => '小妖',
                'kind' => 'normal',
                'hp' => 6,
                'atk' => 1,
                'def' => 0,
                'speed' => 85,
                'radius' => 14,
                'aggro_range' => 220,
                'attack_interval' => 1.10,
                'attack_range' => 8,
                'exp' => 1,
                'drop_bonus_percent' => 0,
                'dex_gold' => 3,
                'icon' => '',
                'is_enabled' => true,
                'sort_order' => 10,
            ],
            [
                'id' => 'elite_a',
                'name' => '精英·小妖',
                'kind' => 'elite',
                'hp' => 18,
                'atk' => 2,
                'def' => 1,
                'speed' => 92,
                'radius' => 16,
                'aggro_range' => 260,
                'attack_interval' => 1.00,
                'attack_range' => 10,
                'exp' => 5,
                'drop_bonus_percent' => 30,
                'dex_gold' => 8,
                'icon' => '',
                'is_enabled' => true,
                'sort_order' => 20,
            ],
            [
                'id' => 'boss_a',
                'name' => 'Boss·妖王',
                'kind' => 'boss',
                'hp' => 45,
                'atk' => 3,
                'def' => 2,
                'speed' => 80,
                'radius' => 20,
                'aggro_range' => 320,
                'attack_interval' => 0.90,
                'attack_range' => 12,
                'exp' => 12,
                'drop_bonus_percent' => 80,
                'dex_gold' => 20,
                'icon' => '',
                'is_enabled' => true,
                'sort_order' => 30,
            ],
        ];

        foreach ($rows as $row) {
            Monster::query()->updateOrCreate(
                ['id' => $row['id']],
                [
                    'name' => $row['name'],
                    'kind' => $row['kind'],
                    'hp' => $row['hp'],
                    'atk' => $row['atk'],
                    'def' => $row['def'],
                    'speed' => $row['speed'],
                    'radius' => $row['radius'],
                    'aggro_range' => $row['aggro_range'],
                    'attack_interval' => $row['attack_interval'],
                    'attack_range' => $row['attack_range'],
                    'exp' => $row['exp'],
                    'drop_bonus_percent' => $row['drop_bonus_percent'],
                    'dex_gold' => $row['dex_gold'],
                    'icon' => $row['icon'],
                    'is_enabled' => $row['is_enabled'],
                    'sort_order' => $row['sort_order'],
                ],
            );
        }
    }
}
