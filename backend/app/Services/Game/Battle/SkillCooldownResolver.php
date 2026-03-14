<?php

namespace App\Services\Game\Battle;

class SkillCooldownResolver
{
    private const CASTABLE_SKILL_TYPES = [
        'single_damage',
        'multi_hit',
        'aoe',
        'self_buff',
        'shield',
    ];

    /**
     * @param  array<string, mixed>  $skillState
     */
    public function tick(array $skillState): array
    {
        $remaining = max(0, (int) ($skillState['cooldown_remaining'] ?? 0));
        $skillState['cooldown_remaining'] = max(0, $remaining - 1);

        return $this->success([
            'skill' => $skillState,
        ]);
    }

    /**
     * @param  array<string, mixed>  $skillState
     */
    public function canCast(array $skillState): array
    {
        $enabled = (bool) ($skillState['enabled'] ?? false);
        $autoCast = (bool) ($skillState['auto_cast'] ?? false);
        $skillType = trim((string) ($skillState['skill_type'] ?? ''));
        $cooldownRemaining = max(0, (int) ($skillState['cooldown_remaining'] ?? 0));

        return $this->success([
            'can_cast' => $enabled
                && $autoCast
                && in_array($skillType, self::CASTABLE_SKILL_TYPES, true)
                && $cooldownRemaining === 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $skillState
     */
    public function enterCooldown(array $skillState): array
    {
        $skillState['cooldown_remaining'] = max(0, (int) ($skillState['cooldown_total'] ?? 0));

        return $this->success([
            'skill' => $skillState,
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
}
