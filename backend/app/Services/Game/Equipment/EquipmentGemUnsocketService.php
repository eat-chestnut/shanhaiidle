<?php

namespace App\Services\Game\Equipment;

use App\Models\PlayerEquipmentGemSlot;
use App\Models\PlayerEquipmentInstance;
use Illuminate\Support\Facades\DB;

class EquipmentGemUnsocketService
{
    public function execute(int|string $playerId, string $instanceId, int $slotIndex): array
    {
        $safePlayerId = (int) $playerId;
        $safeInstanceId = trim($instanceId);

        if ($safePlayerId < 1 || $safeInstanceId === '' || $slotIndex < 1) {
            return $this->failure('invalid input');
        }

        return DB::transaction(function () use ($safePlayerId, $safeInstanceId, $slotIndex): array {
            /** @var PlayerEquipmentInstance|null $instance */
            $instance = PlayerEquipmentInstance::query()
                ->where('player_id', $safePlayerId)
                ->where('instance_id', $safeInstanceId)
                ->lockForUpdate()
                ->first();

            if (! $instance instanceof PlayerEquipmentInstance) {
                return $this->failure('equipment instance does not belong to player');
            }

            /** @var PlayerEquipmentGemSlot|null $slot */
            $slot = PlayerEquipmentGemSlot::query()
                ->where('instance_id', $safeInstanceId)
                ->where('slot_index', $slotIndex)
                ->lockForUpdate()
                ->first();

            if (! $slot instanceof PlayerEquipmentGemSlot) {
                return $this->failure(sprintf('slot %d was not found', $slotIndex));
            }

            if ($slot->gem_item_id === null) {
                return $this->failure(sprintf('slot %d has no gem', $slotIndex));
            }

            $removedGemItemId = (string) $slot->gem_item_id;
            $slot->gem_item_id = null;
            $slot->save();

            return $this->success([
                'instance_id' => $safeInstanceId,
                'slot_index' => $slotIndex,
                'removed_gem_item_id' => $removedGemItemId,
            ]);
        });
    }

    private function success(array $data): array
    {
        return [
            'ok' => true,
            'reason' => null,
            'data' => $data,
        ];
    }

    private function failure(string $reason): array
    {
        return [
            'ok' => false,
            'reason' => $reason,
            'data' => null,
        ];
    }
}
