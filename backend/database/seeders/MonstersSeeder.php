<?php

namespace Database\Seeders;

use App\Services\MonsterModuleImportService;
use Illuminate\Database\Seeder;

class MonstersSeeder extends Seeder
{
    public function run(): void
    {
        MonsterModuleImportService::importFromProjectFiles();
    }
}
