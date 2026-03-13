<?php

namespace App\Services;

use App\Models\EquipmentSet;
use App\Models\EquipmentSetCraftRecipe;
use App\Models\EquipmentSetEffect;
use App\Models\EquipmentSetItem;
use App\Models\EquipmentSetRecipeCostItem;
use App\Support\EquipmentSetModuleSupport;
use Illuminate\Support\Facades\File;
use RuntimeException;

class EquipmentSetModuleExportService
{
    private const PROJECT_DATA_FILE = '../data/equipment_sets.json';

    /**
     * @return array{path:string,project_path:string,payload:array<string,mixed>}
     */
    public function exportToProjectFile(): array
    {
        $payloadWithoutMeta = [
            'equipment_set_rules' => EquipmentSetModuleSupport::rulesPayload(),
            'equipment_sets' => EquipmentSet::query()
                ->orderBy('sort_order')
                ->orderBy('set_level')
                ->orderBy('set_id')
                ->get()
                ->map(fn (EquipmentSet $set): array => EquipmentSetModuleSupport::exportSet($set))
                ->all(),
            'equipment_set_items' => EquipmentSetItem::query()
                ->orderBy('set_id')
                ->orderBy('sort_order')
                ->orderBy('slot_type')
                ->get()
                ->map(fn (EquipmentSetItem $row): array => EquipmentSetModuleSupport::exportSetItem($row))
                ->all(),
            'equipment_set_effects' => EquipmentSetEffect::query()
                ->orderBy('set_id')
                ->orderBy('piece_count')
                ->orderBy('sort_order')
                ->orderBy('effect_key')
                ->get()
                ->map(fn (EquipmentSetEffect $row): array => EquipmentSetModuleSupport::exportEffect($row))
                ->all(),
            'equipment_set_craft_recipes' => EquipmentSetCraftRecipe::query()
                ->orderBy('set_id')
                ->orderBy('sort_order')
                ->orderBy('slot_type')
                ->get()
                ->map(fn (EquipmentSetCraftRecipe $row): array => EquipmentSetModuleSupport::exportRecipe($row))
                ->all(),
            'equipment_set_recipe_cost_items' => EquipmentSetRecipeCostItem::query()
                ->orderBy('recipe_id')
                ->orderBy('sort_order')
                ->orderBy('item_id')
                ->get()
                ->map(fn (EquipmentSetRecipeCostItem $row): array => EquipmentSetModuleSupport::exportCostItem($row))
                ->all(),
        ];

        $meta = ExportMetaService::makeMeta('equipment_sets', $payloadWithoutMeta);
        $payload = ['meta' => $meta] + $payloadWithoutMeta;

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('equipment_sets.json 序列化失败。');
        }

        $dir = storage_path('app/exports');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'equipment_sets.json';
        $projectPath = base_path(self::PROJECT_DATA_FILE);
        File::put($path, $json);
        File::put($projectPath, $json);

        return [
            'path' => $path,
            'project_path' => $projectPath,
            'payload' => $payload,
        ];
    }
}
