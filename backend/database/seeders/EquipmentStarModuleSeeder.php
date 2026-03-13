<?php

namespace Database\Seeders;

use App\Services\EquipmentStarModuleImportService;
use Illuminate\Database\Seeder;

class EquipmentStarModuleSeeder extends Seeder
{
    public function run(): void
    {
        EquipmentStarModuleImportService::importFromProjectFile();
    }
}
