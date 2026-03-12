<?php

namespace Database\Seeders;

use App\Services\CharacterGrowthRulesService;
use Illuminate\Database\Seeder;

class CharacterGrowthRulesSettingSeeder extends Seeder
{
    public function run(): void
    {
        CharacterGrowthRulesService::ensureDefaultSetting();
    }
}
