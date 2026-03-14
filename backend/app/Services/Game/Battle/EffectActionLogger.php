<?php

namespace App\Services\Game\Battle;

class EffectActionLogger
{
    /**
     * @param  array<string, mixed>  $runtimeState
     */
    public function logEffectApply(
        array $runtimeState,
        int $tick,
        string $actorUnitId,
        string $effectKey,
        mixed $value,
        string $source,
    ): array {
        $safeActorUnitId = trim($actorUnitId);
        $safeEffectKey = trim($effectKey);
        $safeSource = trim($source);

        if ($safeActorUnitId === '' || $safeEffectKey === '' || $safeSource === '') {
            return $this->failure('invalid_effect_apply_log_context');
        }

        $runtimeState['logs'] = is_array($runtimeState['logs'] ?? null) ? array_values($runtimeState['logs']) : [];

        $log = [
            'tick' => max(0, $tick),
            'actor' => $safeActorUnitId,
            'action' => 'effect_apply',
            'effect_key' => $safeEffectKey,
            'value' => $value,
            'source' => $safeSource,
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
