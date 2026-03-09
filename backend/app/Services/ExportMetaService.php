<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ExportMetaService
{
    public static function getNextVersion(string $key): int
    {
        $key = trim($key);
        if ($key === '') {
            throw new InvalidArgumentException('导出 key 不能为空。');
        }

        $settingKey = "export_version:{$key}";

        return DB::transaction(function () use ($settingKey): int {
            $counter = AppSetting::query()
                ->where('key', $settingKey)
                ->lockForUpdate()
                ->first();

            $nextVersion = $counter ? ((int) $counter->value + 1) : 1;
            AppSetting::setValue($settingKey, $nextVersion);

            return $nextVersion;
        });
    }

    public static function makeMeta(string $key, int $version, array $payloadWithoutMeta): array
    {
        $encoded = json_encode($payloadWithoutMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $sha256 = hash('sha256', $encoded === false ? '{}' : $encoded);

        return [
            'key' => $key,
            'version' => max(0, $version),
            'exported_at' => now()->format('Y-m-d H:i:s'),
            'sha256' => $sha256,
        ];
    }
}

