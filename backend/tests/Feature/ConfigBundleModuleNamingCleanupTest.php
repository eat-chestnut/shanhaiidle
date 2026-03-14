<?php

namespace Tests\Feature;

use App\Console\Commands\ExportConfigBundle;
use Tests\TestCase;

class ConfigBundleModuleNamingCleanupTest extends TestCase
{
    public function test_config_bundle_only_keeps_current_daily_blue_and_talisman_module_keys(): void
    {
        $reflection = new \ReflectionClass(ExportConfigBundle::class);
        $files = $reflection->getConstant('FILES');

        $this->assertIsArray($files);

        $keys = array_column($files, 'key');
        $filenames = array_column($files, 'filename');

        $this->assertContains('daily_dungeons', $keys);
        $this->assertContains('blue_equipment_templates', $keys);
        $this->assertContains('blue_affixes', $keys);
        $this->assertContains('talisman_module', $keys);

        $this->assertContains('daily_dungeons_v1.json', $filenames);
        $this->assertContains('blue_equipment_templates_v1.json', $filenames);
        $this->assertContains('blue_affixes_v1.json', $filenames);
        $this->assertContains('talisman_module_v1.json', $filenames);

        $this->assertNotContains('material_dungeons', $keys);
        $this->assertNotContains('blue_gear_templates', $keys);
        $this->assertNotContains('blue_affix_pool', $keys);

        $this->assertNotContains('material_dungeons_v1.json', $filenames);
        $this->assertNotContains('blue_gear_templates_v1.json', $filenames);
        $this->assertNotContains('blue_affix_pool_v1.json', $filenames);

        $this->assertFileDoesNotExist(base_path('../data/material_dungeons_v1.json'));
        $this->assertFileDoesNotExist(base_path('../data/blue_gear_templates_v1.json'));
        $this->assertFileDoesNotExist(base_path('../data/blue_affix_pool_v1.json'));
    }
}
