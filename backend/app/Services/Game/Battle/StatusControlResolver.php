<?php

namespace App\Services\Game\Battle;

class StatusControlResolver
{
    private const SUPPORTED_STATUSES = [
        'stunned',
        'slowed',
        'silenced',
    ];

    /**
     * @param  array<int, array<string, mixed>>  $effects
     */
    public function buildStates(array $effects): array
    {
        $states = [];

        foreach ($effects as $effect) {
            if (! is_array($effect)) {
                continue;
            }

            $effectKey = trim((string) ($effect['effect_key'] ?? ''));
            $ownerUnitId = trim((string) ($effect['owner_unit_id'] ?? ''));
            $targetUnitId = trim((string) ($effect['target_unit_id'] ?? ''));
            $effectType = trim((string) ($effect['effect_type'] ?? ''));
            $triggerTiming = trim((string) ($effect['trigger_timing'] ?? ''));
            $durationTicks = max(0, (int) ($effect['duration_ticks'] ?? 0));
            $status = trim((string) ($effect['status'] ?? ''));

            if (
                $effectKey === ''
                || $ownerUnitId === ''
                || $targetUnitId === ''
                || $effectType !== 'status_control'
                || $triggerTiming !== 'on_apply'
                || $durationTicks <= 0
                || ! in_array($status, self::SUPPORTED_STATUSES, true)
            ) {
                continue;
            }

            $states[] = [
                'effect_key' => $effectKey,
                'owner_unit_id' => $ownerUnitId,
                'target_unit_id' => $targetUnitId,
                'effect_type' => $effectType,
                'trigger_timing' => $triggerTiming,
                'duration_ticks' => $durationTicks,
                'status' => $status,
                'remaining_ticks' => $durationTicks,
                'applied' => false,
                'resolved' => false,
            ];
        }

        return $this->success([
            'status_control_states' => array_values($states),
        ]);
    }

    /**
     * @param  array<string, mixed>  $runtimeState
     */
    public function tick(array $runtimeState, int $tick): array
    {
        $runtimeState['status_control_states'] = $this->normalizeStates($runtimeState['status_control_states'] ?? []);
        $runtimeState['player_unit'] = $this->normalizeUnit($runtimeState['player_unit'] ?? []);
        $runtimeState['enemy_units'] = $this->normalizeUnits($runtimeState['enemy_units'] ?? []);

        $runtimeState['player_unit']['status'] = null;
        foreach ($runtimeState['enemy_units'] as $enemyIndex => $enemyUnit) {
            $runtimeState['enemy_units'][$enemyIndex]['status'] = null;
        }

        $tickResults = [];

        foreach ($runtimeState['status_control_states'] as $stateIndex => $state) {
            $remainingTicks = max(0, (int) ($state['remaining_ticks'] ?? 0));
            $resolved = (bool) ($state['resolved'] ?? false);
            $status = trim((string) ($state['status'] ?? ''));

            if ($remainingTicks > 0) {
                $this->applyStatusToTarget($runtimeState, trim((string) ($state['target_unit_id'] ?? '')), $status);

                $tickResult = [
                    'tick' => max(0, $tick),
                    'effect_key' => trim((string) ($state['effect_key'] ?? '')),
                    'owner_unit_id' => trim((string) ($state['owner_unit_id'] ?? '')),
                    'target_unit_id' => trim((string) ($state['target_unit_id'] ?? '')),
                    'status' => $status,
                ];

                if (($state['applied'] ?? false) !== true) {
                    $tickResult['status_applied'] = true;
                } else {
                    $tickResult['status_active'] = true;
                }

                $runtimeState['status_control_states'][$stateIndex]['applied'] = true;
                $runtimeState['status_control_states'][$stateIndex]['remaining_ticks'] = $remainingTicks - 1;
                $tickResults[] = $tickResult;

                continue;
            }

            if (! $resolved) {
                $tickResults[] = [
                    'tick' => max(0, $tick),
                    'effect_key' => trim((string) ($state['effect_key'] ?? '')),
                    'owner_unit_id' => trim((string) ($state['owner_unit_id'] ?? '')),
                    'target_unit_id' => trim((string) ($state['target_unit_id'] ?? '')),
                    'status' => $status,
                    'status_active' => false,
                ];
                $runtimeState['status_control_states'][$stateIndex]['resolved'] = true;
            }
        }

        return $this->success([
            'runtime_state' => $runtimeState,
            'tick_results' => array_values($tickResults),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeStates(mixed $states): array
    {
        return array_values(array_filter(
            is_array($states) ? $states : [],
            static fn (mixed $state): bool => is_array($state)
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeUnit(mixed $unit): array
    {
        $safeUnit = is_array($unit) ? $unit : [];
        $safeUnit['status'] = is_string($safeUnit['status'] ?? null) ? $safeUnit['status'] : null;

        return $safeUnit;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeUnits(mixed $units): array
    {
        $normalized = [];

        foreach (is_array($units) ? $units : [] as $unit) {
            if (! is_array($unit)) {
                continue;
            }

            $unit['status'] = is_string($unit['status'] ?? null) ? $unit['status'] : null;
            $normalized[] = $unit;
        }

        return array_values($normalized);
    }

    /**
     * @param  array<string, mixed>  $runtimeState
     */
    private function applyStatusToTarget(array &$runtimeState, string $targetUnitId, string $status): void
    {
        if (trim((string) ($runtimeState['player_unit']['unit_id'] ?? '')) === $targetUnitId) {
            $runtimeState['player_unit']['status'] = $status;

            return;
        }

        foreach ($runtimeState['enemy_units'] as $enemyIndex => $enemyUnit) {
            if (trim((string) ($enemyUnit['unit_id'] ?? '')) !== $targetUnitId) {
                continue;
            }

            $runtimeState['enemy_units'][$enemyIndex]['status'] = $status;

            return;
        }
    }

    private function success(array $data): array
    {
        return [
            'ok' => true,
            'reason' => null,
            'data' => $data,
        ];
    }
}
