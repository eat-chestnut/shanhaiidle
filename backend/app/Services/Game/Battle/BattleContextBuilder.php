<?php

namespace App\Services\Game\Battle;

use App\Models\MainStageDifficulty;

class BattleContextBuilder
{
    public function build(array $stageContext): array
    {
        $battleType = trim((string) ($stageContext['battle_type'] ?? 'main_stage'));
        $difficultyId = trim((string) ($stageContext['difficulty_id'] ?? ''));
        $stageId = trim((string) ($stageContext['stage_id'] ?? ''));

        $difficulty = null;
        if ($battleType === 'main_stage' && $difficultyId !== '') {
            $difficulty = MainStageDifficulty::query()
                ->with('chapter')
                ->where('difficulty_id', $difficultyId)
                ->where('is_enabled', true)
                ->first();
        }

        if ($difficulty instanceof MainStageDifficulty) {
            $stageId = $stageId !== '' ? $stageId : (string) $difficulty->chapter_id;
        }

        if ($battleType === '' || $stageId === '' || $difficultyId === '') {
            return $this->failure('invalid_stage_context');
        }

        $recommendedPower = array_key_exists('recommended_power', $stageContext)
            ? max(0, (int) ($stageContext['recommended_power'] ?? 0))
            : (int) ($difficulty?->chapter?->suggested_power ?? 0);

        return $this->success([
            'battle_type' => $battleType,
            'stage_id' => $stageId,
            'difficulty_id' => $difficultyId,
            'recommended_power' => $recommendedPower,
            'is_boss_battle' => (bool) ($stageContext['is_boss_battle'] ?? false),
            'scene_id' => trim((string) ($stageContext['scene_id'] ?? $stageId)),
            'settlement_mode' => trim((string) ($stageContext['settlement_mode'] ?? 'normal')),
            'version' => trim((string) ($stageContext['version'] ?? 'v1')),
        ]);
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
