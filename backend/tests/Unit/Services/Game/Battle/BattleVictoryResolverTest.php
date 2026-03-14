<?php

namespace Tests\Unit\Services\Game\Battle;

use App\Services\Game\Battle\BattleVictoryResolver;
use Tests\TestCase;

class BattleVictoryResolverTest extends TestCase
{
    public function test_player_death_results_in_defeat(): void
    {
        $result = app(BattleVictoryResolver::class)->resolve([
            'player_unit' => ['alive' => false],
            'enemy_units' => [
                ['alive' => true],
            ],
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame('defeat', $result['data']['battle_result']);
    }

    public function test_all_enemies_dead_results_in_victory(): void
    {
        $result = app(BattleVictoryResolver::class)->resolve([
            'player_unit' => ['alive' => true],
            'enemy_units' => [
                ['alive' => false],
                ['alive' => false],
            ],
        ]);

        $this->assertSame('victory', $result['data']['battle_result']);
    }

    public function test_otherwise_battle_is_ongoing(): void
    {
        $result = app(BattleVictoryResolver::class)->resolve([
            'player_unit' => ['alive' => true],
            'enemy_units' => [
                ['alive' => false],
                ['alive' => true],
            ],
        ]);

        $this->assertSame('ongoing', $result['data']['battle_result']);
    }
}
