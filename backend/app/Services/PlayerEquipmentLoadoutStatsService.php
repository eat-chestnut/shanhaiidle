<?php

namespace App\Services;

use App\Models\PlayerEquipmentInstance;
use App\Models\PlayerEquipmentLoadout;

class PlayerEquipmentLoadoutStatsService
{
    /**
     * 套装件数统计位与护符星级连锁统计位固定一致，且都排除 talisman。
     * 统计位：main_weapon / sub_weapon / armor / leg / shoe / cloak / helmet / necklace / bracelet_1 / bracelet_2 / ring_1 / ring_2。
     * 数据来源：仅统计 player_equipment_loadouts 当前穿戴实例。
     *
     * @return array<int, string>
     */
    public static function trackedSlotTypes(): array
    {
        return PlayerEquipmentInstance::SET_COUNT_TRACKED_SLOT_TYPES;
    }

    /**
     * @return array<string, int>
     */
    public static function equippedSetPieceCounts(int $playerId): array
    {
        return PlayerEquipmentLoadout::query()
            ->join('player_equipment_instances as pei', function ($join): void {
                $join->on('player_equipment_loadouts.instance_id', '=', 'pei.instance_id')
                    ->on('player_equipment_loadouts.player_id', '=', 'pei.player_id');
            })
            ->where('player_equipment_loadouts.player_id', $playerId)
            ->whereIn('player_equipment_loadouts.slot_type', self::trackedSlotTypes())
            ->whereNotNull('pei.set_id')
            ->selectRaw('pei.set_id as set_id, COUNT(*) as piece_count')
            ->groupBy('pei.set_id')
            ->pluck('piece_count', 'set_id')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * @return array<string, int>
     */
    public static function trackedEquipmentStars(int $playerId): array
    {
        return PlayerEquipmentLoadout::query()
            ->join('player_equipment_instances as pei', function ($join): void {
                $join->on('player_equipment_loadouts.instance_id', '=', 'pei.instance_id')
                    ->on('player_equipment_loadouts.player_id', '=', 'pei.player_id');
            })
            ->where('player_equipment_loadouts.player_id', $playerId)
            ->whereIn('player_equipment_loadouts.slot_type', self::trackedSlotTypes())
            ->select(['player_equipment_loadouts.slot_type', 'pei.star'])
            ->get()
            ->mapWithKeys(static fn ($row): array => [
                (string) $row->slot_type => (int) $row->star,
            ])
            ->all();
    }

    /**
     * 护符星级连锁按“所有参与统计位的最小星级”判定；若有缺位则返回 0。
     */
    public static function trackedSlotsMinStar(int $playerId): int
    {
        $starsBySlot = self::trackedEquipmentStars($playerId);

        foreach (self::trackedSlotTypes() as $slotType) {
            if (! array_key_exists($slotType, $starsBySlot)) {
                return 0;
            }
        }

        return min($starsBySlot);
    }
}
