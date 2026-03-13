<?php

namespace Database\Seeders;

use App\Models\ShopGood;
use App\Support\ShopGoodsSupport;
use Illuminate\Database\Seeder;

class ShopGoodsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'goods_id' => 'daily_gold_pack_s',
                'title' => '铜贝小包',
                'display_name' => '铜贝小包',
                'shop_tab' => 'daily',
                'goods_type' => 'direct_item',
                'reward_item_id' => 'cur_gold',
                'reward_count' => 1000,
                'price_item_id' => 'cur_gold',
                'price_amount' => 600,
                'unlock_level' => 1,
                'buy_limit_type' => 'daily',
                'buy_limit_value' => 3,
                'is_recommended' => false,
                'is_enabled' => true,
                'sort_order' => 10,
                'desc' => '日常补给页签中的铜贝补给样例。',
                'icon' => null,
                'remark' => '直接售卖单个 reward_item_id。',
            ],
            [
                'goods_id' => 'daily_upgrade_mat_pack_s',
                'title' => '基础强化材料包',
                'display_name' => '基础强化材料包',
                'shop_tab' => 'daily',
                'goods_type' => 'direct_item',
                'reward_item_id' => 'mat_upgrade_star_low',
                'reward_count' => 5,
                'price_item_id' => 'cur_gold',
                'price_amount' => 250,
                'unlock_level' => 5,
                'buy_limit_type' => 'daily',
                'buy_limit_value' => 2,
                'is_recommended' => false,
                'is_enabled' => true,
                'sort_order' => 20,
                'desc' => '基础强化材料的小型直售商品样例。',
                'icon' => null,
                'remark' => '本轮不扩展礼包内容定义，直接售卖单个材料 item。',
            ],
            [
                'goods_id' => 'growth_gift_lv20',
                'title' => '20级成长礼包',
                'display_name' => '20级成长礼包',
                'shop_tab' => 'growth',
                'goods_type' => 'gift_pack',
                'reward_item_id' => 'gift_lv20_growth',
                'reward_count' => 1,
                'price_item_id' => 'cur_premium_jade',
                'price_amount' => 30,
                'unlock_level' => 20,
                'buy_limit_type' => 'lifetime',
                'buy_limit_value' => 1,
                'is_recommended' => true,
                'is_enabled' => true,
                'sort_order' => 10,
                'desc' => '20 级开启的成长礼包商品样例。',
                'icon' => null,
                'remark' => '多奖励通过礼包 item 承载。',
            ],
            [
                'goods_id' => 'growth_weapon_select_20',
                'title' => '20级武器自选礼包',
                'display_name' => '20级武器自选礼包',
                'shop_tab' => 'growth',
                'goods_type' => 'gift_pack',
                'reward_item_id' => 'gift_shop_weapon_select_20',
                'reward_count' => 1,
                'price_item_id' => 'cur_premium_jade',
                'price_amount' => 45,
                'unlock_level' => 20,
                'buy_limit_type' => 'lifetime',
                'buy_limit_value' => 1,
                'is_recommended' => true,
                'is_enabled' => true,
                'sort_order' => 20,
                'desc' => '成长页签中的武器自选礼包样例。',
                'icon' => null,
                'remark' => '礼包内容定义在 gift_packs / gift_pack_items。',
            ],
            [
                'goods_id' => 'sect_talisman_common_40',
                'title' => '通用护符',
                'display_name' => '通用护符',
                'shop_tab' => 'sect',
                'goods_type' => 'direct_item',
                'reward_item_id' => 'itm_talisman_common_40',
                'reward_count' => 1,
                'price_item_id' => 'cur_contribution',
                'price_amount' => 300,
                'unlock_level' => 40,
                'buy_limit_type' => 'weekly',
                'buy_limit_value' => 1,
                'is_recommended' => false,
                'is_enabled' => true,
                'sort_order' => 10,
                'desc' => '宗门贡献兑换页签中的通用护符样例。',
                'icon' => null,
                'remark' => '价格货币统一引用 cur_contribution。',
            ],
            [
                'goods_id' => 'special_stage_boost_pack',
                'title' => '首通助力礼包',
                'display_name' => '首通助力礼包',
                'shop_tab' => 'special',
                'goods_type' => 'gift_pack',
                'reward_item_id' => 'gift_stage01_first_clear',
                'reward_count' => 1,
                'price_item_id' => 'cur_premium_jade',
                'price_amount' => 25,
                'unlock_level' => 10,
                'buy_limit_type' => 'lifetime',
                'buy_limit_value' => 1,
                'is_recommended' => true,
                'is_enabled' => true,
                'sort_order' => 10,
                'desc' => '特殊推荐页签中的主线首通助力礼包样例。',
                'icon' => null,
                'remark' => '不做活动时段逻辑，仅做特殊推荐页签预留。',
            ],
        ];

        ShopGoodsSupport::validateRowsOrFail($rows);

        foreach ($rows as $row) {
            $normalized = ShopGoodsSupport::normalizeRow($row);

            ShopGood::query()->updateOrCreate(
                ['goods_id' => $normalized['goods_id']],
                $normalized,
            );
        }
    }
}
