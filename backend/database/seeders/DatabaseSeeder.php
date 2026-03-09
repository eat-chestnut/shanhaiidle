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
            EquipmentSetsSeeder::class,
            EquipTemplatesSeeder::class,
            EquipSettingsSeeder::class,
            BattleDefaultsSettingSeeder::class,
            SkillsCatalogSeeder::class,
            MonstersSeeder::class,
            StagesSeeder::class,
        ]);
    }
}
