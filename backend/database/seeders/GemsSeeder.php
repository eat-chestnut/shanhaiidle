<?php

namespace Database\Seeders;

use App\Services\GemModuleImportService;
use Illuminate\Database\Seeder;

class GemsSeeder extends Seeder
{
    public function run(): void
    {
        GemModuleImportService::importFromProjectFile();
    }
}
