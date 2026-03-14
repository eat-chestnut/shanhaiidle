<?php

namespace App\Services\Game\Battle;

class DotHotTickResolver
{
    /**
     * @param  array<string, mixed>  $runtimeState
     */
    public function resolve(array $runtimeState, int $tick): array
    {
        $runtimeState['dot_hot_states'] = $this->normalizeStates($runtimeState['dot_hot_states'] ?? []);
        $runtimeState['player_unit'] = is_array($runtimeState['player_unit'] ?? null) ? $runtimeState['player_unit'] : [];
        $runtimeState['enemy_units'] = $this->normalizeUnits($runtimeState['enemy_units'] ?? []);

        $tickResults = [];

        foreach ($runtimeState['dot_hot_states'] as $stateIndex => $state) {
            $remainingTicks = max(0, (int) ($state['remaining_ticks'] ?? 0));
            if ($remainingTicks <= 0) {
                continue;
            }

            $tickResult = [
                'tick' => max(0, $tick),
                'effect_key' => trim((string) ($state['effect_key'] ?? '')),
                'owner_unit_id' => trim((string) ($state['owner_unit_id'] ?? '')),
                'target_unit_id' => trim((string) ($state['target_unit_id'] ?? '')),
                'effect_type' => trim((string) ($state['effect_type'] ?? '')),
                'hp_damage' => 0,
                'hp_healed' => 0,
            ];

            $targetResolveResult = $this->resolveTargetUnit(
                $runtimeState['player_unit'],
                $runtimeState['enemy_units'],
                $tickResult['target_unit_id']
            );

            if ($tickResult['effect_type'] === 'dot') {
                $damagePerTick = (float) ($state['damage_per_tick'] ?? 0);
                if ($targetResolveResult['target_type'] !== null) {
                    $appliedDamage = $this->applyDotDamage(
                        $runtimeState,
                        $targetResolveResult['target_type'],
                        (int) $targetResolveResult['target_index'],
                        $damagePerTick
                    );
                    $tickResult['hp_damage'] = $appliedDamage;
                }
            }

            if ($tickResult['effect_type'] === 'hot') {
                $healingPerTick = (float) ($state['healing_per_tick'] ?? 0);
                if ($targetResolveResult['target_type'] !== null) {
                    $appliedHealing = $this->applyHotHealing(
                        $runtimeState,
                        $targetResolveResult['target_type'],
                        (int) $targetResolveResult['target_index'],
                        $healingPerTick
                    );
                    $tickResult['hp_healed'] = $appliedHealing;
                }
            }

            $runtimeState['dot_hot_states'][$stateIndex]['remaining_ticks'] = $remainingTicks - 1;
            $tickResults[] = $tickResult;
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
     * @return array<int, array<string, mixed>>
     */
    private function normalizeUnits(mixed $units): array
    {
        return array_values(array_filter(
            is_array($units) ? $units : [],
            static fn (mixed $unit): bool => is_array($unit)
        ));
    }

    /**
     * @param  array<string, mixed>  $playerUnit
     * @param  array<int, array<string, mixed>>  $enemyUnits
     * @return array{target_type: string|null, target_index: int|null}
     */
    private function resolveTargetUnit(array $playerUnit, array $enemyUnits, string $targetUnitId): array
    {
        if (trim((string) ($playerUnit['unit_id'] ?? '')) === $targetUnitId) {
            return [
                'target_type' => 'player',
                'target_index' => null,
            ];
        }

        foreach ($enemyUnits as $enemyIndex => $enemyUnit) {
            if (trim((string) ($enemyUnit['unit_id'] ?? '')) !== $targetUnitId) {
                continue;
            }

            return [
                'target_type' => 'enemy',
                'target_index' => $enemyIndex,
            ];
        }

        return [
            'target_type' => null,
            'target_index' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $runtimeState
     */
    private function applyDotDamage(array &$runtimeState, string $targetType, ?int $targetIndex, float $damagePerTick): int|float
    {
        if ($targetType === 'player') {
            $currentHp = max(0.0, (float) ($runtimeState['player_unit']['current_hp'] ?? 0));
            $appliedDamage = min($currentHp, max(0.0, $damagePerTick));
            $runtimeState['player_unit']['current_hp'] = $this->normalizeNumber($currentHp - $appliedDamage);

            return $this->normalizeNumber($appliedDamage);
        }

        if ($targetType === 'enemy' && $targetIndex !== null && isset($runtimeState['enemy_units'][$targetIndex])) {
            $currentHp = max(0.0, (float) ($runtimeState['enemy_units'][$targetIndex]['current_hp'] ?? 0));
            $appliedDamage = min($currentHp, max(0.0, $damagePerTick));
            $runtimeState['enemy_units'][$targetIndex]['current_hp'] = $this->normalizeNumber($currentHp - $appliedDamage);

            return $this->normalizeNumber($appliedDamage);
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $runtimeState
     */
    private function applyHotHealing(array &$runtimeState, string $targetType, ?int $targetIndex, float $healingPerTick): int|float
    {
        if ($targetType === 'player') {
            $currentHp = max(0.0, (float) ($runtimeState['player_unit']['current_hp'] ?? 0));
            $maxHp = max($currentHp, (float) ($runtimeState['player_unit']['max_hp'] ?? $currentHp));
            $appliedHealing = min(max(0.0, $healingPerTick), max(0.0, $maxHp - $currentHp));
            $runtimeState['player_unit']['current_hp'] = $this->normalizeNumber($currentHp + $appliedHealing);

            return $this->normalizeNumber($appliedHealing);
        }

        if ($targetType === 'enemy' && $targetIndex !== null && isset($runtimeState['enemy_units'][$targetIndex])) {
            $currentHp = max(0.0, (float) ($runtimeState['enemy_units'][$targetIndex]['current_hp'] ?? 0));
            $maxHp = max($currentHp, (float) ($runtimeState['enemy_units'][$targetIndex]['max_hp'] ?? $currentHp));
            $appliedHealing = min(max(0.0, $healingPerTick), max(0.0, $maxHp - $currentHp));
            $runtimeState['enemy_units'][$targetIndex]['current_hp'] = $this->normalizeNumber($currentHp + $appliedHealing);

            return $this->normalizeNumber($appliedHealing);
        }

        return 0;
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
}
