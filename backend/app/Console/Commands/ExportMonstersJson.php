<?php

namespace App\Console\Commands;

use App\Models\Monster;
use App\Services\ExportMetaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExportMonstersJson extends Command
{
    protected $signature = 'game:export-monsters';

    protected $description = 'Export enabled monsters to storage/app/exports/monsters.json';

    public function handle(): int
    {
        $monsters = Monster::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (Monster $monster): array {
                return [
                    'id' => (string) $monster->id,
                    'name' => (string) $monster->name,
                    'kind' => (string) $monster->kind,
                    'hp' => (int) $monster->hp,
                    'atk' => (int) $monster->atk,
                    'def' => (int) $monster->def,
                    'speed' => (int) $monster->speed,
                    'radius' => (int) $monster->radius,
                    'aggro_range' => (int) $monster->aggro_range,
                    'attack_interval' => (float) $monster->attack_interval,
                    'attack_range' => (int) $monster->attack_range,
                    'exp' => (int) $monster->exp,
                    'drop_bonus_percent' => (int) $monster->drop_bonus_percent,
                    'dex_gold' => (int) $monster->dex_gold,
                    'icon' => (string) ($monster->icon ?? ''),
                ];
            })
            ->values()
            ->all();

        $payloadWithoutMeta = [
            'monsters' => $monsters,
        ];
        $version = ExportMetaService::getNextVersion('monsters');
        $meta = ExportMetaService::makeMeta('monsters', $version, $payloadWithoutMeta);
        $payload = ['meta' => $meta] + $payloadWithoutMeta;

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('monsters.json 序列化失败。');
        }

        $dir = storage_path('app/exports');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'monsters.json';
        File::put($path, $json);

        $this->info(sprintf('Exported %d monsters -> %s (version=%d)', count($monsters), $path, $version));

        return self::SUCCESS;
    }
}
