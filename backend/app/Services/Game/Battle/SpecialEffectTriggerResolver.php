<?php

namespace App\Services\Game\Battle;

class SpecialEffectTriggerResolver
{
    private const SUPPORTED_TRIGGER_TIMINGS = [
        'passive_always',
        'on_battle_start',
    ];

    /**
     * @param  array<int, array<string, mixed>>  $runtimeEffects
     */
    public function resolveByTiming(array $runtimeEffects, string $triggerTiming): array
    {
        $safeTriggerTiming = trim($triggerTiming);
        if (! in_array($safeTriggerTiming, self::SUPPORTED_TRIGGER_TIMINGS, true)) {
            return $this->failure('unsupported_trigger_timing');
        }

        $resolvedEffects = [];

        foreach ($runtimeEffects as $runtimeEffect) {
            if (! is_array($runtimeEffect)) {
                continue;
            }

            if (($runtimeEffect['enabled'] ?? false) !== true) {
                continue;
            }

            if (trim((string) ($runtimeEffect['trigger_timing'] ?? '')) !== $safeTriggerTiming) {
                continue;
            }

            $resolvedEffects[] = $runtimeEffect;
        }

        return $this->success([
            'runtime_effects' => array_values($resolvedEffects),
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
