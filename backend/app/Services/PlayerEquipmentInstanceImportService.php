<?php

namespace App\Services;

use App\Models\PlayerEquipmentGemSlot;
use App\Models\PlayerEquipmentInstance;
use App\Models\PlayerEquipmentLoadout;
use App\Support\PlayerEquipmentInstanceModuleSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class PlayerEquipmentInstanceImportService
{
    public const PROJECT_DATA_FILE = '../data/player_equipment_instance_examples_v1.json';

    /**
     * @return array{rules: array<string,mixed>, instances: array<int, array<string, mixed>>, loadouts: array<int, array<string, mixed>>, gem_slots: array<int, array<string, mixed>>, progression_examples: array<int, array<string, mixed>>}
     */
    public static function loadProjectPayload(): array
    {
        $path = base_path(self::PROJECT_DATA_FILE);
        if (! File::exists($path)) {
            throw new RuntimeException(sprintf('未找到玩家装备实例示例文件：%s', $path));
        }

        $decoded = json_decode((string) File::get($path), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('player_equipment_instance_examples_v1.json 解析失败。');
        }

        return PlayerEquipmentInstanceModuleSupport::normalizeProjectPayloadOrFail($decoded);
    }

    /**
     * @return array{instance_count:int,loadout_count:int,gem_slot_count:int}
     */
    public static function importFromProjectFile(): array
    {
        $payload = self::loadProjectPayload();

        return DB::transaction(function () use ($payload): array {
            $instanceIds = [];

            foreach ($payload['instances'] as $row) {
                $instanceIds[] = (string) $row['instance_id'];

                PlayerEquipmentInstance::query()->updateOrCreate(
                    ['instance_id' => (string) $row['instance_id']],
                    collect($row)->except('instance_id')->all(),
                );
            }

            if ($instanceIds === []) {
                PlayerEquipmentInstance::query()->delete();
            } else {
                PlayerEquipmentInstance::query()->whereNotIn('instance_id', $instanceIds)->delete();
            }

            PlayerEquipmentLoadout::query()->delete();
            foreach ($payload['loadouts'] as $row) {
                PlayerEquipmentLoadout::query()->create($row);
            }

            PlayerEquipmentGemSlot::query()->delete();
            foreach ($payload['gem_slots'] as $row) {
                PlayerEquipmentGemSlot::query()->create($row);
            }

            return [
                'instance_count' => count($payload['instances']),
                'loadout_count' => count($payload['loadouts']),
                'gem_slot_count' => count($payload['gem_slots']),
            ];
        });
    }
}
