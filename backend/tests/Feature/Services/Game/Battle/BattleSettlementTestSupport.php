<?php

namespace Tests\Feature\Services\Game\Battle;

use App\Models\BattleResult;
use App\Models\Item;
use App\Models\MainStageChapter;
use App\Models\MainStageDifficulty;
use App\Models\Monster;
use App\Models\MonsterDropItem;
use App\Models\ShopPlayerProfile;
use App\Models\StageDifficultyFirstClearReward;

trait BattleSettlementTestSupport
{
    protected function createItem(string $itemId, string $mainType = 'material'): Item
    {
        return Item::query()->create([
            'id' => $itemId,
            'item_id' => $itemId,
            'item_name' => $itemId,
            'display_name' => $itemId,
            'name' => $itemId,
            'type' => $mainType,
            'main_type' => $mainType,
            'sub_type' => $mainType === 'currency' ? 'gold' : 'base_material',
            'quality' => 'white',
            'rarity' => 'white',
            'is_stackable' => true,
            'max_stack' => 9999,
            'is_enabled' => true,
            'sort_order' => 10,
        ]);
    }

    protected function createProfile(string $playerId): ShopPlayerProfile
    {
        return ShopPlayerProfile::query()->create([
            'player_id' => $playerId,
            'level' => 1,
            'exp' => 0,
            'gold' => 0,
            'crystal' => 0,
            'contribution' => 0,
            'free_attr_points' => 0,
            'skill_points' => 0,
            'inventory' => [],
            'equipment' => [],
            'claimed_milestones' => [],
        ]);
    }

    protected function createMainStageDifficulty(string $stageId = 'stage_nanshan_03', string $difficultyId = 'hard'): MainStageDifficulty
    {
        MainStageChapter::query()->create([
            'chapter_id' => $stageId,
            'chapter_name' => '南山 03',
            'chapter_type' => 'main_stage',
            'chapter_flow_type' => 'combat',
            'is_functional_chapter' => false,
            'has_combat' => true,
            'has_sect_selection' => false,
            'has_shanshen_ritual' => false,
            'suggested_level_min' => 1,
            'suggested_level_max' => 10,
            'suggested_power' => 100,
            'mountain_name' => '南山',
            'boss_display_name' => '青丘之主',
            'unlock_level' => 1,
            'unlock_prev_chapter_id' => 'stage_nanshan_02',
            'sect_selection_enabled' => false,
            'shanshen_ritual_enabled' => false,
            'sort_order' => 30,
            'is_enabled' => true,
        ]);

        MainStageChapter::query()->create([
            'chapter_id' => 'stage_nanshan_04',
            'chapter_name' => '南山 04',
            'chapter_type' => 'main_stage',
            'chapter_flow_type' => 'combat',
            'is_functional_chapter' => false,
            'has_combat' => true,
            'has_sect_selection' => false,
            'has_shanshen_ritual' => false,
            'suggested_level_min' => 1,
            'suggested_level_max' => 10,
            'suggested_power' => 120,
            'mountain_name' => '南山',
            'boss_display_name' => '下一章 Boss',
            'unlock_level' => 1,
            'unlock_prev_chapter_id' => $stageId,
            'sect_selection_enabled' => false,
            'shanshen_ritual_enabled' => false,
            'sort_order' => 40,
            'is_enabled' => true,
        ]);

        return MainStageDifficulty::query()->create([
            'difficulty_id' => $difficultyId,
            'chapter_id' => $stageId,
            'difficulty_code' => $difficultyId,
            'difficulty_name' => strtoupper($difficultyId),
            'remark' => null,
            'sort_order' => 20,
            'is_enabled' => true,
        ]);
    }

    protected function createFirstClearReward(string $difficultyId, string $itemId, int $count): StageDifficultyFirstClearReward
    {
        return StageDifficultyFirstClearReward::query()->create([
            'difficulty_id' => $difficultyId,
            'item_id' => $itemId,
            'count' => $count,
            'sort_order' => 10,
            'is_enabled' => true,
            'remark' => null,
        ]);
    }

    protected function createBossWithDrop(string $monsterId = 'mon_qingqiu_boss', string $itemId = 'mat_boss_mark_qingqiu', int $count = 1): Monster
    {
        $monster = Monster::query()->create([
            'monster_id' => $monsterId,
            'monster_name' => '青丘 Boss',
            'display_name' => '青丘 Boss',
            'monster_type' => 'boss',
            'chapter_id' => 'stage_nanshan_03',
            'display_stage_id' => 'stage_nanshan_03',
            'family' => 'fox',
            'title' => null,
            'desc' => null,
            'icon' => null,
            'sprite' => null,
            'prefab_key' => null,
            'level' => 10,
            'hp' => 100,
            'atk' => 20,
            'def' => 10,
            'speed' => 10,
            'move_speed' => 10,
            'attack_range' => 10,
            'attack_interval' => 1,
            'aggro_range' => 10,
            'ai_type' => 'boss_pattern',
            'rarity_tag' => 'boss',
            'is_enabled' => true,
            'sort_order' => 10,
        ]);

        MonsterDropItem::query()->create([
            'monster_id' => $monsterId,
            'item_id' => $itemId,
            'drop_type' => 'fixed',
            'count_min' => $count,
            'count_max' => $count,
            'drop_rate' => 1,
            'sort_order' => 10,
            'is_enabled' => true,
            'remark' => null,
        ]);

        return $monster;
    }

    protected function createBattleResultRecord(
        string $battleId = 'battle_runtime_001',
        string $playerId = 'player_10001',
        string $battleType = 'main_stage',
        string $stageId = 'stage_nanshan_03',
        string $difficultyId = 'hard',
        string $battleResult = 'victory',
    ): BattleResult {
        return BattleResult::query()->create([
            'battle_id' => $battleId,
            'player_id' => $playerId,
            'battle_type' => $battleType,
            'stage_id' => $stageId,
            'difficulty_id' => $difficultyId,
            'battle_result' => $battleResult,
            'elapsed_ticks' => 12,
            'remaining_player_hp' => 320,
            'remaining_enemy_count' => 0,
            'cleared_wave_count' => 2,
            'is_settled' => false,
            'settled_at' => null,
        ]);
    }
}
