<?php

namespace Database\Seeders;

use App\Services\GiftPackModuleImportService;
use Illuminate\Database\Seeder;

class GiftPackModuleSeeder extends Seeder
{
    public function run(): void
    {
        GiftPackModuleImportService::importFromProjectFile();
    }
}
