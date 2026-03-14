<?php

namespace App\Services\Game\Battle;

class SkillRuntimeStateBuilder
{
    private const SUPPORTED_SKILL_TYPES = [
        'single_damage',
        'multi_hit',
        'aoe',
        'self_buff',
        'shield',
    ];

    /**
     * @param  array<string, mixed>  $unit
     * @param  array<int, array<string, mixed>>  $skillConfigs
     */
    public function build(array $unit, array $skillConfigs): array
    {
        $ownerUnitId = trim((string) ($unit['unit_id'] ?? ''));
        if ($ownerUnitId === '') {
            return $this->failure('invalid_unit');
        }

        $skills = [];
        foreach ($skillConfigs as $index => $skillConfig) {
            if (! is_array($skillConfig)) {
                return $this->failure(sprintf('invalid_skill_config_at_index_%d', $index));
            }

            $skillId = trim((string) ($skillConfig['skill_id'] ?? ''));
            if ($skillId === '') {
                return $this->failure(sprintf('invalid_skill_config_at_index_%d:skill_id_required', $index));
            }

            $skillType = trim((string) ($skillConfig['skill_type'] ?? 'single_damage'));
            if (! in_array($skillType, self::SUPPORTED_SKILL_TYPES, true)) {
                return $this->failure(sprintf('unsupported_skill_type_%s', $skillType));
            }

            $cooldownTotal = max(0, (int) ($skillConfig['cooldown_ticks'] ?? $skillConfig['cooldown_total'] ?? 0));
            $cooldownRemaining = max(0, (int) ($skillConfig['cooldown_remaining'] ?? 0));

            $skillState = [
                'skill_id' => $skillId,
                'owner_unit_id' => $ownerUnitId,
                'skill_type' => $skillType,
                'cooldown_total' => $cooldownTotal,
                'cooldown_remaining' => $cooldownRemaining,
                'auto_cast' => (bool) ($skillConfig['auto_cast'] ?? true),
                'enabled' => (bool) ($skillConfig['enabled'] ?? true),
            ];

            $buildResult = $this->appendSkillTypeState($skillState, $skillConfig, $index);
            if (! ($buildResult['ok'] ?? false)) {
                return $buildResult;
            }

            $skills[] = $buildResult['data']['skill'];
        }

        return $this->success([
            'skills' => $skills,
        ]);
    }

    /**
     * @param  array<string, mixed>  $skillState
     * @param  array<string, mixed>  $skillConfig
     */
    private function appendSkillTypeState(array $skillState, array $skillConfig, int $index): array
    {
        $skillType = trim((string) ($skillState['skill_type'] ?? ''));

        if ($skillType === 'single_damage' || $skillType === 'aoe') {
            $damageRatio = (float) ($skillConfig['damage_ratio'] ?? 0);
            if ($damageRatio <= 0) {
                return $this->failure(sprintf('invalid_skill_config_at_index_%d:damage_ratio_required', $index));
            }

            return $this->success([
                'skill' => $this->rebuildSkillState($skillState, [
                    'damage_ratio' => $damageRatio,
                ]),
            ]);
        }

        if ($skillType === 'multi_hit') {
            $multiHitCount = max(0, (int) ($skillConfig['multi_hit_count'] ?? 0));
            if ($multiHitCount <= 0) {
                return $this->failure(sprintf('invalid_skill_config_at_index_%d:multi_hit_count_required', $index));
            }

            $hitDamageRatios = $this->normalizeNumericList($skillConfig['hit_damage_ratios'] ?? []);

            if ($hitDamageRatios !== []) {
                if (count($hitDamageRatios) !== $multiHitCount) {
                    return $this->failure(sprintf('invalid_skill_config_at_index_%d:hit_damage_ratios_count_mismatch', $index));
                }

                return $this->success([
                    'skill' => $this->rebuildSkillState($skillState, [
                        'multi_hit_count' => $multiHitCount,
                        'hit_damage_ratios' => $hitDamageRatios,
                    ]),
                ]);
            }

            $damageRatio = (float) ($skillConfig['damage_ratio'] ?? 0);
            if ($damageRatio <= 0) {
                return $this->failure(sprintf('invalid_skill_config_at_index_%d:damage_ratio_required', $index));
            }

            return $this->success([
                'skill' => $this->rebuildSkillState($skillState, [
                    'multi_hit_count' => $multiHitCount,
                    'damage_ratio' => $damageRatio,
                ]),
            ]);
        }

        if ($skillType === 'self_buff') {
            $modifierKey = trim((string) ($skillConfig['modifier_key'] ?? ''));
            if ($modifierKey === '') {
                return $this->failure(sprintf('invalid_skill_config_at_index_%d:modifier_key_required', $index));
            }

            return $this->success([
                'skill' => $this->rebuildSkillState($skillState, [
                    'modifier_key' => $modifierKey,
                    'modifier_value' => $this->normalizeNumeric($skillConfig['modifier_value'] ?? 0),
                ]),
            ]);
        }

        if ($skillType === 'shield') {
            $shieldValue = $this->normalizeNumeric($skillConfig['shield_value'] ?? 0);
            if ((float) $shieldValue <= 0) {
                return $this->failure(sprintf('invalid_skill_config_at_index_%d:shield_value_required', $index));
            }

            return $this->success([
                'skill' => $this->rebuildSkillState($skillState, [
                    'shield_value' => $shieldValue,
                ]),
            ]);
        }

        return $this->failure(sprintf('unsupported_skill_type_%s', $skillType));
    }

    /**
     * @return array<int, int|float>
     */
    private function normalizeNumericList(mixed $values): array
    {
        $normalized = [];

        foreach (is_array($values) ? $values : [] as $value) {
            if (! is_numeric($value)) {
                continue;
            }

            $normalized[] = $this->normalizeNumeric($value);
        }

        return $normalized;
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

    /**
     * @param  array<string, mixed>  $baseSkillState
     * @param  array<string, mixed>  $typeFields
     * @return array<string, mixed>
     */
    private function rebuildSkillState(array $baseSkillState, array $typeFields): array
    {
        return [
            'skill_id' => $baseSkillState['skill_id'],
            'owner_unit_id' => $baseSkillState['owner_unit_id'],
            'skill_type' => $baseSkillState['skill_type'],
            ...$typeFields,
            'cooldown_total' => $baseSkillState['cooldown_total'],
            'cooldown_remaining' => $baseSkillState['cooldown_remaining'],
            'auto_cast' => $baseSkillState['auto_cast'],
            'enabled' => $baseSkillState['enabled'],
        ];
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
