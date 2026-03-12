<?php

namespace App\Console\Commands;

use App\Models\EquipmentSetSource;
use App\Services\ExportMetaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExportEquipmentSetSourcesJson extends Command
{
    protected $signature = 'game:export-equipment-set-sources';

    protected $description = 'Export enabled equipment set sources to storage/app/exports/equipment_set_sources_v1.json';

    public function handle(): int
    {
        $rows = EquipmentSetSource::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (EquipmentSetSource $row): array => [
                'set_source_id' => (string) $row->set_source_id,
                'set_line_id' => (string) $row->set_line_id,
                'set_name' => (string) $row->set_name,
                'flow_tag' => (string) ($row->flow_tag ?? ''),
                'set_stage' => (int) $row->set_stage,
                'main_mat_1' => (string) ($row->main_mat_1 ?? ''),
                'main_mat_2' => (string) ($row->main_mat_2 ?? ''),
                'sub_materials' => is_array($row->sub_materials) ? array_values($row->sub_materials) : [],
                'source_maps' => is_array($row->source_maps) ? array_values($row->source_maps) : [],
                'source_bosses' => is_array($row->source_bosses) ? array_values($row->source_bosses) : [],
                'need_blueprint' => (bool) $row->need_blueprint,
                'blueprint_source' => (string) ($row->blueprint_source ?? ''),
                'craft_desc' => (string) ($row->craft_desc ?? ''),
                'icon_path' => (string) ($row->icon_path ?? ''),
                'image_path' => (string) ($row->image_path ?? ''),
                'sort_order' => (int) $row->sort_order,
            ])
            ->values()
            ->all();

        return $this->writePayload('equipment_set_sources', 'equipment_set_sources_v1.json', ['equipment_set_sources' => $rows]);
    }

    private function writePayload(string $key, string $filename, array $payloadWithoutMeta): int
    {
        $payload = ['meta' => ExportMetaService::makeMeta($key, $payloadWithoutMeta)] + $payloadWithoutMeta;
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException("{$filename} 序列化失败。");
        }

        $dir = storage_path('app/exports');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }
        $path = $dir . DIRECTORY_SEPARATOR . $filename;
        File::put($path, $json);
        $this->info(sprintf('Exported %d rows -> %s', count($payloadWithoutMeta['equipment_set_sources']), $path));

        return self::SUCCESS;
    }
}
