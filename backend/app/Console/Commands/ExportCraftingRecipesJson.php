<?php

namespace App\Console\Commands;

use App\Models\CraftingRecipe;
use App\Services\ExportMetaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExportCraftingRecipesJson extends Command
{
    protected $signature = 'game:export-crafting-recipes';

    protected $description = 'Export enabled crafting recipes to storage/app/exports/crafting_recipes_v1.json';

    public function handle(): int
    {
        $rows = CraftingRecipe::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('recipe_id')
            ->get()
            ->map(function (CraftingRecipe $row): array {
                $costItems = [];
                if (is_array($row->cost_items)) {
                    foreach ($row->cost_items as $entry) {
                        if (! is_array($entry)) {
                            continue;
                        }
                        $itemId = trim((string) ($entry['item_id'] ?? ''));
                        $count = (int) ($entry['count'] ?? 0);
                        if ($itemId === '' || $count <= 0) {
                            continue;
                        }
                        $costItems[] = ['item_id' => $itemId, 'count' => $count];
                    }
                }

                return [
                    'recipe_id' => (string) $row->recipe_id,
                    'recipe_type' => (string) $row->recipe_type,
                    'output_type' => (string) $row->output_type,
                    'output_id' => (string) $row->output_id,
                    'output_count' => (int) $row->output_count,
                    'unlock_level' => (int) $row->unlock_level,
                    'cost_items' => $costItems,
                    'cost_gold' => (int) $row->cost_gold,
                    'cost_currency' => (string) ($row->cost_currency ?? ''),
                    'notes' => (string) ($row->notes ?? ''),
                    'sort_order' => (int) $row->sort_order,
                ];
            })
            ->values()
            ->all();

        $payloadWithoutMeta = ['crafting_recipes' => $rows];
        $version = ExportMetaService::getNextVersion('crafting_recipes');
        $meta = ExportMetaService::makeMeta('crafting_recipes', $version, $payloadWithoutMeta);
        $payload = ['meta' => $meta] + $payloadWithoutMeta;

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('crafting_recipes_v1.json 序列化失败。');
        }

        $dir = storage_path('app/exports');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'crafting_recipes_v1.json';
        File::put($path, $json);

        $this->info(sprintf('Exported %d crafting recipes -> %s (version=%d)', count($rows), $path, $version));

        return self::SUCCESS;
    }
}
