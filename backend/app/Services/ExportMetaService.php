<?php

namespace App\Services;

class ExportMetaService
{
    public static function makeMeta(string $key, array $payloadWithoutMeta, int $schemaVersion = 1): array
    {
        $encoded = json_encode($payloadWithoutMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $sha256 = hash('sha256', $encoded === false ? '{}' : $encoded);

        return [
            'key' => $key,
            'schema_version' => max(1, $schemaVersion),
            'exported_at' => now()->format('Y-m-d H:i:s'),
            'sha256' => $sha256,
        ];
    }
}
