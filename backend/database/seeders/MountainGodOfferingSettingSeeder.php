<?php

namespace Database\Seeders;

use App\Services\MountainGodOfferingService;
use Illuminate\Database\Seeder;

class MountainGodOfferingSettingSeeder extends Seeder
{
    public function run(): void
    {
        MountainGodOfferingService::ensureDefaultSetting();
    }
}
