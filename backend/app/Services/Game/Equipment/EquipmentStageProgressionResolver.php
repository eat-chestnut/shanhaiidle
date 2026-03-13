<?php

namespace App\Services\Game\Equipment;

class EquipmentStageProgressionResolver
{
    private const MAX_STAR_BY_SET_LEVEL = [
        20 => 3,
        40 => 6,
        50 => 8,
        60 => 10,
    ];

    public function resolve(array $instance, array $progressionRules, array $recipeMap): array
    {
        $failedData = [
            'can_progress' => false,
            'next_state' => null,
        ];

        if ($this->isNonSetEquipmentInstance($instance)) {
            return $this->failure('set_equipment_only', $failedData);
        }

        $fromSetLevel = (int) ($instance['set_level'] ?? 0);
        if ($fromSetLevel < 1) {
            return $this->failure('invalid_set_level', $failedData);
        }

        $progressionRule = $this->findProgressionRule($progressionRules, $fromSetLevel);
        if (! is_array($progressionRule)) {
            return $this->failure('progression_rule_not_found', $failedData);
        }

        $requiredMaxStar = (int) ($progressionRule['required_max_star'] ?? 0);
        $currentStar = (int) ($instance['star'] ?? 0);
        if ($currentStar < $requiredMaxStar) {
            return $this->failure('current_star_below_required_max_star', $failedData);
        }

        $toSetLevel = (int) ($progressionRule['to_set_level'] ?? 0);
        $nextMaxStar = self::MAX_STAR_BY_SET_LEVEL[$toSetLevel] ?? null;
        if (! is_int($nextMaxStar)) {
            return $this->failure('unsupported_to_set_level', $failedData);
        }

        $slotType = trim((string) ($instance['slot_type'] ?? ''));
        $recipe = $this->findRecipe($recipeMap, $fromSetLevel, $toSetLevel, $slotType);
        if (! is_array($recipe)) {
            return $this->failure('progression_recipe_not_found', $failedData);
        }

        $nextItemId = trim((string) ($recipe['result_item_id'] ?? ''));
        if ($nextItemId === '') {
            return $this->failure('progression_result_item_missing', $failedData);
        }

        return $this->success([
            'can_progress' => true,
            'next_state' => [
                'item_id' => $nextItemId,
                'set_level' => $toSetLevel,
                'star' => $currentStar,
                'max_star' => $nextMaxStar,
            ],
        ]);
    }

    private function isNonSetEquipmentInstance(array $instance): bool
    {
        $sourceType = trim((string) ($instance['equipment_source_type'] ?? ''));
        if ($sourceType !== '' && $sourceType !== 'set_equipment') {
            return true;
        }

        if (! array_key_exists('set_level', $instance) || $instance['set_level'] === null) {
            return true;
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findProgressionRule(array $progressionRules, int $fromSetLevel): ?array
    {
        foreach ($progressionRules as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            if (array_key_exists('is_enabled', $rule) && $rule['is_enabled'] === false) {
                continue;
            }

            if ((int) ($rule['from_set_level'] ?? 0) !== $fromSetLevel) {
                continue;
            }

            return $rule;
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findRecipe(array $recipeMap, int $fromSetLevel, int $toSetLevel, string $slotType): ?array
    {
        foreach ($recipeMap as $row) {
            if (! is_array($row)) {
                continue;
            }

            if (array_key_exists('is_enabled', $row) && $row['is_enabled'] === false) {
                continue;
            }

            if ((int) ($row['from_set_level'] ?? 0) !== $fromSetLevel) {
                continue;
            }

            if ((int) ($row['to_set_level'] ?? 0) !== $toSetLevel) {
                continue;
            }

            if (trim((string) ($row['slot_type'] ?? '')) !== $slotType) {
                continue;
            }

            return $row;
        }

        return null;
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
