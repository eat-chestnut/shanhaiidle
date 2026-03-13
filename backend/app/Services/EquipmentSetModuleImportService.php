<?php

namespace App\Services;

use App\Models\EquipmentSet;
use App\Models\EquipmentSetCraftRecipe;
use App\Models\EquipmentSetEffect;
use App\Models\EquipmentSetItem;
use App\Models\EquipmentSetRecipeCostItem;
use App\Support\EquipmentSetModuleSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class EquipmentSetModuleImportService
{
    public const PROJECT_DATA_FILE = '../data/equipment_sets.json';

    /**
     * @return array{
     *     equipment_sets: array<int, array<string, mixed>>,
     *     equipment_set_items: array<int, array<string, mixed>>,
     *     equipment_set_effects: array<int, array<string, mixed>>,
     *     equipment_set_craft_recipes: array<int, array<string, mixed>>,
     *     equipment_set_recipe_cost_items: array<int, array<string, mixed>>
     * }
     */
    public static function loadProjectPayload(): array
    {
        $path = base_path(self::PROJECT_DATA_FILE);
        if (! File::exists($path)) {
            throw new RuntimeException(sprintf('未找到套装配置文件：%s', $path));
        }

        $decoded = json_decode((string) File::get($path), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('equipment_sets.json 解析失败。');
        }

        return EquipmentSetModuleSupport::normalizeRowsOrFail(
            is_array($decoded['equipment_sets'] ?? null) ? $decoded['equipment_sets'] : [],
            is_array($decoded['equipment_set_items'] ?? null) ? $decoded['equipment_set_items'] : [],
            is_array($decoded['equipment_set_effects'] ?? null) ? $decoded['equipment_set_effects'] : [],
            is_array($decoded['equipment_set_craft_recipes'] ?? null) ? $decoded['equipment_set_craft_recipes'] : [],
            is_array($decoded['equipment_set_recipe_cost_items'] ?? null) ? $decoded['equipment_set_recipe_cost_items'] : [],
        );
    }

    /**
     * @return array{set_count:int,item_count:int,effect_count:int,recipe_count:int,cost_item_count:int}
     */
    public static function importFromProjectFile(): array
    {
        $payload = self::loadProjectPayload();

        return DB::transaction(function () use ($payload): array {
            $setIds = [];

            foreach ($payload['sets'] as $row) {
                $setIds[] = (string) $row['set_id'];

                EquipmentSet::query()->updateOrCreate(
                    ['set_id' => (string) $row['set_id']],
                    $row,
                );
            }

            if ($setIds === []) {
                EquipmentSet::query()->delete();
            } else {
                EquipmentSet::query()->whereNotIn('set_id', $setIds)->delete();
            }

            EquipmentSetItem::query()->delete();
            foreach ($payload['set_items'] as $row) {
                EquipmentSetItem::query()->create($row);
            }

            EquipmentSetEffect::query()->delete();
            foreach ($payload['effects'] as $row) {
                EquipmentSetEffect::query()->create($row);
            }

            EquipmentSetCraftRecipe::query()->delete();
            foreach ($payload['recipes'] as $row) {
                EquipmentSetCraftRecipe::query()->create($row);
            }

            EquipmentSetRecipeCostItem::query()->delete();
            foreach ($payload['cost_items'] as $row) {
                EquipmentSetRecipeCostItem::query()->create($row);
            }

            return [
                'set_count' => count($payload['sets']),
                'item_count' => count($payload['set_items']),
                'effect_count' => count($payload['effects']),
                'recipe_count' => count($payload['recipes']),
                'cost_item_count' => count($payload['cost_items']),
            ];
        });
    }
}
