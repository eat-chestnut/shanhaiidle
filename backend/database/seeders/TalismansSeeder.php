<?php

namespace Database\Seeders;

use App\Services\TalismanModuleImportService;
use Illuminate\Database\Seeder;

class TalismansSeeder extends Seeder
{
    public function run(): void
    {
        TalismanModuleImportService::importFromProjectFile();
    }
}
