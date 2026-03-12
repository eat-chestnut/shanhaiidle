<?php

namespace Database\Seeders;

use App\Services\ProgressionMilestonesService;
use Illuminate\Database\Seeder;

class ProgressionMilestonesSettingSeeder extends Seeder
{
    public function run(): void
    {
        ProgressionMilestonesService::ensureDefaultSetting();
    }
}
