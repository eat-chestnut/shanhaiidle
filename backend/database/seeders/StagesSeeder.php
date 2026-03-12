<?php

namespace Database\Seeders;

use App\Models\Stage;
use App\Support\StageConfigSupport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class StagesSeeder extends Seeder
{
    public function run(): void
    {
        $sourcePath = base_path('../data/stages_v1.json');
        $stages = [];

        if (File::exists($sourcePath)) {
            $parsed = json_decode(File::get($sourcePath), true);
            $stages = data_get($parsed, 'stages', []);
        }

        if (! is_array($stages) || $stages === []) {
            $stages = $this->defaultStages();
        }

        foreach ($stages as $index => $stage) {
            if (! is_array($stage)) {
                continue;
            }

            $id = trim((string) ($stage['id'] ?? ''));
            if ($id === '') {
                continue;
            }

            Stage::query()->updateOrCreate(
                ['id' => $id],
                [
                    'name' => (string) ($stage['name'] ?? $id),
                    'unlock_min_level' => max(1, (int) ($stage['unlock_min_level'] ?? 1)),
                    'difficulties' => StageConfigSupport::normalizeDifficulties($stage['difficulties'] ?? []),
                    'sort_order' => (int) ($stage['sort_order'] ?? $index),
                    'is_enabled' => (bool) ($stage['is_enabled'] ?? true),
                ],
            );
        }
    }

    private function defaultStages(): array
    {
        return [
            [
                'id' => 'nan_01',
                'name' => '南山·招摇',
                'unlock_min_level' => 1,
                'difficulties' => $this->defaultDifficulties(0),
            ],
            [
                'id' => 'nan_02',
                'name' => '南山·青丘',
                'unlock_min_level' => 5,
                'difficulties' => $this->defaultDifficulties(1),
            ],
            [
                'id' => 'nan_03',
                'name' => '南山·涂山',
                'unlock_min_level' => 9,
                'difficulties' => $this->defaultDifficulties(2),
            ],
        ];
    }

    private function defaultDifficulties(int $offset): array
    {
        return [
            [
                'difficulty_name' => '普通',
                'recommended_power' => 400 + ($offset * 300),
                'spawn_interval' => 1.7,
                'onscreen_limit' => 6 + $offset,
                'spawn_radius' => 220 + ($offset * 10),
                'normal_monsters' => [['monster_id' => 'mob_a', 'weight' => 100]],
                'elite_monsters' => [['monster_id' => 'elite_a', 'weight' => 100]],
                'boss_monsters' => [['monster_id' => 'boss_a', 'weight' => 100]],
                'elite_spawn_rule' => null,
                'boss_spawn_rule' => null,
            ],
            [
                'difficulty_name' => '困难',
                'recommended_power' => 700 + ($offset * 350),
                'spawn_interval' => 1.55,
                'onscreen_limit' => 7 + $offset,
                'spawn_radius' => 240 + ($offset * 10),
                'normal_monsters' => [['monster_id' => 'mob_a', 'weight' => 100]],
                'elite_monsters' => [['monster_id' => 'elite_a', 'weight' => 100]],
                'boss_monsters' => [['monster_id' => 'boss_a', 'weight' => 100]],
                'elite_spawn_rule' => null,
                'boss_spawn_rule' => null,
            ],
        ];
    }
}
