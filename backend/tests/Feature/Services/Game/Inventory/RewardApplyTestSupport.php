<?php

namespace Tests\Feature\Services\Game\Inventory;

use App\Models\Item;
use App\Models\RewardGrantLog;

trait RewardApplyTestSupport
{
    protected function createItem(
        string $itemId,
        string $mainType = 'material',
        string $subType = 'base_material',
    ): Item {
        return Item::query()->create([
            'id' => $itemId,
            'item_id' => $itemId,
            'item_name' => $itemId,
            'display_name' => $itemId,
            'name' => $itemId,
            'type' => $mainType,
            'main_type' => $mainType,
            'sub_type' => $subType,
            'quality' => 'white',
            'rarity' => 'white',
            'is_stackable' => true,
            'max_stack' => 9999,
            'is_enabled' => true,
            'sort_order' => 10,
        ]);
    }

    protected function createRewardGrantLog(array $overrides = []): RewardGrantLog
    {
        return RewardGrantLog::query()->create(array_merge([
            'player_id' => '10001',
            'battle_id' => 'battle_runtime_001',
            'item_id' => 'cur_gold',
            'count' => 500,
            'reward_source_type' => 'battle_settlement',
            'reward_source_id' => 'battle_runtime_001',
            'grant_batch_id' => 'grant_batch_001',
        ], $overrides));
    }
}
