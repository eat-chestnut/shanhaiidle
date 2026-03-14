<?php

namespace Tests\Unit\Services\Game\Battle;

use App\Services\Game\Battle\StatusControlResolver;
use Tests\TestCase;

class StatusControlResolverTest extends TestCase
{
    public function test_advanced_status_control_applies_and_expires_from_json_example(): void
    {
        $example = $this->loadExamples()['status_control_example'];
        $buildResult = app(StatusControlResolver::class)->buildStates([$example]);

        $this->assertTrue($buildResult['ok']);
        $this->assertSame('stunned', $buildResult['data']['status_control_states'][0]['status']);
        $this->assertFalse($buildResult['data']['status_control_states'][0]['stacking']);
        $this->assertSame([], $buildResult['data']['status_control_states'][0]['immune_tags']);

        $runtimeState = [
            'player_unit' => [
                'unit_id' => 'player_10001',
                'current_hp' => 300,
                'max_hp' => 300,
                'status' => null,
                'alive' => true,
            ],
            'enemy_units' => [
                [
                    'unit_id' => 'enemy_mon_ice_witch_2_1',
                    'current_hp' => 300,
                    'max_hp' => 300,
                    'status' => null,
                    'alive' => true,
                ],
            ],
            'status_control_states' => $buildResult['data']['status_control_states'],
        ];

        $expectedTickResults = [
            ['tick' => 1, 'status_applied' => true],
            ['tick' => 2, 'status_active' => true],
            ['tick' => 3, 'status_active' => false],
        ];

        foreach ($expectedTickResults as $expectedTickResult) {
            $result = app(StatusControlResolver::class)->tick($runtimeState, (int) $expectedTickResult['tick']);

            $this->assertTrue($result['ok']);
            $this->assertSame(
                [$this->buildExpectedTickResult($example, $expectedTickResult)],
                $result['data']['tick_results']
            );

            $runtimeState = $result['data']['runtime_state'];
        }

        $this->assertNull($runtimeState['player_unit']['status']);
    }

    public function test_silenced_status_is_supported(): void
    {
        $result = app(StatusControlResolver::class)->buildStates([[
            'effect_key' => 'silence_status',
            'owner_unit_id' => 'enemy_mon_shadow_mage_1_1',
            'target_unit_id' => 'player_10001',
            'effect_type' => 'status_control',
            'trigger_timing' => 'on_apply',
            'duration_ticks' => 2,
            'status' => 'silenced',
        ]]);

        $this->assertTrue($result['ok']);
        $this->assertSame('silenced', $result['data']['status_control_states'][0]['status']);
        $this->assertSame(2, $result['data']['status_control_states'][0]['remaining_ticks']);
    }

    public function test_slowed_status_is_supported(): void
    {
        $result = app(StatusControlResolver::class)->buildStates([[
            'effect_key' => 'slow_status',
            'owner_unit_id' => 'enemy_mon_ice_witch_2_1',
            'target_unit_id' => 'player_10001',
            'effect_type' => 'status_control',
            'trigger_timing' => 'on_apply',
            'duration_ticks' => 2,
            'status' => 'slowed',
        ]]);

        $this->assertTrue($result['ok']);
        $this->assertSame('slowed', $result['data']['status_control_states'][0]['status']);
        $this->assertSame(2, $result['data']['status_control_states'][0]['remaining_ticks']);
    }

    /**
     * @param  array<string, mixed>  $example
     * @param  array<string, mixed>  $expectedTickResult
     * @return array<string, mixed>
     */
    private function buildExpectedTickResult(array $example, array $expectedTickResult): array
    {
        $result = [
            'tick' => $expectedTickResult['tick'],
            'effect_key' => $example['effect_key'],
            'owner_unit_id' => $example['owner_unit_id'],
            'target_unit_id' => $example['target_unit_id'],
            'status' => $example['status'],
        ];

        if (array_key_exists('status_applied', $expectedTickResult)) {
            $result['status_applied'] = $expectedTickResult['status_applied'];
        }

        if (array_key_exists('status_active', $expectedTickResult)) {
            $result['status_active'] = $expectedTickResult['status_active'];
        }

        return $result;
    }

    private function loadExamples(): array
    {
        return json_decode((string) file_get_contents(base_path('../data/dot_hot_status_control_advanced_examples_v1.json')), true);
    }
}
