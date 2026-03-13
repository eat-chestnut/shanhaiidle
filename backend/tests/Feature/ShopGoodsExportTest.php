<?php

namespace Tests\Feature;

use Database\Seeders\GiftPackModuleSeeder;
use Database\Seeders\ItemsSeeder;
use Database\Seeders\ShopGoodsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ShopGoodsExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_goods_export_uses_unified_item_references(): void
    {
        $this->seed([
            ItemsSeeder::class,
            GiftPackModuleSeeder::class,
            ShopGoodsSeeder::class,
        ]);

        Artisan::call('game:export-shop-goods');

        $json = (string) file_get_contents(storage_path('app/exports/shop_goods_v1.json'));
        $payload = json_decode($json, true);
        $rows = $payload['shop_goods'] ?? [];

        $this->assertCount(6, $rows);
        $this->assertSame([
            'daily_gold_pack_s',
            'daily_upgrade_mat_pack_s',
            'growth_gift_lv20',
            'growth_weapon_select_20',
            'sect_talisman_common_40',
            'special_stage_boost_pack',
        ], array_column($rows, 'goods_id'));

        $this->assertStringNotContainsString('shop_type', $json);
        $this->assertStringNotContainsString('cost_currency_type', $json);
        $this->assertStringNotContainsString('starts_at', $json);
        $this->assertStringNotContainsString('ends_at', $json);

        $growthGift = collect($rows)->firstWhere('goods_id', 'growth_gift_lv20');
        $weaponSelectGift = collect($rows)->firstWhere('goods_id', 'growth_weapon_select_20');
        $specialPack = collect($rows)->firstWhere('goods_id', 'special_stage_boost_pack');

        $this->assertSame('gift_pack', $growthGift['goods_type']);
        $this->assertSame('gift_lv20_growth', $growthGift['reward_item_id']);
        $this->assertSame('cur_premium_jade', $growthGift['price_item_id']);

        $this->assertSame('gift_pack', $weaponSelectGift['goods_type']);
        $this->assertSame('gift_shop_weapon_select_20', $weaponSelectGift['reward_item_id']);
        $this->assertSame('lifetime', $weaponSelectGift['buy_limit_type']);

        $this->assertSame('gift_stage01_first_clear', $specialPack['reward_item_id']);
        $this->assertSame('special', $specialPack['shop_tab']);
    }
}
