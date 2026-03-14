<?php

namespace App\Services\Game\Battle;

class SkillRuntimeStateBuilder
{
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
            if ($skillType !== 'single_damage') {
                return $this->failure(sprintf('unsupported_skill_type_%s', $skillType));
            }

            $damageRatio = (float) ($skillConfig['damage_ratio'] ?? 0);
            if ($damageRatio <= 0) {
                return $this->failure(sprintf('invalid_skill_config_at_index_%d:damage_ratio_required', $index));
            }

            $cooldownTotal = max(0, (int) ($skillConfig['cooldown_ticks'] ?? $skillConfig['cooldown_total'] ?? 0));
            $cooldownRemaining = max(0, (int) ($skillConfig['cooldown_remaining'] ?? 0));

            $skills[] = [
                'skill_id' => $skillId,
                'owner_unit_id' => $ownerUnitId,
                'skill_type' => $skillType,
                'damage_ratio' => $damageRatio,
                'cooldown_total' => $cooldownTotal,
                'cooldown_remaining' => $cooldownRemaining,
                'auto_cast' => (bool) ($skillConfig['auto_cast'] ?? true),
                'enabled' => (bool) ($skillConfig['enabled'] ?? true),
            ];
        }

        return $this->success([
            'skills' => $skills,
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
