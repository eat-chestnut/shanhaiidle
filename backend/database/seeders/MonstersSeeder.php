<?php

namespace Database\Seeders;

use App\Models\Monster;
use App\Support\MonsterDropSupport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class MonstersSeeder extends Seeder
{
    public function run(): void
    {
        $sourcePath = base_path('../data/monsters.json');
        $rows = [];

        if (File::exists($sourcePath)) {
            $parsed = json_decode(File::get($sourcePath), true);
            $rows = data_get($parsed, 'monsters', []);
        }

        if (! is_array($rows) || $rows === []) {
            $rows = $this->defaultMonsters();
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = trim((string) ($row['id'] ?? ''));
            if ($id === '') {
                continue;
            }

            Monster::query()->updateOrCreate(
                ['id' => $id],
                [
                    'name' => (string) ($row['name'] ?? $id),
                    'kind' => (string) ($row['kind'] ?? 'normal'),
                    'hp' => (int) ($row['hp'] ?? 1),
                    'atk' => (int) ($row['atk'] ?? 0),
                    'def' => (int) ($row['def'] ?? 0),
                    'speed' => (int) ($row['speed'] ?? 0),
                    'radius' => (int) ($row['radius'] ?? 0),
                    'aggro_range' => (int) ($row['aggro_range'] ?? 0),
                    'attack_interval' => (float) ($row['attack_interval'] ?? 1.0),
                    'attack_range' => (int) ($row['attack_range'] ?? 0),
                    'exp' => (int) ($row['exp'] ?? 0),
                    'drop_bonus_percent' => (int) ($row['drop_bonus_percent'] ?? 0),
                    'dex_gold' => (int) ($row['dex_gold'] ?? 0),
                    'icon' => (string) ($row['icon'] ?? ''),
                    'drops' => MonsterDropSupport::normalizeDrops($row['drops'] ?? []),
                    'is_enabled' => (bool) ($row['is_enabled'] ?? true),
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                ],
            );
        }
    }

    private function defaultMonsters(): array
    {
        return [
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
                'drops' => [
                    ['item_id' => '桂枝', 'count_min' => 1, 'count_max' => 2, 'drop_rate' => 0.45, 'is_enabled' => true, 'sort' => 1],
                ],
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
                'drops' => [
                    ['item_id' => '白玉碎', 'count_min' => 1, 'count_max' => 2, 'drop_rate' => 0.55, 'is_enabled' => true, 'sort' => 1],
                    ['item_id' => '打孔石', 'count_min' => 1, 'count_max' => 1, 'drop_rate' => 0.15, 'is_enabled' => true, 'sort' => 2],
                ],
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
                'drops' => [
                    ['item_id' => '妖核', 'count_min' => 1, 'count_max' => 1, 'drop_rate' => 1, 'is_enabled' => true, 'sort' => 1],
                    ['item_id' => '白玉碎', 'count_min' => 2, 'count_max' => 4, 'drop_rate' => 0.75, 'is_enabled' => true, 'sort' => 2],
                ],
                'is_enabled' => true,
                'sort_order' => 30,
            ],
        ];
    }
}
