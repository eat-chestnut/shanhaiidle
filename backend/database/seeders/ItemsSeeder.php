<?php

namespace Database\Seeders;

use App\Services\ItemCatalogImportService;
use Illuminate\Database\Seeder;

class ItemsSeeder extends Seeder
{
    public function run(): void
    {
        ItemCatalogImportService::importFromProjectFile();
    }
}
