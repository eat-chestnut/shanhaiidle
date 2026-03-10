<?php

namespace App\Console\Commands;

use App\Models\EquipmentSet;
use App\Services\ExportMetaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExportEquipmentSetsJson extends Command
{
    protected $signature = 'game:export-equipment-sets';

    protected $description = 'Export enabled equipment sets to storage/app/exports/equipment_sets.json';

    public function handle(): int
    {
        $rows = EquipmentSet::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (EquipmentSet $set): array {
                $thresholds = $this->normalizeThresholds($set->thresholds);
                $slotIds = is_array($set->slot_ids) ? array_values($set->slot_ids) : [];
                $pieceCount = (int) ($set->piece_count ?? 0);
                $maxPieces = (int) $set->max_pieces;
                if ($pieceCount <= 0) {
                    $pieceCount = $maxPieces;
                }

                return [
                    'id' => (string) $set->id,
                    'set_line_id' => (string) ($set->set_line_id ?? ''),
                    'name' => (string) $set->name,
                    'sect' => (string) ($set->sect ?? ''),
                    'flow_tag' => (string) ($set->flow_tag ?? ''),
                    'stage' => (int) ($set->stage ?? 0),
                    'piece_count' => $pieceCount,
                    'slot_ids' => $slotIds,
                    'max_pieces' => $maxPieces,
                    'thresholds' => $thresholds,
                    'description' => (string) ($set->description ?? ''),
                    'sort_order' => (int) $set->sort_order,
                ];
            })
            ->values()
            ->all();

        $payloadWithoutMeta = [
            'equipment_sets' => $rows,
        ];
        $version = ExportMetaService::getNextVersion('equipment_sets');
        $meta = ExportMetaService::makeMeta('equipment_sets', $version, $payloadWithoutMeta);
        $payload = ['meta' => $meta] + $payloadWithoutMeta;

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('equipment_sets.json 序列化失败。');
        }

        $dir = storage_path('app/exports');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'equipment_sets.json';
        File::put($path, $json);

        $this->info(sprintf('Exported %d equipment sets -> %s (version=%d)', count($rows), $path, $version));

        return self::SUCCESS;
    }

    private function normalizeThresholds(mixed $thresholdsRaw): array
    {
        $rows = [];
        if (! is_array($thresholdsRaw)) {
            return $rows;
        }

        foreach ($thresholdsRaw as $thresholdRow) {
            if (! is_array($thresholdRow)) {
                continue;
            }
            $count = max(1, (int) ($thresholdRow['count'] ?? 0));
            $bonuses = [];
            $bonusRaw = $thresholdRow['bonuses'] ?? [];
            if (is_array($bonusRaw)) {
                foreach ($bonusRaw as $bonusRow) {
                    if (! is_array($bonusRow)) {
                        continue;
                    }
                    $type = (string) ($bonusRow['type'] ?? '');
                    $val = max(0, (int) ($bonusRow['val'] ?? 0));
                    if ($type === 'stat') {
                        $stat = trim((string) ($bonusRow['stat'] ?? ''));
                        if ($stat === '') {
                            continue;
                        }
                        $bonuses[] = [
                            'type' => 'stat',
                            'stat' => $stat,
                            'val' => $val,
                        ];
                    } elseif ($type === 'skill_level') {
                        $skillId = trim((string) ($bonusRow['skill_id'] ?? ''));
                        if ($skillId === '') {
                            continue;
                        }
                        $bonuses[] = [
                            'type' => 'skill_level',
                            'skill_id' => $skillId,
                            'val' => $val,
                        ];
                    }
                }
            }

            $rows[] = [
                'count' => $count,
                'bonuses' => array_values($bonuses),
            ];
        }

        return array_values($rows);
    }
}
