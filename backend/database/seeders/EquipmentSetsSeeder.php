<?php

namespace Database\Seeders;

use App\Services\EquipmentSetModuleImportService;
use Illuminate\Database\Seeder;

class EquipmentSetsSeeder extends Seeder
{
    public function run(): void
    {
        EquipmentSetModuleImportService::importFromProjectFile();
    }
}
