<?php

namespace App\Services\Game\Battle;

class SelfBuffShieldResolver
{
    /**
     * @param  array<string, mixed>  $actorUnit
     * @param  array<string, mixed>  $skillState
     */
    public function resolve(array $actorUnit, array $skillState): array
    {
        $actorUnit['runtime_modifiers'] = $this->normalizeRuntimeModifiers($actorUnit['runtime_modifiers'] ?? []);
        $actorUnit['shield'] = max(0, $this->normalizeNumeric($actorUnit['shield'] ?? 0));

        $skillType = trim((string) ($skillState['skill_type'] ?? ''));
        if ($skillType === 'self_buff') {
            return $this->resolveSelfBuff($actorUnit, $skillState);
        }

        if ($skillType === 'shield') {
            return $this->resolveShield($actorUnit, $skillState);
        }

        return $this->failure('unsupported_skill_type');
    }

    /**
     * @param  array<string, mixed>  $actorUnit
     * @param  array<string, mixed>  $skillState
     */
    private function resolveSelfBuff(array $actorUnit, array $skillState): array
    {
        $modifierKey = trim((string) ($skillState['modifier_key'] ?? ''));
        if ($modifierKey === '') {
            return $this->failure('invalid_modifier_key');
        }

        $modifierValue = $this->normalizeNumeric($skillState['modifier_value'] ?? 0);
        $actorUnit['runtime_modifiers'][$modifierKey] = $this->sumNumeric(
            $actorUnit['runtime_modifiers'][$modifierKey] ?? 0,
            $modifierValue
        );

        return $this->success([
            'actor_unit' => $actorUnit,
            'effects' => [[
                'log_action' => 'effect_apply',
                'target_unit_id' => trim((string) ($actorUnit['unit_id'] ?? '')),
                'effect_key' => $modifierKey,
                'effect_value' => $modifierValue,
                'source' => trim((string) ($skillState['skill_id'] ?? '')),
                'raw_damage' => 0,
                'hp_damage' => 0,
                'shield_absorbed' => 0,
                'is_critical' => false,
            ]],
        ]);
    }

    /**
     * @param  array<string, mixed>  $actorUnit
     * @param  array<string, mixed>  $skillState
     */
    private function resolveShield(array $actorUnit, array $skillState): array
    {
        $shieldValue = $this->normalizeNumeric($skillState['shield_value'] ?? 0);
        if ((float) $shieldValue <= 0) {
            return $this->failure('invalid_shield_value');
        }

        $actorUnit['shield'] = max(0, $this->sumNumeric($actorUnit['shield'], $shieldValue));

        return $this->success([
            'actor_unit' => $actorUnit,
            'effects' => [[
                'log_action' => 'effect_apply',
                'target_unit_id' => trim((string) ($actorUnit['unit_id'] ?? '')),
                'effect_key' => 'shield',
                'effect_value' => $shieldValue,
                'source' => trim((string) ($skillState['skill_id'] ?? '')),
                'raw_damage' => 0,
                'hp_damage' => 0,
                'shield_absorbed' => 0,
                'is_critical' => false,
            ]],
        ]);
    }

    /**
     * @return array<string, int|float>
     */
    private function normalizeRuntimeModifiers(mixed $runtimeModifiers): array
    {
        $modifiers = [];

        foreach (is_array($runtimeModifiers) ? $runtimeModifiers : [] as $modifierKey => $modifierValue) {
            $safeModifierKey = trim((string) $modifierKey);
            if ($safeModifierKey === '') {
                continue;
            }

            $modifiers[$safeModifierKey] = $this->normalizeNumeric($modifierValue);
        }

        return $modifiers;
    }

    private function normalizeNumeric(mixed $value): int|float
    {
        if (! is_numeric($value)) {
            return 0;
        }

        $numericValue = (float) $value;
        if (fmod($numericValue, 1.0) === 0.0) {
            return (int) $numericValue;
        }

        return $numericValue;
    }

    private function sumNumeric(mixed $currentValue, mixed $deltaValue): int|float
    {
        $sum = (float) $this->normalizeNumeric($currentValue) + (float) $this->normalizeNumeric($deltaValue);

        if (fmod($sum, 1.0) === 0.0) {
            return (int) $sum;
        }

        return $sum;
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
