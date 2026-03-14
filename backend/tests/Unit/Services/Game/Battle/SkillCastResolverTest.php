<?php

namespace Tests\Unit\Services\Game\Battle;

use App\Services\Game\Battle\SkillCastResolver;
use Tests\TestCase;

class SkillCastResolverTest extends TestCase
{
    public function test_player_cast_targets_front_alive_enemy_in_current_wave(): void
    {
        $result = app(SkillCastResolver::class)->resolvePlayerCast(
            ['unit_id' => 'player_10001', 'alive' => true],
            [
                ['unit_id' => 'enemy_wave1_2', 'wave_index' => 1, 'unit_index' => 2, 'alive' => true],
                ['unit_id' => 'enemy_wave1_1', 'wave_index' => 1, 'unit_index' => 1, 'alive' => true],
                ['unit_id' => 'enemy_wave2_1', 'wave_index' => 2, 'unit_index' => 1, 'alive' => true],
            ],
            [
                [
                    'skill_id' => 'skill_slash',
                    'skill_type' => 'single_damage',
                    'cooldown_remaining' => 0,
                    'auto_cast' => true,
                    'enabled' => true,
                ],
            ],
        );

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['data']['can_cast']);
        $this->assertSame('enemy_wave1_1', $result['data']['target_unit_id']);
    }

    public function test_enemy_cast_targets_player(): void
    {
        $result = app(SkillCastResolver::class)->resolveEnemyCast(
            ['unit_id' => 'enemy_mon_qingqiu_boss_2_1', 'alive' => true],
            ['unit_id' => 'player_10001', 'alive' => true],
            [
                [
                    'skill_id' => 'skill_frost_breath',
                    'skill_type' => 'single_damage',
                    'cooldown_remaining' => 0,
                    'auto_cast' => true,
                    'enabled' => true,
                ],
            ],
        );

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['data']['can_cast']);
        $this->assertSame('player_10001', $result['data']['target_unit_id']);
    }

    public function test_returns_not_castable_when_skill_cannot_cast(): void
    {
        $result = app(SkillCastResolver::class)->resolvePlayerCast(
            ['unit_id' => 'player_10001', 'alive' => true],
            [
                ['unit_id' => 'enemy_wave1_1', 'wave_index' => 1, 'unit_index' => 1, 'alive' => true],
            ],
            [
                [
                    'skill_id' => 'skill_slash',
                    'skill_type' => 'single_damage',
                    'cooldown_remaining' => 2,
                    'auto_cast' => true,
                    'enabled' => true,
                ],
            ],
        );

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['data']['can_cast']);
        $this->assertNull($result['data']['skill']);
        $this->assertNull($result['data']['target_unit_id']);
    }

    public function test_player_aoe_cast_targets_all_alive_enemies_in_current_wave(): void
    {
        $result = app(SkillCastResolver::class)->resolvePlayerCast(
            ['unit_id' => 'player_10001', 'alive' => true],
            [
                ['unit_id' => 'enemy_wave1_2', 'wave_index' => 1, 'unit_index' => 2, 'alive' => true],
                ['unit_id' => 'enemy_wave1_1', 'wave_index' => 1, 'unit_index' => 1, 'alive' => true],
                ['unit_id' => 'enemy_wave2_1', 'wave_index' => 2, 'unit_index' => 1, 'alive' => true],
            ],
            [
                [
                    'skill_id' => 'skill_fire_blast',
                    'skill_type' => 'aoe',
                    'cooldown_remaining' => 0,
                    'auto_cast' => true,
                    'enabled' => true,
                ],
            ],
        );

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['data']['can_cast']);
        $this->assertSame('enemy_wave1_1', $result['data']['target_unit_id']);
        $this->assertSame([1, 0], $result['data']['target_enemy_indexes']);
    }

    public function test_self_target_skill_can_cast_without_enemy_target(): void
    {
        $result = app(SkillCastResolver::class)->resolvePlayerCast(
            ['unit_id' => 'player_10001', 'alive' => true],
            [],
            [
                [
                    'skill_id' => 'skill_atk_boost',
                    'skill_type' => 'self_buff',
                    'cooldown_remaining' => 0,
                    'auto_cast' => true,
                    'enabled' => true,
                ],
            ],
        );

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['data']['can_cast']);
        $this->assertSame('player_10001', $result['data']['target_unit_id']);
        $this->assertNull($result['data']['target_enemy_index']);
        $this->assertSame([], $result['data']['target_enemy_indexes']);
    }
}
