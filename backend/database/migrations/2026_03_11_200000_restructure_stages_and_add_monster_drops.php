<?php

use App\Support\MonsterDropSupport;
use App\Support\StageConfigSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stages')) {
            $stageRows = DB::table('stages')->get();

            foreach ($stageRows as $stageRow) {
                $spawnPatch = $this->decodeJson($stageRow->spawn_patch ?? null);
                $monstersPatch = $this->decodeJson($stageRow->monsters_patch ?? null);
                $oldDifficulties = $this->decodeJson($stageRow->difficulties ?? null);

                $normalized = [];
                foreach (is_array($oldDifficulties) ? $oldDifficulties : [] as $index => $difficulty) {
                    if (! is_array($difficulty)) {
                        continue;
                    }

                    $normalized[] = [
                        'difficulty_name' => trim((string) ($difficulty['difficulty_name'] ?? $difficulty['name'] ?? '')) ?: ($index === 0 ? '普通' : '难度' . ($index + 1)),
                        'recommended_power' => max(0, (int) ($difficulty['recommended_power'] ?? $difficulty['recommend_score'] ?? 0)),
                        'spawn_interval' => is_numeric($spawnPatch['respawn_s'] ?? null) ? (float) $spawnPatch['respawn_s'] : 1.6,
                        'onscreen_limit' => max(1, (int) ($spawnPatch['max_alive'] ?? 10)),
                        'spawn_radius' => max(1, (int) ($spawnPatch['spawn_radius'] ?? 220)),
                        'normal_monsters' => $this->normalizePool($monstersPatch['normal_pool'] ?? null, 'mob_a'),
                        'elite_monsters' => $this->normalizePool($monstersPatch['elite_pool'] ?? null, 'elite_a'),
                        'boss_monsters' => $this->normalizePool($monstersPatch['boss_pool'] ?? null, 'boss_a'),
                        'elite_spawn_rule' => null,
                        'boss_spawn_rule' => null,
                    ];
                }

                if ($normalized === []) {
                    $normalized = StageConfigSupport::defaultDifficulties();
                }

                DB::table('stages')
                    ->where('id', $stageRow->id)
                    ->update([
                        'difficulties' => json_encode(StageConfigSupport::normalizeDifficulties($normalized), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]);
            }

            Schema::table('stages', function (Blueprint $table): void {
                foreach (['elite_every_kills', 'boss_every_kills', 'spawn_patch', 'drops_patch', 'monsters_patch'] as $column) {
                    if (Schema::hasColumn('stages', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('monsters')) {
            if (! Schema::hasColumn('monsters', 'drops')) {
                Schema::table('monsters', function (Blueprint $table): void {
                    $table->json('drops')->nullable()->after('icon');
                });
            }

            $monsterRows = DB::table('monsters')->get(['id', 'drops']);
            foreach ($monsterRows as $monsterRow) {
                DB::table('monsters')
                    ->where('id', $monsterRow->id)
                    ->update([
                        'drops' => json_encode(MonsterDropSupport::normalizeDrops($this->decodeJson($monsterRow->drops ?? null)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('monsters') && Schema::hasColumn('monsters', 'drops')) {
            Schema::table('monsters', function (Blueprint $table): void {
                $table->dropColumn('drops');
            });
        }

        if (Schema::hasTable('stages')) {
            Schema::table('stages', function (Blueprint $table): void {
                if (! Schema::hasColumn('stages', 'elite_every_kills')) {
                    $table->unsignedInteger('elite_every_kills')->default(40)->after('unlock_min_level');
                }
                if (! Schema::hasColumn('stages', 'boss_every_kills')) {
                    $table->unsignedInteger('boss_every_kills')->default(120)->after('elite_every_kills');
                }
                if (! Schema::hasColumn('stages', 'spawn_patch')) {
                    $table->json('spawn_patch')->nullable()->after('boss_every_kills');
                }
                if (! Schema::hasColumn('stages', 'drops_patch')) {
                    $table->json('drops_patch')->nullable()->after('spawn_patch');
                }
                if (! Schema::hasColumn('stages', 'monsters_patch')) {
                    $table->json('monsters_patch')->nullable()->after('drops_patch');
                }
            });
        }
    }

    private function decodeJson(mixed $value): mixed
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    private function normalizePool(mixed $raw, string $fallbackId): array
    {
        $rows = [];

        foreach (is_array($raw) ? $raw : [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $monsterId = trim((string) ($row['monster_id'] ?? $row['id'] ?? ''));
            $weight = max(0, (int) ($row['weight'] ?? $row['w'] ?? 0));

            if ($monsterId === '') {
                continue;
            }

            $rows[] = [
                'monster_id' => $monsterId,
                'weight' => $weight > 0 ? $weight : 100,
            ];
        }

        return $rows !== [] ? array_values($rows) : [['monster_id' => $fallbackId, 'weight' => 100]];
    }
};
