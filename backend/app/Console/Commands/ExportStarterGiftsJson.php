<?php

namespace App\Console\Commands;

use App\Models\StarterGift;
use App\Services\ExportMetaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExportStarterGiftsJson extends Command
{
    protected $signature = 'game:export-starter-gifts';

    protected $description = 'Export enabled starter gifts to storage/app/exports/starter_gifts_v1.json';

    public function handle(): int
    {
        $rows = StarterGift::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (StarterGift $row): array => [
                'gift_id' => (string) $row->gift_id,
                'unlock_level' => (int) $row->unlock_level,
                'gift_name' => (string) $row->gift_name,
                'gift_role' => (string) ($row->gift_role ?? ''),
                'open_copy' => (string) ($row->open_copy ?? ''),
                'rewards' => is_array($row->rewards) ? array_values($row->rewards) : [],
                'must_claim' => (bool) $row->must_claim,
                'is_free' => (bool) $row->is_free,
                'icon_path' => (string) ($row->icon_path ?? ''),
                'banner_path' => (string) ($row->banner_path ?? ''),
                'sort_order' => (int) $row->sort_order,
            ])
            ->values()
            ->all();

        return $this->writePayload('starter_gifts', 'starter_gifts_v1.json', ['starter_gifts' => $rows]);
    }

    private function writePayload(string $key, string $filename, array $payloadWithoutMeta): int
    {
        $version = ExportMetaService::getNextVersion($key);
        $payload = ['meta' => ExportMetaService::makeMeta($key, $version, $payloadWithoutMeta)] + $payloadWithoutMeta;
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
        $this->info(sprintf('Exported %d rows -> %s (version=%d)', count($payloadWithoutMeta['starter_gifts']), $path, $version));

        return self::SUCCESS;
    }
}
