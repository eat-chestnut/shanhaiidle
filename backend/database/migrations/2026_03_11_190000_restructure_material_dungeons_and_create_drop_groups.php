<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_dungeon_drop_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('group_id', 120)->unique();
            $table->string('name', 255);
            $table->json('rewards')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->index(['is_enabled', 'sort_order']);
        });

        Schema::table('material_dungeons', function (Blueprint $table): void {
            $table->json('display_rewards')->nullable()->after('unlock_level');
            $table->json('layer_rules')->nullable()->after('display_rewards');
        });

        $itemIds = DB::table('items')->pluck('id')->map(fn ($value): string => (string) $value)->all();
        $itemNames = DB::table('items')->pluck('id', 'name')->mapWithKeys(fn ($id, $name): array => [(string) $name => (string) $id])->all();
        $existingGroupIds = [];

        $rows = DB::table('material_dungeons')->get();

        foreach ($rows as $row) {
            $displayRewards = $this->normalizeLegacyItems(json_decode((string) ($row->drop_pools ?? '[]'), true), $itemIds, $itemNames);
            $layerConfig = json_decode((string) ($row->layer_config ?? '[]'), true);
            if (! is_array($layerConfig)) {
                $layerConfig = [];
            }

            $layerRules = [];
            $layerIndex = 0;

            foreach ($layerConfig as $legacyRule) {
                if (! is_array($legacyRule)) {
                    continue;
                }

                $layer = max(1, (int) ($legacyRule['layer'] ?? ($layerIndex + 1)));
                $groupId = sprintf('mdg_%s_l%d_drop', $row->dungeon_id, $layer);
                $groupName = sprintf('%s 第%d层掉落', (string) $row->name, $layer);
                $groupRewards = $this->buildGroupRewards($legacyRule['drop_pool'] ?? [], $itemIds, $itemNames);

                if (! isset($existingGroupIds[$groupId])) {
                    DB::table('material_dungeon_drop_groups')->insert([
                        'group_id' => $groupId,
                        'name' => $groupName,
                        'rewards' => json_encode($groupRewards, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'description' => null,
                        'sort_order' => ($row->sort_order * 10) + $layer,
                        'is_enabled' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $existingGroupIds[$groupId] = true;
                }

                if ($displayRewards === []) {
                    $displayRewards = array_values(array_unique(array_column($groupRewards, 'item_id')));
                }

                $layerRules[] = [
                    'layer' => $layer,
                    'drop_group_id' => $groupId,
                    'first_clear_reward_group_id' => null,
                    'recommended_power' => filled($legacyRule['recommended_power'] ?? null) ? (int) $legacyRule['recommended_power'] : null,
                    'sort' => $layerIndex + 1,
                ];

                $layerIndex++;
            }

            DB::table('material_dungeons')
                ->where('id', $row->id)
                ->update([
                    'display_rewards' => json_encode(array_values(array_unique($displayRewards)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'layer_rules' => json_encode($layerRules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
        }

        Schema::table('material_dungeons', function (Blueprint $table): void {
            $table->dropColumn(['layer_config', 'drop_pools']);
        });
    }

    public function down(): void
    {
        Schema::table('material_dungeons', function (Blueprint $table): void {
            $table->json('layer_config')->nullable()->after('unlock_level');
            $table->json('drop_pools')->nullable()->after('layer_config');
        });

        DB::table('material_dungeons')->update([
            'layer_config' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'drop_pools' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        Schema::table('material_dungeons', function (Blueprint $table): void {
            $table->dropColumn(['display_rewards', 'layer_rules']);
        });

        Schema::dropIfExists('material_dungeon_drop_groups');
    }

    /**
     * @param  array<int, string>  $itemIds
     * @param  array<string, string>  $itemNames
     * @return array<int, string>
     */
    private function normalizeLegacyItems(mixed $values, array $itemIds, array $itemNames): array
    {
        $normalized = [];

        foreach (is_array($values) ? $values : [] as $raw) {
            $itemId = $this->resolveLegacyItemId((string) $raw, $itemIds, $itemNames);
            if ($itemId === null) {
                continue;
            }

            $normalized[] = $itemId;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param  array<int, string>  $itemIds
     * @param  array<string, string>  $itemNames
     * @return array<int, array<string, int|float|string>>
     */
    private function buildGroupRewards(mixed $values, array $itemIds, array $itemNames): array
    {
        $rewards = [];
        $sort = 1;

        foreach (is_array($values) ? $values : [] as $raw) {
            $itemId = $this->resolveLegacyItemId((string) $raw, $itemIds, $itemNames);
            if ($itemId === null) {
                continue;
            }

            $rewards[] = [
                'item_id' => $itemId,
                'count_min' => 1,
                'count_max' => 1,
                'probability' => 1,
                'sort' => $sort,
            ];
            $sort++;
        }

        return $rewards;
    }

    /**
     * @param  array<int, string>  $itemIds
     * @param  array<string, string>  $itemNames
     */
    private function resolveLegacyItemId(string $raw, array $itemIds, array $itemNames): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $aliases = [
            '箕尾镇脉石碎片' => '箕尾镇脉石',
        ];

        $candidate = $aliases[$raw] ?? $raw;

        if (in_array($candidate, $itemIds, true)) {
            return $candidate;
        }

        return $itemNames[$candidate] ?? null;
    }
};
