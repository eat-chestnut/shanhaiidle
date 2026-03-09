<?php

namespace Database\Seeders;

use App\Models\Stage;
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

        if (! is_array($stages)) {
            $stages = [];
        }
        if ($stages === []) {
            $stages = $this->defaultStages();
        }

        foreach ($stages as $index => $stage) {
            if (! is_array($stage)) {
                continue;
            }

            $id = (string) ($stage['id'] ?? '');
            if ($id === '') {
                continue;
            }

            Stage::query()->updateOrCreate(
                ['id' => $id],
                [
                    'name' => (string) ($stage['name'] ?? $id),
                    'unlock_min_level' => (int) ($stage['unlock_min_level'] ?? 1),
                    'elite_every_kills' => (int) ($stage['elite_every_kills'] ?? 40),
                    'boss_every_kills' => (int) ($stage['boss_every_kills'] ?? 120),
                    'monsters_patch' => $this->normalizeMonstersPatch($stage['monsters'] ?? null),
                    'spawn_patch' => is_array($stage['spawn_patch'] ?? null) ? $stage['spawn_patch'] : null,
                    'drops_patch' => is_array($stage['drops_patch'] ?? null) ? $stage['drops_patch'] : null,
                    'difficulties' => is_array($stage['difficulties'] ?? null) ? $stage['difficulties'] : null,
                    'sort_order' => (int) ($stage['sort_order'] ?? $index),
                    'is_enabled' => (bool) ($stage['is_enabled'] ?? true),
                ]
            );
        }
    }

    protected function defaultStages(): array
    {
        $make = function (
            string $id,
            string $name,
            int $unlock,
            int $eliteEvery,
            int $bossEvery,
            float $respawn,
            int $maxAlive,
            int $radius,
            int $w,
            int $b,
            int $g,
            float $normalGem,
            float $elitePunch,
            float $eliteGem,
            float $bossPunch,
            float $bossGem
        ): array {
            return [
                'id' => $id,
                'name' => $name,
                'unlock_min_level' => $unlock,
                'elite_every_kills' => $eliteEvery,
                'boss_every_kills' => $bossEvery,
                'monsters' => [
                    'normal_pool' => [['id' => 'mob_a', 'w' => 100]],
                    'elite_pool' => [['id' => 'elite_a', 'w' => 100]],
                    'boss_pool' => [['id' => 'boss_a', 'w' => 100]],
                ],
                'spawn_patch' => [
                    'respawn_s' => $respawn,
                    'max_alive' => $maxAlive,
                    'spawn_radius' => $radius,
                ],
                'drops_patch' => [
                    'drop_chance' => 0.28,
                    'rarity_weights' => [
                        'white' => $w,
                        'blue' => $b,
                        'gold' => $g,
                    ],
                    'items_by_rarity' => [
                        'white' => ['桂枝', '玉屑'],
                        'blue' => ['白玉碎'],
                        'gold' => ['妖核'],
                    ],
                    'special' => [
                        'normal' => [
                            'extra_gem_chance' => $normalGem,
                            'extra_gems' => ['赤晶石', '沧澜石', '青木石'],
                        ],
                        'elite' => [
                            'punch_stone_chance' => $elitePunch,
                            'extra_gem_chance' => $eliteGem,
                            'extra_gems' => ['赤晶石', '沧澜石', '青木石'],
                        ],
                        'boss' => [
                            'core_guarantee' => '妖王核心',
                            'punch_stone_chance' => $bossPunch,
                            'extra_gem_chance' => $bossGem,
                            'extra_gems' => ['赤晶石', '沧澜石', '青木石'],
                        ],
                    ],
                ],
                'difficulties' => $this->defaultDifficulties(),
                'is_enabled' => true,
            ];
        };

        return [
            $make('nan_01', '南山·招摇', 1, 40, 120, 1.70, 4, 220, 90, 9, 1, 0.02, 0.12, 0.06, 0.35, 0.18),
            $make('nan_02', '南山·青丘', 5, 36, 108, 1.65, 5, 228, 88, 10, 2, 0.02, 0.14, 0.07, 0.39, 0.20),
            $make('nan_03', '南山·涂山', 9, 34, 102, 1.58, 6, 236, 85, 13, 2, 0.025, 0.16, 0.08, 0.43, 0.22),
            $make('nan_04', '南山·丹穴', 13, 32, 96, 1.52, 7, 244, 83, 15, 2, 0.025, 0.18, 0.09, 0.47, 0.24),
            $make('nan_05', '南山·诸沃', 19, 30, 90, 1.46, 8, 252, 80, 17, 3, 0.03, 0.20, 0.10, 0.51, 0.26),
            $make('nan_06', '南山·堂庭', 25, 28, 84, 1.40, 9, 260, 78, 19, 3, 0.03, 0.22, 0.12, 0.55, 0.28),
        ];
    }

    private function defaultDifficulties(): array
    {
        return [
            [
                'name' => '普通',
                'unlock' => [
                    'boss_kills_required' => 0,
                    'material_cost' => [],
                    'reward' => ['gold' => 0, 'skill_points' => 0, 'items' => []],
                ],
                'first_clear_reward' => ['gold' => 0, 'skill_points' => 0, 'items' => []],
                'recommend_score' => 0,
                'monster_mult' => ['hp' => 1.0, 'atk' => 1.0, 'def' => 1.0],
                'drops_override' => [],
            ],
            [
                'name' => '困难I',
                'unlock' => [
                    'boss_kills_required' => 3,
                    'material_cost' => ['玉屑' => 20, '桂枝' => 20],
                    'reward' => ['gold' => 80, 'skill_points' => 1, 'items' => ['玉屑' => 10]],
                ],
                'first_clear_reward' => ['gold' => 120, 'skill_points' => 1, 'items' => ['玉屑' => 15]],
                'recommend_score' => 900,
                'monster_mult' => ['hp' => 1.4, 'atk' => 1.2, 'def' => 1.2],
                'drops_override' => [
                    'rarity_weights' => ['white' => 78, 'blue' => 19, 'gold' => 3],
                ],
            ],
            [
                'name' => '困难II',
                'unlock' => [
                    'boss_kills_required' => 10,
                    'material_cost' => ['白玉碎' => 12, '妖核' => 2],
                    'reward' => ['gold' => 150, 'skill_points' => 1, 'items' => ['白玉碎' => 8, '妖核' => 1]],
                ],
                'first_clear_reward' => ['gold' => 240, 'skill_points' => 1, 'items' => ['白玉碎' => 10, '妖核' => 1]],
                'recommend_score' => 1600,
                'monster_mult' => ['hp' => 1.8, 'atk' => 1.35, 'def' => 1.35],
                'drops_override' => [
                    'rarity_weights' => ['white' => 70, 'blue' => 24, 'gold' => 6],
                    'special' => [
                        'elite' => [
                            'punch_stone_chance' => 0.22,
                            'extra_gem_chance' => 0.12,
                            'extra_gems' => ['赤晶石', '沧澜石', '青木石'],
                        ],
                        'boss' => [
                            'core_guarantee' => '妖王核心',
                            'punch_stone_chance' => 0.55,
                            'extra_gem_chance' => 0.30,
                            'extra_gems' => ['赤晶石', '沧澜石', '青木石'],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function normalizeMonstersPatch(mixed $monsters): array
    {
        $fallback = [
            'normal_pool' => [['id' => 'mob_a', 'w' => 100]],
            'elite_pool' => [['id' => 'elite_a', 'w' => 100]],
            'boss_pool' => [['id' => 'boss_a', 'w' => 100]],
        ];

        if (! is_array($monsters)) {
            return $fallback;
        }

        $normalPool = $this->normalizePoolRows($monsters['normal_pool'] ?? null, (string) ($monsters['normal'] ?? ''));
        $elitePool = $this->normalizePoolRows($monsters['elite_pool'] ?? null, (string) ($monsters['elite'] ?? ''));
        $bossPool = $this->normalizePoolRows($monsters['boss_pool'] ?? null, (string) ($monsters['boss'] ?? ''));

        return [
            'normal_pool' => $this->poolOrFallback($normalPool, $fallback['normal_pool']),
            'elite_pool' => $this->poolOrFallback($elitePool, $fallback['elite_pool']),
            'boss_pool' => $this->poolOrFallback($bossPool, $fallback['boss_pool']),
        ];
    }

    private function normalizePoolRows(mixed $poolRaw, string $legacyId = ''): array
    {
        $rows = [];
        if (is_array($poolRaw)) {
            foreach ($poolRaw as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $id = trim((string) ($row['id'] ?? ''));
                if ($id === '') {
                    continue;
                }
                $rows[] = [
                    'id' => $id,
                    'w' => max(0, (int) ($row['w'] ?? 0)),
                ];
            }
        }

        $legacyId = trim($legacyId);
        if ($rows === [] && $legacyId !== '') {
            $rows[] = ['id' => $legacyId, 'w' => 100];
        }

        return array_values($rows);
    }

    private function poolOrFallback(array $pool, array $fallback): array
    {
        if ($pool === []) {
            return $fallback;
        }
        $sum = 0;
        foreach ($pool as $row) {
            if (! is_array($row)) {
                continue;
            }
            $sum += max(0, (int) ($row['w'] ?? 0));
        }

        return $sum > 0 ? $pool : $fallback;
    }
}
