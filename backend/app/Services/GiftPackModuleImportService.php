<?php

namespace App\Services;

use App\Models\GiftPack;
use App\Models\GiftPackItem;
use App\Support\GiftPackModuleSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class GiftPackModuleImportService
{
    public const PROJECT_DATA_FILE = '../data/gift_pack_module_v1.json';

    /**
     * @return array{gift_packs: array<int, array<string, mixed>>, gift_pack_items: array<int, array<string, mixed>>}
     */
    public static function loadProjectPayload(): array
    {
        $path = base_path(self::PROJECT_DATA_FILE);
        if (! File::exists($path)) {
            throw new RuntimeException(sprintf('未找到礼包模块数据文件：%s', $path));
        }

        $decoded = json_decode((string) File::get($path), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('gift_pack_module_v1.json 解析失败。');
        }

        $module = $decoded['gift_pack_module'] ?? null;
        if (! is_array($module)) {
            throw new RuntimeException('gift_pack_module 节点缺失。');
        }

        $packs = is_array($module['gift_packs'] ?? null) ? array_values($module['gift_packs']) : [];
        $packItems = is_array($module['gift_pack_items'] ?? null) ? array_values($module['gift_pack_items']) : [];

        GiftPackModuleSupport::validateModuleRowsOrFail($packs, $packItems);

        return [
            'gift_packs' => array_map(fn (array $row): array => GiftPackModuleSupport::normalizePack($row), $packs),
            'gift_pack_items' => array_values($packItems),
        ];
    }

    /**
     * @return array{gift_pack_count:int,gift_pack_item_count:int}
     */
    public static function importFromProjectFile(): array
    {
        $payload = self::loadProjectPayload();

        return DB::transaction(function () use ($payload): array {
            $packIds = [];
            $groupedItems = [];

            foreach ($payload['gift_pack_items'] as $row) {
                $packId = trim((string) ($row['pack_id'] ?? ''));
                $contentMode = trim((string) ($row['content_mode'] ?? 'fixed'));
                $groupedItems[$packId][$contentMode][] = $row;
            }

            foreach ($payload['gift_packs'] as $row) {
                $packIds[] = (string) $row['pack_id'];

                GiftPack::query()->updateOrCreate(
                    ['pack_id' => (string) $row['pack_id']],
                    collect($row)->except('pack_id')->all(),
                );

                GiftPackItem::query()->where('pack_id', (string) $row['pack_id'])->delete();

                $fixedItems = GiftPackModuleSupport::normalizePackItems($groupedItems[$row['pack_id']]['fixed'] ?? [], 'fixed');
                $selectableItems = GiftPackModuleSupport::normalizePackItems($groupedItems[$row['pack_id']]['selectable'] ?? [], 'selectable');

                foreach (array_merge($fixedItems, $selectableItems) as $item) {
                    GiftPackItem::query()->create([
                        'pack_id' => (string) $row['pack_id'],
                        ...$item,
                    ]);
                }
            }

            if ($packIds === []) {
                GiftPack::query()->delete();
            } else {
                GiftPack::query()->whereNotIn('pack_id', $packIds)->delete();
            }

            return [
                'gift_pack_count' => count($packIds),
                'gift_pack_item_count' => GiftPackItem::query()->count(),
            ];
        });
    }
}
