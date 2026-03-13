<?php

namespace App\Services\Game\Equipment;

class BlueAffixEligibilityResolver
{
    private const SLOT_TYPE_NORMALIZATION_MAP = [
        'ring_1' => 'ring',
        'ring_2' => 'ring',
        'bracelet_1' => 'bracelet',
        'bracelet_2' => 'bracelet',
    ];

    public function resolve(
        array $instance,
        ?array $blueTemplate,
        array $blueAffixes,
        array $blueAffixSlotRules
    ): array {
        if ($this->isNonBlueEquipmentInstance($instance)) {
            return $this->failure('blue_equipment_only', ['eligible_affixes' => []]);
        }

        if (! is_array($blueTemplate) || $blueTemplate === []) {
            return $this->failure('blue_template_missing', ['eligible_affixes' => []]);
        }

        $templateSlotType = trim((string) ($blueTemplate['slot_type'] ?? ''));
        $templateLevelBand = (int) ($blueTemplate['level_band'] ?? 0);
        if ($templateSlotType === '' || $templateLevelBand < 1) {
            return $this->failure('invalid_blue_template', ['eligible_affixes' => []]);
        }

        $instanceSlotType = trim((string) ($instance['slot_type'] ?? ''));
        if ($instanceSlotType !== '') {
            $normalizedInstanceSlotType = $this->normalizeSlotType($instanceSlotType);
            if ($normalizedInstanceSlotType !== $templateSlotType) {
                return $this->failure('template_slot_mismatch', ['eligible_affixes' => []]);
            }
        }

        $allowedAffixIds = [];
        foreach ($blueAffixSlotRules as $slotRule) {
            if (! is_array($slotRule)) {
                continue;
            }

            $affixId = trim((string) ($slotRule['affix_id'] ?? ''));
            $slotType = trim((string) ($slotRule['slot_type'] ?? ''));
            if ($affixId === '' || $slotType !== $templateSlotType) {
                continue;
            }

            $allowedAffixIds[$affixId] = true;
        }

        $eligibleAffixes = [];
        foreach ($blueAffixes as $affix) {
            if (! is_array($affix)) {
                continue;
            }

            $affixId = trim((string) ($affix['affix_id'] ?? ''));
            if ($affixId === '' || ! isset($allowedAffixIds[$affixId])) {
                continue;
            }

            if ((int) ($affix['level_band'] ?? 0) !== $templateLevelBand) {
                continue;
            }

            $eligibleAffixes[] = [
                'affix_id' => $affixId,
                'effect_key' => trim((string) ($affix['effect_key'] ?? '')),
                'value_min' => $this->normalizeNumeric($affix['value_min'] ?? 0),
                'value_max' => $this->normalizeNumeric($affix['value_max'] ?? 0),
            ];
        }

        return $this->success([
            'eligible_affixes' => $eligibleAffixes,
        ]);
    }

    private function isNonBlueEquipmentInstance(array $instance): bool
    {
        if (array_key_exists('equipment_source_type', $instance)) {
            $sourceType = trim((string) $instance['equipment_source_type']);
            if ($sourceType !== '' && $sourceType !== 'blue_equipment') {
                return true;
            }
        }

        if (array_key_exists('quality', $instance)) {
            $quality = trim((string) $instance['quality']);
            if ($quality !== '' && $quality !== 'blue') {
                return true;
            }
        }

        if (array_key_exists('rarity', $instance)) {
            $rarity = trim((string) $instance['rarity']);
            if ($rarity !== '' && $rarity !== 'blue') {
                return true;
            }
        }

        return false;
    }

    private function normalizeSlotType(string $slotType): string
    {
        return self::SLOT_TYPE_NORMALIZATION_MAP[$slotType] ?? $slotType;
    }

    private function normalizeNumeric(mixed $value): int|float
    {
        if (is_int($value) || is_float($value)) {
            return $value;
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

    private function failure(string $reason, ?array $data = null): array
    {
        return [
            'ok' => false,
            'reason' => $reason,
            'data' => $data,
        ];
    }
}
