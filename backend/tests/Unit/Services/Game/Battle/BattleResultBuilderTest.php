<?php

namespace Tests\Unit\Services\Game\Battle;

use App\Services\Game\Battle\BattleResultBuilder;
use Tests\TestCase;

class BattleResultBuilderTest extends TestCase
{
    public function test_it_builds_minimal_battle_result_payload(): void
    {
        $runtimeState = [
            'tick' => 12,
            'player_unit' => [
                'current_hp' => 320,
                'alive' => true,
            ],
            'enemy_units' => [
                ['wave_index' => 1, 'alive' => false],
                ['wave_index' => 1, 'alive' => false],
                ['wave_index' => 2, 'alive' => false],
            ],
            'logs' => [
                ['tick' => 12, 'action' => 'battle_end', 'battle_result' => 'victory'],
            ],
        ];

        $result = app(BattleResultBuilder::class)->build($runtimeState);

        $this->assertTrue($result['ok']);
        $this->assertNull($result['reason']);
        $this->assertSame([
            'battle_result' => 'victory',
            'elapsed_ticks' => 12,
            'remaining_player_hp' => 320,
            'remaining_enemy_count' => 0,
            'cleared_wave_count' => 2,
            'logs' => [
                ['tick' => 12, 'action' => 'battle_end', 'battle_result' => 'victory'],
            ],
        ], $result['data']);
    }
}
