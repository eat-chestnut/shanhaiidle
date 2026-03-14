<?php

namespace App\Services\Game\Battle;

class SkillActionLogger
{
    /**
     * @param  array<string, mixed>  $runtimeState
     */
    public function logSkillCast(
        array $runtimeState,
        int $tick,
        string $actorUnitId,
        string $targetUnitId,
        string $skillId,
        int $damage,
    ): array {
        $safeActorUnitId = trim($actorUnitId);
        $safeTargetUnitId = trim($targetUnitId);
        $safeSkillId = trim($skillId);

        if ($safeActorUnitId === '' || $safeTargetUnitId === '' || $safeSkillId === '') {
            return $this->failure('invalid_skill_cast_log_context');
        }

        $runtimeState['logs'] = is_array($runtimeState['logs'] ?? null) ? array_values($runtimeState['logs']) : [];

        $log = [
            'tick' => max(0, $tick),
            'actor' => $safeActorUnitId,
            'target' => $safeTargetUnitId,
            'action' => 'skill_cast',
            'skill_id' => $safeSkillId,
            'damage' => max(1, $damage),
        ];

        $runtimeState['logs'][] = $log;

        return $this->success([
            'runtime_state' => $runtimeState,
            'log' => $log,
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
