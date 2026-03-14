<?php

namespace App\Services\Game\Battle;

class StatusEffectLogger
{
    /**
     * @param  array<string, mixed>  $runtimeState
     */
    public function logStatus(
        array $runtimeState,
        int $tick,
        string $actorUnitId,
        string $targetUnitId,
        string $effectKey,
        string $status,
        ?bool $statusApplied,
        ?bool $statusActive,
        array $extraFields = [],
    ): array {
        $safeActorUnitId = trim($actorUnitId);
        $safeTargetUnitId = trim($targetUnitId);
        $safeEffectKey = trim($effectKey);
        $safeStatus = trim($status);

        if ($safeActorUnitId === '' || $safeTargetUnitId === '' || $safeEffectKey === '' || $safeStatus === '') {
            return $this->failure('invalid_status_effect_log_context');
        }

        $runtimeState['logs'] = is_array($runtimeState['logs'] ?? null) ? array_values($runtimeState['logs']) : [];

        $log = [
            'tick' => max(0, $tick),
            'actor' => $safeActorUnitId,
            'target' => $safeTargetUnitId,
            'action' => 'status_effect',
            'effect_key' => $safeEffectKey,
            'status' => $safeStatus,
        ];

        if ($statusApplied !== null) {
            $log['status_applied'] = $statusApplied;
        }

        if ($statusActive !== null) {
            $log['status_active'] = $statusActive;
        }

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
