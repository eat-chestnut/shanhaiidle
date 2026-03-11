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
            StoryMapDropsSeeder::class,
            StoryBossesSeeder::class,
            StoryBossDropsSeeder::class,
            EquipmentSetSourcesSeeder::class,
            StarterGiftsSeeder::class,
        ]);
    }
}
