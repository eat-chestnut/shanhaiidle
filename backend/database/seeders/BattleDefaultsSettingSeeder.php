<?php

namespace Database\Seeders;

use App\Services\BattleDefaultsService;
use Illuminate\Database\Seeder;

class BattleDefaultsSettingSeeder extends Seeder
{
    public function run(): void
    {
        BattleDefaultsService::ensureDefaultSetting();
    }
}

