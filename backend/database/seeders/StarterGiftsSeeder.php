<?php

namespace Database\Seeders;

use App\Models\StarterGift;
use Illuminate\Database\Seeder;

class StarterGiftsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            $this->gift('gift_lv1', 1, '祝余行囊', '新手补给', '巡山司为新入山候补准备的最初补给，装着最基础的采药与护身物资。', [
                $this->reward('祝余叶', '祝余叶', 12),
                $this->reward('桂木', '桂木', 12),
                $this->reward('金币', '金币', 2000),
            ], true, true, 10),
            $this->gift('gift_lv5', 5, '迷榖指途匣', '路线引导', '打开后可获得用于前期巡山与定位的指途材料。', [
                $this->reward('迷榖枝', '迷榖枝', 18),
                $this->reward('桂枝', '桂枝', 24),
                $this->reward('金币', '金币', 5000),
            ], true, true, 20),
            $this->gift('gift_lv10', 10, '堂庭采玉礼', '章节推进', '堂庭之山初开时发放的采玉礼包，帮助玩家过渡堂庭资源线。', [
                $this->reward('棪木', '棪木', 20),
                $this->reward('水玉晶', '水玉晶', 12),
                $this->reward('基础打造材料', '基础打造材料', 10),
                $this->reward('金币', '金币', 12000),
            ], true, true, 30),
            $this->gift('gift_lv20', 20, '招摇铸兵礼', '主养成启动', '20级主养成装备正式成型时发放，用于启动套装主武器与升星。', [
                $this->reward('box_weapon_set_20_select', '20级套装主武器自选箱', 1, 'weapon_select_20'),
                $this->reward('star_material_t1_pack', '初阶星材礼包', 1),
                $this->reward('pack_set_20_parts', '20级套装其他部位材料包', 1),
                $this->reward('金币', '金币', 40000),
            ], true, true, 40),
            $this->gift('gift_lv30', 30, '杻阳猎痕礼', '蓝装与图纸引导', '30级后进入蓝装、蓝词条和图纸体系，礼包用于帮助玩家理解中期成长线。', [
                $this->reward('20级蓝装', '20级蓝装', 1),
                $this->reward('蓝词条胚子装', '蓝词条胚子装', 1),
                $this->reward('bp_fragment_nanshan', '南山图纸碎片', 12),
                $this->reward('金币', '金币', 80000),
            ], true, true, 50),
            $this->gift('gift_lv40', 40, '青丘秘箓礼', '高阶功能开启', '40级后护身符、技能宝石掉落与Boss材料副本开放，礼包用于减少系统切换成本。', [
                $this->reward('护身符兑换凭证', '护身符兑换凭证', 1),
                $this->reward('技能宝石碎片', '技能宝石碎片', 15),
                $this->reward('star_stone_t2_common', '中阶星材', 30),
                $this->reward('boss_mark_nanshan', '南山印记', 6),
                $this->reward('金币', '金币', 150000),
            ], true, true, 60),
            $this->gift('gift_lv50', 50, '箕尾洗髓礼', '洗练系统开启', '50级开放紫色洗练后发放的资源礼包，用于完成中后期 build 过渡。', [
                $this->reward('refine_sand_basic', '洗练石', 30),
                $this->reward('equipment_essence', '装备精华', 12),
                $this->reward('star_stone_t3_common', '高阶星材', 24),
                $this->reward('box_attr_gem_purple_random', '紫色属性宝石随机箱', 1),
                $this->reward('金币', '金币', 260000),
            ], true, true, 70),
            $this->gift('gift_lv60', 60, '鹊山镇厄礼', '终章冲刺', '南山一经终章阶段发放的高阶资源礼包，用于冲刺60级主养成与终章打造。', [
                $this->reward('box_weapon_blueprint_60_select', '60级套装主武器图纸自选箱', 1, 'weapon_blueprint_60_select'),
                $this->reward('star_stone_t4_common', '极阶星材', 12),
                $this->reward('高阶技能宝石碎片', '高阶技能宝石碎片', 20),
                $this->reward('青丘核心', '青丘核心', 2),
                $this->reward('purple_refine_dust', '紫洗练材料', 10),
                $this->reward('金币', '金币', 420000),
            ], true, true, 80),
        ];

        foreach ($rows as $row) {
            StarterGift::query()->updateOrCreate(
                ['gift_id' => $row['gift_id']],
                $row,
            );
        }
    }

    private function gift(
        string $giftId,
        int $unlockLevel,
        string $giftName,
        string $giftRole,
        string $openCopy,
        array $rewards,
        bool $mustClaim,
        bool $isFree,
        int $sortOrder,
    ): array {
        $normalizedRewards = [];
        foreach (array_values($rewards) as $index => $reward) {
            $reward['reward_type'] = (string) ($reward['reward_type'] ?? (filled($reward['optional_group'] ?? null) ? 'choice' : 'fixed'));
            $reward['sort_order'] = (int) ($reward['sort_order'] ?? (($index + 1) * 10));
            $normalizedRewards[] = $reward;
        }

        return [
            'gift_id' => $giftId,
            'unlock_level' => $unlockLevel,
            'gift_name' => $giftName,
            'gift_role' => $giftRole,
            'open_copy' => $openCopy,
            'rewards' => $normalizedRewards,
            'must_claim' => $mustClaim,
            'is_free' => $isFree,
            'icon_path' => '',
            'banner_path' => '',
            'sort_order' => $sortOrder,
            'is_enabled' => true,
        ];
    }

    private function reward(string $itemId, string $itemName, int $count, ?string $optionalGroup = null): array
    {
        $reward = [
            'reward_type' => $optionalGroup !== null ? 'choice' : 'fixed',
            'item_id' => $itemId,
            'item_name' => $itemName,
            'count' => $count,
        ];

        if ($optionalGroup !== null) {
            $reward['optional_group'] = $optionalGroup;
        }

        return $reward;
    }
}
