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
            EquipSlotsSeeder::class,
            EquipmentSetsSeeder::class,
            EquipTemplatesSeeder::class,
            BlueAffixPoolSeeder::class,
            PurpleAffixPoolSeeder::class,
            BlueGearTemplatesSeeder::class,
            MaterialDungeonsSeeder::class,
            CraftingRecipesSeeder::class,
            EquipSettingsSeeder::class,
            BattleDefaultsSettingSeeder::class,
            EquipmentGrowthRulesSettingSeeder::class,
            SkillsCatalogSeeder::class,
            MonstersSeeder::class,
            StagesSeeder::class,
        ]);
    }
}
