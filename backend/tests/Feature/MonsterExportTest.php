<?php

namespace Tests\Feature;

use Database\Seeders\ItemsSeeder;
use Database\Seeders\MainStageModuleSeeder;
use Database\Seeders\MonstersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class MonsterExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_monster_export_does_not_include_first_clear_reward_group_id(): void
    {
        $this->seed([
            ItemsSeeder::class,
            MainStageModuleSeeder::class,
            MonstersSeeder::class,
        ]);

        Artisan::call('game:export-monsters');

        $json = (string) file_get_contents(storage_path('app/exports/monsters.json'));
        $payload = json_decode($json, true);

        $this->assertStringNotContainsString('first_clear_reward_group_id', $json);

        foreach (($payload['monsters'] ?? []) as $monster) {
            if (! is_array($monster['boss_profile'] ?? null)) {
                continue;
            }

            $this->assertArrayNotHasKey('first_clear_reward_group_id', $monster['boss_profile']);
        }
    }
}
