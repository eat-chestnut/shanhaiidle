<?php

namespace App\Services\Game\Equipment;

class EquipmentSpecialEffectAggregator
{
    /**
     * @param  array<int, array<string, mixed>>  $talismanEffects
     * @param  array<int, array<string, mixed>>  $bossCoreEffects
     * @param  array<int, array<string, mixed>>  $setEffects
     * @param  array<int, array<string, mixed>>  $blueAffixEffects
     */
    public function aggregate(
        int|string $playerId,
        array $talismanEffects,
        array $bossCoreEffects,
        array $setEffects,
        array $blueAffixEffects
    ): array {
        unset($playerId);

        $specialEffects = [];

        foreach ([$talismanEffects, $bossCoreEffects, $setEffects, $blueAffixEffects] as $effects) {
            foreach ($effects as $effect) {
                if (! is_array($effect)) {
                    continue;
                }

                $effectKey = trim((string) ($effect['effect_key'] ?? ''));
                $source = trim((string) ($effect['source'] ?? ''));
                if ($effectKey === '' || $source === '') {
                    continue;
                }

                $specialEffects[] = [
                    'effect_key' => $effectKey,
                    'source' => $source,
                ];
            }
        }

        return $this->success($specialEffects);
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
