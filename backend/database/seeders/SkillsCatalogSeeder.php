<?php

namespace Database\Seeders;

use App\Models\SkillCatalog;
use Illuminate\Database\Seeder;

class SkillsCatalogSeeder extends Seeder
{
    public function run(): void
    {
        SkillCatalog::query()->updateOrCreate(
            ['id' => 'BING_01'],
            [
                'name' => '裂石击',
                'desc' => '挥出沉重一击，适合贴身清怪。',
                'sect' => '兵宗',
                'class' => '兵宗',
                'is_enabled' => true,
                'sort_order' => 10,
            ]
        );
    }
}
