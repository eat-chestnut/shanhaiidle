<?php

namespace App\Services\Game\Battle;

class DotHotEffectLogger
{
    /**
     * @param  array<string, mixed>  $runtimeState
     */
    public function logTick(
        array $runtimeState,
        int $tick,
        string $actorUnitId,
        string $targetUnitId,
        string $effectKey,
        string $effectType,
        int|float $hpDamage,
        int|float $hpHealed,
    ): array {
        $safeActorUnitId = trim($actorUnitId);
        $safeTargetUnitId = trim($targetUnitId);
        $safeEffectKey = trim($effectKey);
        $safeEffectType = trim($effectType);

        if ($safeActorUnitId === '' || $safeTargetUnitId === '' || $safeEffectKey === '' || $safeEffectType === '') {
            return $this->failure('invalid_dot_hot_log_context');
        }

        $runtimeState['logs'] = is_array($runtimeState['logs'] ?? null) ? array_values($runtimeState['logs']) : [];

        $log = [
            'tick' => max(0, $tick),
            'actor' => $safeActorUnitId,
            'target' => $safeTargetUnitId,
            'action' => 'dot_hot_tick',
            'effect_key' => $safeEffectKey,
            'effect_type' => $safeEffectType,
            'hp_damage' => $this->normalizeNumber(max(0.0, (float) $hpDamage)),
            'hp_healed' => $this->normalizeNumber(max(0.0, (float) $hpHealed)),
        ];

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
