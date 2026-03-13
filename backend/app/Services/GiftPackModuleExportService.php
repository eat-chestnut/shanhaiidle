<?php

namespace App\Services;

use App\Models\GiftPack;
use App\Models\GiftPackItem;
use Illuminate\Support\Facades\File;
use RuntimeException;

class GiftPackModuleExportService
{
    private const FILE_NAME = 'gift_pack_module_v1.json';

    private const PROJECT_DATA_FILE = '../data/gift_pack_module_v1.json';

    /**
     * @return array{gift_pack_count:int,latest_path:string,project_data_path:string}
     */
    public function export(): array
    {
        $payloadWithoutMeta = [
            'gift_pack_module' => [
                'gift_packs' => GiftPack::query()
                    ->where('is_enabled', true)
                    ->orderBy('sort_order')
                    ->orderBy('pack_id')
                    ->get()
                    ->map(fn (GiftPack $pack): array => [
                        'pack_id' => (string) $pack->pack_id,
                        'item_id' => (string) $pack->item_id,
                        'pack_name' => (string) $pack->pack_name,
                        'display_name' => (string) $pack->display_name,
                        'pack_type' => (string) $pack->pack_type,
                        'pack_mode' => (string) $pack->pack_mode,
                        'open_mode' => (string) $pack->open_mode,
                        'select_count_min' => $pack->select_count_min !== null ? (int) $pack->select_count_min : null,
                        'select_count_max' => $pack->select_count_max !== null ? (int) $pack->select_count_max : null,
                        'desc' => $pack->desc,
                        'icon' => $pack->icon,
                        'is_enabled' => (bool) $pack->is_enabled,
                        'sort_order' => (int) $pack->sort_order,
                        'remark' => $pack->remark,
                    ])->values()->all(),
                'gift_pack_items' => GiftPackItem::query()
                    ->where('is_enabled', true)
                    ->whereHas('pack', fn ($query) => $query->where('is_enabled', true))
                    ->orderBy('pack_id')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (GiftPackItem $item): array => [
                        'pack_id' => (string) $item->pack_id,
                        'item_id' => (string) $item->item_id,
                        'content_mode' => (string) $item->content_mode,
                        'count_min' => (int) $item->count_min,
                        'count_max' => (int) $item->count_max,
                        'weight' => (int) $item->weight,
                        'recommended_sect' => (string) $item->recommended_sect,
                        'display_note' => $item->display_note,
                        'sort_order' => (int) $item->sort_order,
                        'is_enabled' => (bool) $item->is_enabled,
                        'remark' => $item->remark,
                    ])->values()->all(),
            ],
        ];
        $payload = ['meta' => ExportMetaService::makeMeta('gift_pack_module', $payloadWithoutMeta)] + $payloadWithoutMeta;

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('gift_pack_module_v1.json 序列化失败。');
        }

        $dir = storage_path('app/exports');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $latestPath = $dir . DIRECTORY_SEPARATOR . self::FILE_NAME;
        $projectDataPath = base_path(self::PROJECT_DATA_FILE);
        File::put($latestPath, $json);
        File::put($projectDataPath, $json);

        return [
            'gift_pack_count' => count($payloadWithoutMeta['gift_pack_module']['gift_packs']),
            'latest_path' => $latestPath,
            'project_data_path' => $projectDataPath,
        ];
    }
}
