<?php

namespace Tests\Feature;

use App\Models\ShopPlayerProfile;
use App\Services\ShopPurchaseService;
use Database\Seeders\GiftPackModuleSeeder;
use Database\Seeders\ItemsSeeder;
use Database\Seeders\ShopGoodsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopPurchaseServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_purchase_uses_price_item_id_and_buy_limit_rules(): void
    {
        $this->seed([
            ItemsSeeder::class,
            GiftPackModuleSeeder::class,
            ShopGoodsSeeder::class,
        ]);

        ShopPlayerProfile::query()->create([
            'player_id' => 'player_shop_test',
            'level' => 20,
            'exp' => 0,
            'gold' => 5000,
            'crystal' => 100,
            'contribution' => 500,
            'free_attr_points' => 0,
            'skill_points' => 0,
            'inventory' => [],
            'equipment' => [],
            'claimed_milestones' => [],
        ]);

        $service = app(ShopPurchaseService::class);

        $firstGoldPurchase = $service->purchase('player_shop_test', 'daily_gold_pack_s');
        $secondGoldPurchase = $service->purchase('player_shop_test', 'daily_gold_pack_s');
        $thirdGoldPurchase = $service->purchase('player_shop_test', 'daily_gold_pack_s');
        $fourthGoldPurchase = $service->purchase('player_shop_test', 'daily_gold_pack_s');

        $this->assertTrue($firstGoldPurchase['ok']);
        $this->assertTrue($secondGoldPurchase['ok']);
        $this->assertTrue($thirdGoldPurchase['ok']);
        $this->assertFalse($fourthGoldPurchase['ok']);
        $this->assertSame('daily_limit_reached', $fourthGoldPurchase['reason']);

        $profile = ShopPlayerProfile::query()->where('player_id', 'player_shop_test')->firstOrFail();
        $this->assertSame(6200, (int) $profile->gold);
        $this->assertSame([], $profile->inventory ?? []);

        $growthPurchase = $service->purchase('player_shop_test', 'growth_gift_lv20');
        $repeatGrowthPurchase = $service->purchase('player_shop_test', 'growth_gift_lv20');

        $this->assertTrue($growthPurchase['ok']);
        $this->assertSame('growth', $growthPurchase['shop_tab']);
        $this->assertSame('gift_pack', $growthPurchase['goods_type']);
        $this->assertSame('cur_premium_jade', $growthPurchase['price_item_id']);
        $this->assertSame('gift_lv20_growth', $growthPurchase['reward_item_id']);

        $profile->refresh();
        $this->assertSame(70, (int) $profile->crystal);
        $this->assertSame(1, (int) ($profile->inventory['gift_lv20_growth'] ?? 0));

        $this->assertFalse($repeatGrowthPurchase['ok']);
        $this->assertSame('lifetime_limit_reached', $repeatGrowthPurchase['reason']);
    }
}
