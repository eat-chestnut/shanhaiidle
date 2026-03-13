<?php

namespace Tests\Feature;

use App\Support\GiftPackModuleSupport;
use Database\Seeders\GiftPackModuleSeeder;
use Database\Seeders\ItemsSeeder;
use Database\Seeders\MainStageModuleSeeder;
use Database\Seeders\MonstersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GiftPackModuleExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_gift_pack_module_export_contains_expected_samples(): void
    {
        $this->seed([
            ItemsSeeder::class,
            GiftPackModuleSeeder::class,
            MainStageModuleSeeder::class,
            MonstersSeeder::class,
        ]);

        Artisan::call('game:export-gift-pack-module');
        Artisan::call('game:export-main-stage-module');

        $giftPackPayload = json_decode((string) file_get_contents(storage_path('app/exports/gift_pack_module_v1.json')), true);
        $stagePayload = json_decode((string) file_get_contents(storage_path('app/exports/main_stage_module_v1.json')), true);

        $packs = $giftPackPayload['gift_pack_module']['gift_packs'] ?? [];
        $packItems = $giftPackPayload['gift_pack_module']['gift_pack_items'] ?? [];

        $this->assertCount(3, $packs);
        $this->assertSame(
            ['gift_stage01_first_clear', 'gift_lv20_growth', 'gift_shop_weapon_select_20'],
            array_column($packs, 'pack_id'),
        );

        $weaponSelectPackItems = array_values(array_filter(
            $packItems,
            fn (array $row): bool => ($row['pack_id'] ?? null) === 'gift_shop_weapon_select_20'
        ));
        $this->assertCount(3, $weaponSelectPackItems);
        $this->assertSame(
            ['bing', 'jingang', 'fulu'],
            array_column($weaponSelectPackItems, 'recommended_sect'),
        );

        $stage01Difficulty1 = collect($stagePayload['main_stage_module']['chapters'] ?? [])
            ->flatMap(fn (array $chapter): array => $chapter['difficulties'] ?? [])
            ->firstWhere('difficulty_id', 'stage_01_difficulty_1');

        $this->assertNotNull($stage01Difficulty1);
        $this->assertSame([
            [
                'item_id' => 'gift_stage01_first_clear',
                'count' => 1,
                'sort_order' => 10,
                'is_enabled' => true,
                'remark' => '首通礼包样例',
            ],
        ], $stage01Difficulty1['first_clear_rewards']);
    }

    public function test_gift_pack_validation_rejects_nested_gift_pack_items(): void
    {
        $this->seed([
            ItemsSeeder::class,
        ]);

        $pack = GiftPackModuleSupport::normalizePack([
            'pack_id' => 'gift_nested_invalid',
            'item_id' => 'gift_lv20_growth',
            'pack_name' => '非法嵌套礼包',
            'display_name' => '非法嵌套礼包',
            'pack_type' => 'growth_pack',
            'pack_mode' => 'fixed',
            'open_mode' => 'manual',
        ]);

        $this->expectException(ValidationException::class);

        GiftPackModuleSupport::validatePackOrFail(
            $pack,
            GiftPackModuleSupport::normalizePackItems([
                [
                    'item_id' => 'gift_stage01_first_clear',
                    'count_min' => 1,
                    'count_max' => 1,
                ],
            ], 'fixed'),
            [],
        );
    }

    public function test_select_one_pack_requires_enabled_selectable_items(): void
    {
        $this->seed([
            ItemsSeeder::class,
        ]);

        $pack = GiftPackModuleSupport::normalizePack([
            'pack_id' => 'gift_select_invalid',
            'item_id' => 'gift_shop_weapon_select_20',
            'pack_name' => '非法单选礼包',
            'display_name' => '非法单选礼包',
            'pack_type' => 'shop_pack',
            'pack_mode' => 'select_one',
            'open_mode' => 'manual',
        ]);

        $this->expectException(ValidationException::class);

        GiftPackModuleSupport::validatePackOrFail($pack, [], []);
    }
}
