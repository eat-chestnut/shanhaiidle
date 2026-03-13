<?php

namespace Database\Seeders;

use App\Services\MainStageModuleImportService;
use Illuminate\Database\Seeder;

class MainStageModuleSeeder extends Seeder
{
    public function run(): void
    {
        MainStageModuleImportService::importFromProjectFile();
    }
}
