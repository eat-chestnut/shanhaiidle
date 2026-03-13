<?php

namespace Database\Seeders;

use App\Services\BlueAffixImportService;
use Illuminate\Database\Seeder;

class BlueAffixesSeeder extends Seeder
{
    public function run(): void
    {
        BlueAffixImportService::importFromProjectFile();
    }
}
