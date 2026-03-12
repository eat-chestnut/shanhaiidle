<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class NanshanYijingWorldSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            WorldNamesSeeder::class,
            StoryChaptersSeeder::class,
            StoryMapsSeeder::class,
            StoryBossesSeeder::class,
            EquipmentSetSourcesSeeder::class,
        ]);
    }
}
