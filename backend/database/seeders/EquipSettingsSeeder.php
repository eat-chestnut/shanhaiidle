<?php

namespace Database\Seeders;

use App\Models\EquipSetting;
use Illuminate\Database\Seeder;

class EquipSettingsSeeder extends Seeder
{
    public function run(): void
    {
        EquipSetting::query()->updateOrCreate(
            ['key' => 'socket_weights'],
            [
                'value' => [
                    '0' => 60,
                    '1' => 25,
                    '2' => 10,
                    '3' => 4,
                    '4' => 1,
                ],
            ]
        );
    }
}
