<?php

namespace Database\Seeders;

use App\Services\BossCoreModuleImportService;
use Illuminate\Database\Seeder;

class BossCoresSeeder extends Seeder
{
    public function run(): void
    {
        BossCoreModuleImportService::importFromProjectFile();
    }
}
