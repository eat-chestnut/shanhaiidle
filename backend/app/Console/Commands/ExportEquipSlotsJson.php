<?php

namespace App\Console\Commands;

use App\Models\EquipSlot;
use App\Services\ExportMetaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExportEquipSlotsJson extends Command
{
    protected $signature = 'game:export-equip-slots';

    protected $description = 'Export enabled equip slots to storage/app/exports/equip_slots_v1.json';

    public function handle(): int
    {
        $rows = EquipSlot::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('slot_id')
            ->get()
            ->map(fn (EquipSlot $slot): array => [
                'slot_id' => (string) $slot->slot_id,
                'slot_name' => (string) $slot->slot_name,
                'slot_type' => (string) $slot->slot_type,
                'unlock_level' => (int) $slot->unlock_level,
                'equip_limit' => (int) $slot->equip_limit,
                'is_set_slot' => (bool) $slot->is_set_slot,
                'can_drop_blue_gear' => (bool) $slot->can_drop_blue_gear,
                'can_craft' => (bool) $slot->can_craft,
                'can_exchange' => (bool) $slot->can_exchange,
                'can_star_up' => (bool) $slot->can_star_up,
                'can_rank_up' => (bool) $slot->can_rank_up,
                'sort_order' => (int) $slot->sort_order,
            ])
            ->values()
            ->all();

        $payloadWithoutMeta = ['equip_slots' => $rows];
        $meta = ExportMetaService::makeMeta('equip_slots', $payloadWithoutMeta);
        $payload = ['meta' => $meta] + $payloadWithoutMeta;

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('equip_slots_v1.json 序列化失败。');
        }

        $dir = storage_path('app/exports');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'equip_slots_v1.json';
        File::put($path, $json);

        $this->info(sprintf('Exported %d equip slots -> %s', count($rows), $path));

        return self::SUCCESS;
    }
}
