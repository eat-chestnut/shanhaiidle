<?php

namespace App\Services\Game\Battle;

class RuntimeEffectStateBuilder
{
    private const SUPPORTED_TRIGGER_TIMINGS = [
        'passive_always',
        'on_battle_start',
    ];

    private const SUPPORTED_EFFECT_TYPES = [
        'passive_tag',
        'passive_modifier',
        'battle_start_shield',
        'battle_start_modifier',
    ];

    /**
     * @param  array<int, array<string, mixed>>  $specialEffects
     */
    public function build(int|string $unitId, array $specialEffects): array
    {
        $safeUnitId = trim((string) $unitId);
        if ($safeUnitId === '') {
            return $this->failure('invalid_unit_id');
        }

        $runtimeEffects = [];

        foreach ($specialEffects as $specialEffect) {
            if (! is_array($specialEffect)) {
                continue;
            }

            $effectKey = trim((string) ($specialEffect['effect_key'] ?? ''));
            $source = trim((string) ($specialEffect['source'] ?? ''));
            $effectType = trim((string) ($specialEffect['effect_type'] ?? ''));
            $triggerTiming = trim((string) ($specialEffect['trigger_timing'] ?? ''));

            if ($effectKey === '' || $source === '' || $effectType === '' || $triggerTiming === '') {
                continue;
            }

            if (
                ! in_array($effectType, self::SUPPORTED_EFFECT_TYPES, true)
                || ! in_array($triggerTiming, self::SUPPORTED_TRIGGER_TIMINGS, true)
            ) {
                continue;
            }

            $runtimeEffect = [
                'effect_key' => $effectKey,
                'owner_unit_id' => $safeUnitId,
                'source' => $source,
                'effect_type' => $effectType,
                'trigger_timing' => $triggerTiming,
                'value' => $specialEffect['value'] ?? null,
            ];

            $modifierKey = trim((string) ($specialEffect['modifier_key'] ?? ''));
            if ($modifierKey !== '') {
                $runtimeEffect['modifier_key'] = $modifierKey;
            }

            $runtimeEffect['enabled'] = true;

            $runtimeEffects[] = $runtimeEffect;
        }

        return $this->success([
            'runtime_effects' => $runtimeEffects,
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
