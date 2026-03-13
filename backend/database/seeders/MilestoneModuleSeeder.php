<?php

namespace Database\Seeders;

use App\Services\MilestoneModuleImportService;
use Illuminate\Database\Seeder;

class MilestoneModuleSeeder extends Seeder
{
    public function run(): void
    {
        MilestoneModuleImportService::importFromProjectFile();
    }
}
