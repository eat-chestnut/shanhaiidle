<?php

namespace Tests\Feature\Services\Game\Battle;

use App\Services\Game\Battle\BattleSettlementPayloadBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BattleSettlementPayloadBuilderTest extends TestCase
{
    use BattleSettlementTestSupport;
    use RefreshDatabase;

    public function test_it_builds_fixed_settlement_payload_structure(): void
    {
        $record = $this->createBattleResultRecord();

        $result = app(BattleSettlementPayloadBuilder::class)->build($record, [
            'reward_items' => [
                ['item_id' => 'cur_gold', 'count' => 500],
            ],
            'first_clear_granted' => true,
            'next_unlocks' => [
                ['type' => 'main_stage', 'id' => 'stage_nanshan_04'],
            ],
            'debug_reward_sources' => [
                ['type' => 'main_stage_first_clear', 'source' => 'stage_nanshan_03_hard_first_clear'],
            ],
        ]);

        $this->assertTrue($result['ok']);
        $this->assertNull($result['reason']);
        $this->assertSame([
            'battle_result' => 'victory',
            'reward_items' => [
                ['item_id' => 'cur_gold', 'count' => 500],
            ],
            'first_clear_granted' => true,
            'next_unlocks' => [
                ['type' => 'main_stage', 'id' => 'stage_nanshan_04'],
            ],
            'debug_reward_sources' => [
                ['type' => 'main_stage_first_clear', 'source' => 'stage_nanshan_03_hard_first_clear'],
            ],
        ], $result['data']);
    }
}
