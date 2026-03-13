<?php

namespace Database\Seeders;

use App\Services\BlueEquipmentTemplateImportService;
use Illuminate\Database\Seeder;

class BlueEquipmentTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        BlueEquipmentTemplateImportService::importFromProjectFile();
    }
}
