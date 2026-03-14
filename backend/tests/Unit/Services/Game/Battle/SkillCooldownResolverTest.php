<?php

namespace Tests\Unit\Services\Game\Battle;

use App\Services\Game\Battle\SkillCooldownResolver;
use Tests\TestCase;

class SkillCooldownResolverTest extends TestCase
{
    public function test_enter_cooldown_after_cast(): void
    {
        $resolver = app(SkillCooldownResolver::class);

        $result = $resolver->enterCooldown([
            'skill_id' => 'skill_slash',
            'skill_type' => 'single_damage',
            'cooldown_total' => 3,
            'cooldown_remaining' => 0,
            'auto_cast' => true,
            'enabled' => true,
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame(3, $result['data']['skill']['cooldown_remaining']);
    }

    public function test_tick_decrements_cooldown_remaining(): void
    {
        $resolver = app(SkillCooldownResolver::class);

        $result = $resolver->tick([
            'skill_id' => 'skill_slash',
            'skill_type' => 'single_damage',
            'cooldown_total' => 3,
            'cooldown_remaining' => 3,
            'auto_cast' => true,
            'enabled' => true,
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame(2, $result['data']['skill']['cooldown_remaining']);
    }

    public function test_can_cast_when_cooldown_remaining_is_zero(): void
    {
        $resolver = app(SkillCooldownResolver::class);

        $result = $resolver->canCast([
            'skill_id' => 'skill_slash',
            'skill_type' => 'single_damage',
            'cooldown_total' => 3,
            'cooldown_remaining' => 0,
            'auto_cast' => true,
            'enabled' => true,
        ]);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['data']['can_cast']);
    }

    public function test_multi_skill_types_are_castable_when_cooldown_is_zero(): void
    {
        $resolver = app(SkillCooldownResolver::class);

        foreach (['multi_hit', 'aoe', 'self_buff', 'shield'] as $skillType) {
            $result = $resolver->canCast([
                'skill_id' => 'skill_'.$skillType,
                'skill_type' => $skillType,
                'cooldown_total' => 3,
                'cooldown_remaining' => 0,
                'auto_cast' => true,
                'enabled' => true,
            ]);

            $this->assertTrue($result['ok']);
            $this->assertTrue($result['data']['can_cast']);
        }
    }
}
