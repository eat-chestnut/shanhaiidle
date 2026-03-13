<?php

namespace App\Services\Game\Equipment;

class EquipmentBonusAggregator
{
    /**
     * @param  array<string, mixed>  $setEffectResult
     * @param  array<string, mixed>  $talismanLinkResult
     * @param  array<int, array<string, mixed>>  $talismanBaseEffects
     * @param  array<int, array<string, mixed>>  $gemEffects
     * @param  array<int, array<string, mixed>>  $bossCoreEffects
     * @param  array<int, array<string, mixed>>  $blueAffixEffects
     */
    public function aggregate(
        int|string $playerId,
        array $setEffectResult,
        array $talismanLinkResult,
        array $talismanBaseEffects,
        array $gemEffects,
        array $bossCoreEffects,
        array $blueAffixEffects
    ): array {
        unset($playerId);

        $bonusStats = [];

        foreach ($this->normalizeSetEffects($setEffectResult) as $effect) {
            $this->pushEffect($bonusStats, $effect);
        }

        foreach ($this->normalizeTalismanStarLinks($talismanLinkResult) as $effect) {
            $this->pushEffect($bonusStats, $effect);
        }

        foreach ([$talismanBaseEffects, $gemEffects, $bossCoreEffects, $blueAffixEffects] as $effects) {
            foreach ($effects as $effect) {
                if (! is_array($effect)) {
                    continue;
                }

                $this->pushEffect($bonusStats, $effect);
            }
        }

        ksort($bonusStats);

        return $this->success($bonusStats);
    }

    /**
     * @param  array<string, mixed>  $setEffectResult
     * @return array<int, array<string, mixed>>
     */
    private function normalizeSetEffects(array $setEffectResult): array
    {
        $effects = $setEffectResult['data']['activated_effects'] ?? $setEffectResult['activated_effects'] ?? [];
        if (! is_array($effects)) {
            return [];
        }

        $normalized = [];
        foreach ($effects as $effect) {
            if (! is_array($effect)) {
                continue;
            }

            $setId = trim((string) ($effect['set_id'] ?? ''));
            $pieceCount = (int) ($effect['piece_count'] ?? 0);

            $normalized[] = [
                'effect_key' => trim((string) ($effect['effect_key'] ?? '')),
                'value_type' => trim((string) ($effect['value_type'] ?? '')),
                'value' => $this->normalizeNumeric($effect['value'] ?? 0),
                'source' => trim((string) ($effect['source'] ?? ($setId !== '' && $pieceCount > 0
                    ? sprintf('%s_%dpc', $setId, $pieceCount)
                    : ''))),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $talismanLinkResult
     * @return array<int, array<string, mixed>>
     */
    private function normalizeTalismanStarLinks(array $talismanLinkResult): array
    {
        $effects = $talismanLinkResult['data']['qualified_star_links'] ?? $talismanLinkResult['qualified_star_links'] ?? [];
        if (! is_array($effects)) {
            return [];
        }

        $normalized = [];
        foreach ($effects as $effect) {
            if (! is_array($effect)) {
                continue;
            }

            $requiredEquipmentStar = (int) ($effect['required_equipment_star'] ?? 0);

            $normalized[] = [
                'effect_key' => trim((string) ($effect['effect_key'] ?? '')),
                'value_type' => trim((string) ($effect['value_type'] ?? '')),
                'value' => $this->normalizeNumeric($effect['value'] ?? 0),
                'source' => trim((string) ($effect['source'] ?? ($requiredEquipmentStar > 0
                    ? sprintf('talisman_star_link_%d', $requiredEquipmentStar)
                    : ''))),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $bonusStats
     * @param  array<string, mixed>  $effect
     */
    private function pushEffect(array &$bonusStats, array $effect): void
    {
        $effectKey = trim((string) ($effect['effect_key'] ?? ''));
        $valueType = trim((string) ($effect['value_type'] ?? ''));
        $source = trim((string) ($effect['source'] ?? ''));

        if ($effectKey === '' || $valueType === '' || $source === '') {
            return;
        }

        $bonusStats[$effectKey] ??= [];
        $bonusStats[$effectKey][] = [
            'value_type' => $valueType,
            'value' => $this->normalizeNumeric($effect['value'] ?? 0),
            'source' => $source,
        ];
    }

    private function normalizeNumeric(mixed $value): int|float
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return floor($value) === $value ? (int) $value : $value;
        }

        if (! is_numeric($value)) {
            return 0;
        }

        $numeric = (float) $value;

        return floor($numeric) === $numeric ? (int) $numeric : $numeric;
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
