<?php

namespace Database\Seeders;

use App\Services\SectTaskRulesService;
use Illuminate\Database\Seeder;

class SectTaskRulesSettingSeeder extends Seeder
{
    public function run(): void
    {
        SectTaskRulesService::ensureDefaultSetting();
    }
}
