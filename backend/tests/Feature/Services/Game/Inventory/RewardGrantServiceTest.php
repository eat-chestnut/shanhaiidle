<?php

namespace Tests\Feature\Services\Game\Inventory;

use App\Services\Game\Inventory\RewardGrantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RewardGrantServiceTest extends TestCase
{
    use RefreshDatabase;
    use RewardApplyTestSupport;

    public function test_currency_can_be_granted_into_player_currencies(): void
    {
        $this->createItem('cur_gold', 'currency', 'gold');

        $result = app(RewardGrantService::class)->execute('10001', [
            ['item_id' => 'cur_gold', 'count' => 500],
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame([
            ['item_id' => 'cur_gold', 'count' => 500],
        ], $result['data']['granted_items']);
        $this->assertDatabaseHas('player_currencies', [
            'player_id' => '10001',
            'currency_id' => 'cur_gold',
            'amount' => 500,
        ]);
    }

    public function test_material_can_be_granted_into_player_items(): void
    {
        $this->createItem('mat_star_sand_fragment_low', 'material', 'star_material');

        $result = app(RewardGrantService::class)->execute('10001', [
            ['item_id' => 'mat_star_sand_fragment_low', 'count' => 8],
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame([
            ['item_id' => 'mat_star_sand_fragment_low', 'count' => 8],
        ], $result['data']['granted_items']);
        $this->assertDatabaseHas('player_items', [
            'player_id' => '10001',
            'item_id' => 'mat_star_sand_fragment_low',
            'count' => 8,
        ]);
    }

    public function test_boss_core_can_be_granted_into_player_items(): void
    {
        $this->createItem('core_qingqiu_frost', 'boss_core', 'boss_core');

        $result = app(RewardGrantService::class)->execute('10001', [
            ['item_id' => 'core_qingqiu_frost', 'count' => 1],
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame([
            ['item_id' => 'core_qingqiu_frost', 'count' => 1],
        ], $result['data']['granted_items']);
        $this->assertDatabaseHas('player_items', [
            'player_id' => '10001',
            'item_id' => 'core_qingqiu_frost',
            'count' => 1,
        ]);
    }
}
