<?php

namespace Tests\Feature;

use App\Console\Commands\ExportConfigBundle;
use App\Models\BlueAffix;
use App\Models\BlueAffixSlotRule;
use App\Models\Item;
use Database\Seeders\BlueAffixesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BlueAffixModuleExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_blue_affix_seed_imports_exact_level_banded_affixes_and_slot_rules(): void
    {
        $this->seed([
            BlueAffixesSeeder::class,
        ]);

        $affixes = BlueAffix::query()->orderBy('sort_order')->get();

        $this->assertCount(10, $affixes);
        $this->assertSame(36, BlueAffixSlotRule::query()->count());
        $this->assertSame([20, 35, 45], $affixes->pluck('level_band')->unique()->sort()->values()->all());
        $this->assertSame(9, $affixes->pluck('effect_key')->unique()->count());
        $this->assertFalse(BlueAffixSlotRule::query()->where('slot_type', 'talisman')->exists());
        $this->assertFalse(Item::query()->where('item_id', 'affix_melee_atk_20')->exists());

        $atk20 = BlueAffix::query()->where('affix_id', 'affix_melee_atk_20')->firstOrFail();
        $hp35 = BlueAffix::query()->where('affix_id', 'affix_hp_35')->firstOrFail();
        $lifesteal45 = BlueAffix::query()->where('affix_id', 'affix_lifesteal_45')->firstOrFail();

        $this->assertSame('bonus_melee_atk', $atk20->effect_key);
        $this->assertSame(20, $atk20->level_band);
        $this->assertCount(2, $atk20->slotRules);
        $this->assertSame(['main_weapon', 'sub_weapon'], $atk20->slotRules->pluck('slot_type')->all());
        $this->assertSame('bonus_hp', $hp35->effect_key);
        $this->assertSame(35, $hp35->level_band);
        $this->assertCount(4, $hp35->slotRules);
        $this->assertSame(2.5, (float) $lifesteal45->value_max);
        $this->assertCount(4, $lifesteal45->slotRules);
    }

    public function test_blue_affix_export_uses_exact_json_structure_with_slot_rules(): void
    {
        $this->seed([
            BlueAffixesSeeder::class,
        ]);

        Artisan::call('game:export-blue-affixes');

        $payload = json_decode((string) file_get_contents(storage_path('app/exports/blue_affixes_v1.json')), true);

        $this->assertSame('v1', $payload['version'] ?? null);
        $this->assertSame('blue_affixes', $payload['module'] ?? null);
        $this->assertSame(
            [
                'talisman_excluded' => true,
                'quality' => 'blue',
                'rarity' => 'blue',
                'supported_level_bands' => [20, 35, 45],
                'supported_effect_keys' => [
                    'bonus_melee_atk',
                    'bonus_hp',
                    'bonus_def',
                    'bonus_crit_rate',
                    'bonus_crit_dmg',
                    'bonus_atk_speed',
                    'bonus_skill_dmg',
                    'bonus_boss_dmg',
                    'bonus_lifesteal',
                ],
            ],
            $payload['rules'] ?? null,
        );

        $affixes = collect($payload['affixes'] ?? []);
        $boss45 = $affixes->firstWhere('affix_id', 'affix_boss_dmg_45');
        $crit20 = $affixes->firstWhere('affix_id', 'affix_crit_rate_20');

        $this->assertCount(10, $affixes);
        $this->assertSame('bonus_boss_dmg', $boss45['effect_key']);
        $this->assertSame(45, $boss45['level_band']);
        $this->assertSame('percent', $boss45['value_type']);
        $this->assertCount(4, $boss45['slot_rules']);
        $this->assertSame('main_weapon', $boss45['slot_rules'][0]['slot_type']);
        $this->assertSame('bonus_crit_rate', $crit20['effect_key']);
        $this->assertSame(1, $crit20['value_min']);
        $this->assertSame(2, $crit20['value_max']);
        $this->assertNull($affixes->first(fn (array $row): bool => collect($row['slot_rules'] ?? [])->contains(fn (array $slot): bool => ($slot['slot_type'] ?? null) === 'talisman')));
    }

    public function test_config_bundle_registers_blue_affix_export_file(): void
    {
        $reflection = new \ReflectionClass(ExportConfigBundle::class);
        $files = $reflection->getConstant('FILES');

        $this->assertIsArray($files);
        $this->assertContains('blue_affixes', array_column($files, 'key'));
        $this->assertContains('blue_affixes_v1.json', array_column($files, 'filename'));
    }
}
