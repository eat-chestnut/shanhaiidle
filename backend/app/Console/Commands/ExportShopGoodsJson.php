<?php

namespace App\Console\Commands;

use App\Models\ShopGood;
use App\Services\ExportMetaService;
use App\Support\AdminOptions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExportShopGoodsJson extends Command
{
    private const PROJECT_DATA_FILE = '../data/shop_goods_v1.json';

    protected $signature = 'game:export-shop-goods';

    protected $description = 'Export enabled shop goods to storage/app/exports/shop_goods_v1.json';

    public function handle(): int
    {
        $rows = ShopGood::query()
            ->where('is_enabled', true)
            ->orderBy('shop_type')
            ->orderBy('sort_order')
            ->orderBy('goods_id')
            ->get()
            ->map(fn (ShopGood $row): array => [
                'goods_id' => (string) $row->goods_id,
                'shop_type' => (string) $row->shop_type,
                'shop_type_name' => AdminOptions::optionLabel(AdminOptions::shopTypeOptions(), (string) $row->shop_type),
                'title' => (string) $row->title,
                'subtitle' => (string) ($row->subtitle ?? ''),
                'desc' => (string) ($row->desc ?? ''),
                'reward_item_id' => (string) $row->reward_item_id,
                'reward_item_name' => AdminOptions::itemName((string) $row->reward_item_id),
                'reward_count' => (int) $row->reward_count,
                'cost_currency_type' => (string) $row->cost_currency_type,
                'cost_currency_type_name' => AdminOptions::optionLabel(AdminOptions::shopCurrencyOptions(), (string) $row->cost_currency_type),
                'cost_amount' => (int) $row->cost_amount,
                'unlock_level' => (int) $row->unlock_level,
                'daily_limit' => $row->daily_limit !== null ? (int) $row->daily_limit : null,
                'weekly_limit' => $row->weekly_limit !== null ? (int) $row->weekly_limit : null,
                'lifetime_limit' => $row->lifetime_limit !== null ? (int) $row->lifetime_limit : null,
                'sort_order' => (int) $row->sort_order,
                'icon' => (string) ($row->icon ?? ''),
                'is_enabled' => (bool) $row->is_enabled,
                'starts_at' => $row->starts_at?->toDateTimeString(),
                'ends_at' => $row->ends_at?->toDateTimeString(),
            ])
            ->values()
            ->all();

        $payloadWithoutMeta = [
            'shop_goods' => $rows,
        ];
        $meta = ExportMetaService::makeMeta('shop_goods', $payloadWithoutMeta);
        $payload = ['meta' => $meta] + $payloadWithoutMeta;

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('shop_goods_v1.json 序列化失败。');
        }

        $dir = storage_path('app/exports');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'shop_goods_v1.json';
        $projectDataPath = base_path(self::PROJECT_DATA_FILE);
        File::put($path, $json);
        File::put($projectDataPath, $json);

        $this->info(sprintf('Exported %d shop goods -> %s, %s', count($rows), $path, $projectDataPath));

        return self::SUCCESS;
    }
}
