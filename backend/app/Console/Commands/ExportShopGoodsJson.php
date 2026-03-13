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
            ->orderBy('shop_tab')
            ->orderBy('sort_order')
            ->orderBy('goods_id')
            ->get()
            ->map(fn (ShopGood $row): array => [
                'goods_id' => (string) $row->goods_id,
                'title' => (string) $row->title,
                'display_name' => (string) $row->display_name,
                'shop_tab' => (string) $row->shop_tab,
                'shop_tab_name' => AdminOptions::optionLabel(AdminOptions::shopTabOptions(), (string) $row->shop_tab),
                'goods_type' => (string) $row->goods_type,
                'goods_type_name' => AdminOptions::optionLabel(AdminOptions::shopGoodsTypeOptions(), (string) $row->goods_type),
                'reward_item_id' => (string) $row->reward_item_id,
                'reward_item_name' => AdminOptions::itemName((string) $row->reward_item_id),
                'reward_count' => (int) $row->reward_count,
                'price_item_id' => (string) $row->price_item_id,
                'price_item_name' => AdminOptions::itemName((string) $row->price_item_id),
                'price_amount' => (int) $row->price_amount,
                'original_price_amount' => $row->original_price_amount !== null ? (int) $row->original_price_amount : null,
                'unlock_level' => (int) $row->unlock_level,
                'buy_limit_type' => (string) $row->buy_limit_type,
                'buy_limit_type_name' => AdminOptions::optionLabel(AdminOptions::shopBuyLimitTypeOptions(), (string) $row->buy_limit_type),
                'buy_limit_value' => (int) $row->buy_limit_value,
                'is_recommended' => (bool) $row->is_recommended,
                'is_enabled' => (bool) $row->is_enabled,
                'sort_order' => (int) $row->sort_order,
                'desc' => (string) ($row->desc ?? ''),
                'icon' => (string) ($row->icon ?? ''),
                'remark' => (string) ($row->remark ?? ''),
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
