<?php

namespace Database\Seeders;

use App\Services\EquipmentGrowthRulesService;
use Illuminate\Database\Seeder;

class EquipmentGrowthRulesSettingSeeder extends Seeder
{
    public function run(): void
    {
        EquipmentGrowthRulesService::ensureDefaultSetting();
    }
}
