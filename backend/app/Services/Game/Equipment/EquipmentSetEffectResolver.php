<?php

namespace App\Services\Game\Equipment;

class EquipmentSetEffectResolver
{
    private const PARTICIPATING_SLOT_TYPES = [
        'main_weapon',
        'sub_weapon',
        'armor',
        'leg',
        'shoe',
        'cloak',
        'helmet',
        'necklace',
        'bracelet_1',
        'bracelet_2',
        'ring_1',
        'ring_2',
    ];

    public function resolve(array $loadouts, array $equipmentInstances, array $setEffects): array
    {
        $instancesById = $this->indexInstancesById($equipmentInstances);
        $setCountMap = [];

        foreach ($loadouts as $loadout) {
            if (! is_array($loadout)) {
                continue;
            }

            $slotType = trim((string) ($loadout['slot_type'] ?? ''));
            if (! in_array($slotType, self::PARTICIPATING_SLOT_TYPES, true)) {
                continue;
            }

            $instanceId = trim((string) ($loadout['instance_id'] ?? ''));
            if ($instanceId === '' || ! isset($instancesById[$instanceId])) {
                continue;
            }

            $instance = $instancesById[$instanceId];
            $instanceSlotType = trim((string) ($instance['slot_type'] ?? $slotType));
            if (! in_array($instanceSlotType, self::PARTICIPATING_SLOT_TYPES, true)) {
                continue;
            }

            $setId = trim((string) ($instance['set_id'] ?? ''));
            if ($setId === '') {
                continue;
            }

            $setCountMap[$setId] = ($setCountMap[$setId] ?? 0) + 1;
        }

        ksort($setCountMap);

        $setCounts = [];
        foreach ($setCountMap as $setId => $equippedCount) {
            $setCounts[] = [
                'set_id' => (string) $setId,
                'equipped_count' => (int) $equippedCount,
            ];
        }

        $activatedEffects = [];
        foreach ($setEffects as $effect) {
            if (! is_array($effect)) {
                continue;
            }

            $setId = trim((string) ($effect['set_id'] ?? ''));
            $pieceCount = (int) ($effect['piece_count'] ?? 0);
            if ($setId === '' || $pieceCount < 1) {
                continue;
            }

            if (($setCountMap[$setId] ?? 0) < $pieceCount) {
                continue;
            }

            $activatedEffects[] = [
                'set_id' => $setId,
                'piece_count' => $pieceCount,
                'effect_key' => trim((string) ($effect['effect_key'] ?? '')),
                'value_type' => trim((string) ($effect['value_type'] ?? '')),
                'value' => $this->normalizeNumeric($effect['value'] ?? 0),
            ];
        }

        return $this->success([
            'set_counts' => $setCounts,
            'activated_effects' => $activatedEffects,
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function indexInstancesById(array $equipmentInstances): array
    {
        $indexed = [];

        foreach ($equipmentInstances as $instance) {
            if (! is_array($instance)) {
                continue;
            }

            $instanceId = trim((string) ($instance['instance_id'] ?? ''));
            if ($instanceId === '') {
                continue;
            }

            $indexed[$instanceId] = $instance;
        }

        return $indexed;
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
}
