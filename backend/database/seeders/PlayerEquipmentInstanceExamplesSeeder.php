<?php

namespace Database\Seeders;

use App\Services\PlayerEquipmentInstanceImportService;
use Illuminate\Database\Seeder;

class PlayerEquipmentInstanceExamplesSeeder extends Seeder
{
    public function run(): void
    {
        PlayerEquipmentInstanceImportService::importFromProjectFile();
    }
}
