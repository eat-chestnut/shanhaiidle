<?php

namespace App\Services\Game\Battle;

class PassiveEffectApplier
{
    /**
     * @param  array<string, mixed>  $unitRuntimeState
     * @param  array<int, array<string, mixed>>  $runtimeEffects
     */
    public function apply(array $unitRuntimeState, array $runtimeEffects): array
    {
        $unitRuntimeState['runtime_effects'] = $this->normalizeRuntimeEffects(
            $unitRuntimeState['runtime_effects'] ?? $runtimeEffects
        );
        $unitRuntimeState['runtime_tags'] = $this->normalizeRuntimeTags($unitRuntimeState['runtime_tags'] ?? []);
        $unitRuntimeState['runtime_modifiers'] = $this->normalizeRuntimeModifiers($unitRuntimeState['runtime_modifiers'] ?? []);
        $unitRuntimeState['shield'] = $this->normalizeNumeric($unitRuntimeState['shield'] ?? 0);

        $allowedEffectIds = $this->buildEffectIdentityMap($runtimeEffects);
        $appliedEffects = [];

        foreach ($unitRuntimeState['runtime_effects'] as $runtimeEffect) {
            if (! is_array($runtimeEffect)) {
                continue;
            }

            $effectIdentity = $this->buildEffectIdentity($runtimeEffect);
            if ($effectIdentity === '' || ! isset($allowedEffectIds[$effectIdentity])) {
                continue;
            }

            if (($runtimeEffect['enabled'] ?? false) !== true) {
                continue;
            }

            if (trim((string) ($runtimeEffect['trigger_timing'] ?? '')) !== 'passive_always') {
                continue;
            }

            $effectType = trim((string) ($runtimeEffect['effect_type'] ?? ''));
            if ($effectType === 'passive_tag') {
                $effectKey = trim((string) ($runtimeEffect['effect_key'] ?? ''));
                if ($effectKey === '') {
                    continue;
                }

                $unitRuntimeState['runtime_tags'] = $this->appendUniqueTag($unitRuntimeState['runtime_tags'], $effectKey);
                $appliedEffects[] = $runtimeEffect;

                continue;
            }

            if ($effectType === 'passive_modifier') {
                $modifierKey = trim((string) ($runtimeEffect['modifier_key'] ?? ''));
                if ($modifierKey === '') {
                    continue;
                }

                $unitRuntimeState['runtime_modifiers'][$modifierKey] = $this->sumNumeric(
                    $unitRuntimeState['runtime_modifiers'][$modifierKey] ?? 0,
                    $runtimeEffect['value'] ?? 0
                );
                $appliedEffects[] = $runtimeEffect;
            }
        }

        return $this->success([
            'unit_runtime_state' => $unitRuntimeState,
            'applied_effects' => array_values($appliedEffects),
        ]);
    }

    /**
     * @param  mixed  $runtimeEffects
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRuntimeEffects(mixed $runtimeEffects): array
    {
        return array_values(array_filter(
            is_array($runtimeEffects) ? $runtimeEffects : [],
            static fn (mixed $runtimeEffect): bool => is_array($runtimeEffect)
        ));
    }

    /**
     * @param  mixed  $runtimeTags
     * @return array<int, string>
     */
    private function normalizeRuntimeTags(mixed $runtimeTags): array
    {
        $tags = [];

        foreach (is_array($runtimeTags) ? $runtimeTags : [] as $tag) {
            $safeTag = trim((string) $tag);
            if ($safeTag === '') {
                continue;
            }

            $tags[$safeTag] = $safeTag;
        }

        return array_values($tags);
    }

    /**
     * @param  mixed  $runtimeModifiers
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

    /**
     * @param  array<int, string>  $runtimeTags
     * @return array<int, string>
     */
    private function appendUniqueTag(array $runtimeTags, string $tag): array
    {
        $safeTag = trim($tag);
        if ($safeTag === '') {
            return $runtimeTags;
        }

        $runtimeTags[] = $safeTag;

        return $this->normalizeRuntimeTags($runtimeTags);
    }

    /**
     * @param  array<int, array<string, mixed>>  $runtimeEffects
     * @return array<string, true>
     */
    private function buildEffectIdentityMap(array $runtimeEffects): array
    {
        $effectIds = [];

        foreach ($runtimeEffects as $runtimeEffect) {
            if (! is_array($runtimeEffect)) {
                continue;
            }

            $effectIdentity = $this->buildEffectIdentity($runtimeEffect);
            if ($effectIdentity === '') {
                continue;
            }

            $effectIds[$effectIdentity] = true;
        }

        return $effectIds;
    }

    /**
     * @param  array<string, mixed>  $runtimeEffect
     */
    private function buildEffectIdentity(array $runtimeEffect): string
    {
        $effectKey = trim((string) ($runtimeEffect['effect_key'] ?? ''));
        $source = trim((string) ($runtimeEffect['source'] ?? ''));
        $effectType = trim((string) ($runtimeEffect['effect_type'] ?? ''));
        $triggerTiming = trim((string) ($runtimeEffect['trigger_timing'] ?? ''));
        $modifierKey = trim((string) ($runtimeEffect['modifier_key'] ?? ''));

        if ($effectKey === '' || $source === '' || $effectType === '' || $triggerTiming === '') {
            return '';
        }

        return implode(':', [$effectKey, $source, $effectType, $triggerTiming, $modifierKey]);
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
}
