<?php

namespace App\Services\Game\Battle;

class DotHotStateBuilder
{
    private const SUPPORTED_EFFECT_TYPES = [
        'dot',
        'hot',
    ];

    /**
     * @param  array<int, array<string, mixed>>  $effects
     */
    public function build(array $effects): array
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

            if (
                $effectKey === ''
                || $ownerUnitId === ''
                || $targetUnitId === ''
                || ! in_array($effectType, self::SUPPORTED_EFFECT_TYPES, true)
                || $triggerTiming !== 'per_tick'
                || $durationTicks <= 0
            ) {
                continue;
            }

            $state = [
                'effect_key' => $effectKey,
                'owner_unit_id' => $ownerUnitId,
                'target_unit_id' => $targetUnitId,
                'effect_type' => $effectType,
                'trigger_timing' => $triggerTiming,
                'duration_ticks' => $durationTicks,
            ];

            if ($effectType === 'dot') {
                $damagePerTick = max(0, $this->normalizeNumber($effect['damage_per_tick'] ?? 0));
                if ((float) $damagePerTick <= 0) {
                    continue;
                }

                $state['damage_per_tick'] = $damagePerTick;
            }

            if ($effectType === 'hot') {
                $healingPerTick = max(0, $this->normalizeNumber($effect['healing_per_tick'] ?? 0));
                if ((float) $healingPerTick <= 0) {
                    continue;
                }

                $state['healing_per_tick'] = $healingPerTick;
            }

            $state['remaining_ticks'] = $durationTicks;

            $states[] = $state;
        }

        return $this->success([
            'dot_hot_states' => array_values($states),
        ]);
    }

    private function normalizeNumber(mixed $value): int|float
    {
        if (! is_numeric($value)) {
            return 0;
        }

        $rounded = round((float) $value, 3);
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
