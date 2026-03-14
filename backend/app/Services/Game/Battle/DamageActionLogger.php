<?php

namespace App\Services\Game\Battle;

class DamageActionLogger
{
    /**
     * @param  array<string, mixed>  $runtimeState
     */
    public function logDamage(
        array $runtimeState,
        int $tick,
        string $actorUnitId,
        string $targetUnitId,
        string $actionType,
        ?string $skillId,
        int|float $rawDamage,
        bool $isCritical,
        int|float $shieldAbsorbed,
        int|float $hpDamage,
        array $extraFields = [],
    ): array {
        $safeActorUnitId = trim($actorUnitId);
        $safeTargetUnitId = trim($targetUnitId);
        $safeActionType = trim($actionType);
        $safeSkillId = trim((string) $skillId);

        if ($safeActorUnitId === '' || $safeTargetUnitId === '' || $safeActionType === '') {
            return $this->failure('invalid_damage_log_context');
        }

        if ($safeActionType === 'skill_cast' && $safeSkillId === '') {
            return $this->failure('invalid_damage_log_skill_id');
        }

        $runtimeState['logs'] = is_array($runtimeState['logs'] ?? null) ? array_values($runtimeState['logs']) : [];

        $log = [
            'tick' => max(0, $tick),
            'actor' => $safeActorUnitId,
            'target' => $safeTargetUnitId,
            'action' => $safeActionType,
            'skill_id' => $safeSkillId === '' ? null : $safeSkillId,
            'raw_damage' => $this->normalizeNumber(max(0.0, (float) $rawDamage)),
            'is_critical' => $isCritical,
            'shield_absorbed' => $this->normalizeNumber(max(0.0, (float) $shieldAbsorbed)),
            'hp_damage' => $this->normalizeNumber(max(0.0, (float) $hpDamage)),
        ];

        foreach ($extraFields as $fieldKey => $fieldValue) {
            $safeFieldKey = trim((string) $fieldKey);
            if ($safeFieldKey === '' || array_key_exists($safeFieldKey, $log)) {
                continue;
            }

            $log[$safeFieldKey] = $fieldValue;
        }

        $runtimeState['logs'][] = $log;

        return $this->success([
            'runtime_state' => $runtimeState,
            'log' => $log,
        ]);
    }

    private function normalizeNumber(float $value): int|float
    {
        $rounded = round($value, 3);

        if (abs($rounded - round($rounded)) < 0.000001) {
            return (int) round($rounded);
        }

        return $rounded;
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
