<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            ItemsSeeder::class,
            GemsSeeder::class,
            TalismansSeeder::class,
            GiftPackModuleSeeder::class,
            EquipSlotsSeeder::class,
            EquipmentSetsSeeder::class,
            EquipTemplatesSeeder::class,
            BlueAffixesSeeder::class,
            PurpleAffixPoolSeeder::class,
            BlueEquipmentTemplatesSeeder::class,
            CraftingRecipesSeeder::class,
            EquipmentStarModuleSeeder::class,
            BattleDefaultsSettingSeeder::class,
            EquipmentGrowthRulesSettingSeeder::class,
            CharacterGrowthRulesSettingSeeder::class,
            SectTaskRulesSettingSeeder::class,
            MountainGodOfferingSettingSeeder::class,
            SkillsCatalogSeeder::class,
            ShopGoodsSeeder::class,
            MainStageModuleSeeder::class,
            MilestoneModuleSeeder::class,
            MonstersSeeder::class,
            BossCoresSeeder::class,
            DailyDungeonsSeeder::class,
            StagesSeeder::class,
            NanshanYijingWorldSeeder::class,
            PlayerEquipmentInstanceExamplesSeeder::class,
        ]);
    }
}
