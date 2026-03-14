<?php

namespace App\Services\Game\Battle;

class BattleStartPayloadBuilder
{
    public function __construct(
        private readonly PlayerBattleSnapshotBuilder $playerBattleSnapshotBuilder = new PlayerBattleSnapshotBuilder(),
        private readonly EnemyBattleSnapshotBuilder $enemyBattleSnapshotBuilder = new EnemyBattleSnapshotBuilder(),
        private readonly BattleContextBuilder $battleContextBuilder = new BattleContextBuilder(),
    ) {
    }

    public function build(int|string $playerId, array $stageContext): array
    {
        $playerSnapshotResult = $this->playerBattleSnapshotBuilder->build($playerId);
        if (! ($playerSnapshotResult['ok'] ?? false)) {
            return $this->failure((string) ($playerSnapshotResult['reason'] ?? 'player_snapshot_build_failed'));
        }

        $enemySnapshotResult = $this->enemyBattleSnapshotBuilder->build(
            $stageContext,
            is_array($stageContext['monster_configs'] ?? null) ? $stageContext['monster_configs'] : null,
        );
        if (! ($enemySnapshotResult['ok'] ?? false)) {
            return $this->failure((string) ($enemySnapshotResult['reason'] ?? 'enemy_snapshot_build_failed'));
        }

        $battleContextResult = $this->battleContextBuilder->build($stageContext);
        if (! ($battleContextResult['ok'] ?? false)) {
            return $this->failure((string) ($battleContextResult['reason'] ?? 'battle_context_build_failed'));
        }

        return $this->success([
            'player_snapshot' => $playerSnapshotResult['data'],
            'enemy_snapshots' => $enemySnapshotResult['data'],
            'battle_context' => $battleContextResult['data'],
            'debug_sources' => $this->buildDebugSources($stageContext),
        ]);
    }

    /**
     * @return array<int, array{type:string,source:string}>
     */
    private function buildDebugSources(array $stageContext): array
    {
        $sources = [
            'player_equipment:player_equipment_instances' => [
                'type' => 'player_equipment',
                'source' => 'player_equipment_instances',
            ],
            'player_loadout:player_equipment_loadouts' => [
                'type' => 'player_loadout',
                'source' => 'player_equipment_loadouts',
            ],
            'set_effect:equipment_set_effects' => [
                'type' => 'set_effect',
                'source' => 'equipment_set_effects',
            ],
            'monster_config:stage_difficulty_monsters' => [
                'type' => 'monster_config',
                'source' => 'stage_difficulty_monsters',
            ],
            'monster:monsters' => [
                'type' => 'monster',
                'source' => 'monsters',
            ],
        ];

        $battleType = trim((string) ($stageContext['battle_type'] ?? 'main_stage'));
        $sources['battle_context:'.$battleType] = [
            'type' => 'battle_context',
            'source' => $battleType === 'main_stage' ? 'main_stage_difficulties' : 'stage_context',
        ];

        return array_values($sources);
    }

    private function success(array $data): array
    {
        return [
            'ok' => true,
            'reason' => null,
            'data' => $data,
        ];
    }

    private function failure(string $reason): array
    {
        return [
            'ok' => false,
            'reason' => $reason,
            'data' => null,
        ];
    }
}
