<?php

namespace App\Services\Game\Equipment;

class EquipmentGemSlotResolver
{
    private const SLOT_RULE_MAP = [
        1 => ['required_star' => 3, 'slot_group' => 'attr_only'],
        2 => ['required_star' => 6, 'slot_group' => 'attr_only'],
        3 => ['required_star' => 8, 'slot_group' => 'skill_only'],
        4 => ['required_star' => 10, 'slot_group' => 'skill_only'],
    ];

    public function resolveUnlockedSlots(array $instance, array $slotUnlockRules): array
    {
        $normalizedRules = $this->normalizeSlotUnlockRules($slotUnlockRules);
        if ($normalizedRules === null) {
            return $this->failure('invalid_slot_unlock_rules');
        }

        $instanceId = trim((string) ($instance['instance_id'] ?? ''));
        if ($instanceId === '') {
            return $this->failure('instance_missing');
        }

        $star = (int) ($instance['star'] ?? 0);

        $unlockedSlots = [];
        foreach ($normalizedRules as $rule) {
            if ($star < (int) $rule['required_star']) {
                continue;
            }

            $unlockedSlots[] = $rule;
        }

        return $this->success([
            'instance_id' => $instanceId,
            'star' => $star,
            'unlocked_slots' => $unlockedSlots,
        ]);
    }

    public function canSocketGem(
        array $instance,
        int $slotIndex,
        ?array $gemItem,
        ?array $gemConfig,
        array $slotUnlockRules
    ): array {
        $normalizedRules = $this->normalizeSlotUnlockRules($slotUnlockRules);
        if ($normalizedRules === null) {
            return $this->failure('invalid_slot_unlock_rules', ['allowed' => false]);
        }

        $targetRule = null;
        foreach ($normalizedRules as $rule) {
            if ((int) $rule['slot_index'] === $slotIndex) {
                $targetRule = $rule;
                break;
            }
        }

        if (! is_array($targetRule)) {
            return $this->failure('slot_not_found', ['allowed' => false]);
        }

        $instanceStar = (int) ($instance['star'] ?? 0);
        if ($instanceStar < (int) $targetRule['required_star']) {
            return $this->failure('slot_not_unlocked', ['allowed' => false]);
        }

        $gemItemId = is_array($gemItem) ? trim((string) ($gemItem['item_id'] ?? '')) : '';
        if ($gemItemId === '') {
            return $this->failure('gem_item_missing', ['allowed' => false]);
        }

        $gemConfigItemId = is_array($gemConfig) ? trim((string) ($gemConfig['item_id'] ?? '')) : '';
        $gemConfigSlotGroup = is_array($gemConfig) ? trim((string) ($gemConfig['slot_group'] ?? '')) : '';
        if ($gemConfigItemId === '' || $gemConfigSlotGroup === '') {
            return $this->failure('gem_config_missing', ['allowed' => false]);
        }

        if ($gemConfigItemId !== $gemItemId) {
            return $this->failure('gem_config_item_mismatch', ['allowed' => false]);
        }

        if ($gemConfigSlotGroup !== (string) $targetRule['slot_group']) {
            return $this->failure('gem_slot_group_mismatch', ['allowed' => false]);
        }

        return $this->success(['allowed' => true]);
    }

    /**
     * @return array<int, array{slot_index:int,slot_group:string,required_star:int}>|null
     */
    private function normalizeSlotUnlockRules(array $slotUnlockRules): ?array
    {
        $normalizedBySlot = [];

        foreach ($slotUnlockRules as $rule) {
            if (! is_array($rule)) {
                return null;
            }

            $slotIndex = (int) ($rule['slot_index'] ?? 0);
            $requiredStar = (int) ($rule['required_star'] ?? 0);
            $slotGroup = trim((string) ($rule['slot_group'] ?? ''));

            $expected = self::SLOT_RULE_MAP[$slotIndex] ?? null;
            if (! is_array($expected)) {
                return null;
            }

            if (isset($normalizedBySlot[$slotIndex])) {
                return null;
            }

            if ($requiredStar !== (int) $expected['required_star']) {
                return null;
            }

            if ($slotGroup !== (string) $expected['slot_group']) {
                return null;
            }

            $normalizedBySlot[$slotIndex] = [
                'slot_index' => $slotIndex,
                'slot_group' => $slotGroup,
                'required_star' => $requiredStar,
            ];
        }

        if (count($normalizedBySlot) !== count(self::SLOT_RULE_MAP)) {
            return null;
        }

        foreach (array_keys(self::SLOT_RULE_MAP) as $slotIndex) {
            if (! isset($normalizedBySlot[$slotIndex])) {
                return null;
            }
        }

        ksort($normalizedBySlot);

        return array_values($normalizedBySlot);
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
