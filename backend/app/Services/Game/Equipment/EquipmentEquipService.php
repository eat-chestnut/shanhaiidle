<?php

namespace App\Services\Game\Equipment;

use App\Models\PlayerEquipmentInstance;
use App\Models\PlayerEquipmentLoadout;
use Illuminate\Support\Facades\DB;

class EquipmentEquipService
{
    public function execute(int|string $playerId, string $instanceId, string $targetSlotType): array
    {
        $safePlayerId = (int) $playerId;
        $safeInstanceId = trim($instanceId);
        $safeTargetSlotType = trim($targetSlotType);

        if ($safePlayerId < 1 || $safeInstanceId === '' || $safeTargetSlotType === '') {
            return $this->failure('invalid input');
        }

        if (! array_key_exists($safeTargetSlotType, PlayerEquipmentInstance::SLOT_TYPE_OPTIONS)) {
            return $this->failure(sprintf('target slot type %s is invalid', $safeTargetSlotType));
        }

        return DB::transaction(function () use ($safePlayerId, $safeInstanceId, $safeTargetSlotType): array {
            /** @var PlayerEquipmentInstance|null $instance */
            $instance = PlayerEquipmentInstance::query()
                ->where('player_id', $safePlayerId)
                ->where('instance_id', $safeInstanceId)
                ->lockForUpdate()
                ->first();

            if (! $instance instanceof PlayerEquipmentInstance) {
                return $this->failure('equipment instance does not belong to player');
            }

            if (! $this->isCompatibleTargetSlot((string) $instance->slot_type, $safeTargetSlotType)) {
                return $this->failure(sprintf(
                    'instance slot type %s is not compatible with target slot %s',
                    (string) $instance->slot_type,
                    $safeTargetSlotType,
                ));
            }

            /** @var PlayerEquipmentLoadout|null $currentLoadout */
            $currentLoadout = PlayerEquipmentLoadout::query()
                ->where('instance_id', $safeInstanceId)
                ->lockForUpdate()
                ->first();

            /** @var PlayerEquipmentLoadout|null $targetLoadout */
            $targetLoadout = PlayerEquipmentLoadout::query()
                ->where('player_id', $safePlayerId)
                ->where('slot_type', $safeTargetSlotType)
                ->lockForUpdate()
                ->first();

            $replacedInstanceId = null;
            if ($targetLoadout instanceof PlayerEquipmentLoadout && (string) $targetLoadout->instance_id !== $safeInstanceId) {
                $replacedInstanceId = (string) $targetLoadout->instance_id;

                PlayerEquipmentInstance::query()
                    ->where('player_id', $safePlayerId)
                    ->where('instance_id', $replacedInstanceId)
                    ->lockForUpdate()
                    ->update(['is_equipped' => false]);
            }

            if ($currentLoadout instanceof PlayerEquipmentLoadout && (string) $currentLoadout->slot_type !== $safeTargetSlotType) {
                $currentLoadout->delete();
            }

            $instance->slot_type = $safeTargetSlotType;
            $instance->is_equipped = true;
            $instance->save();

            PlayerEquipmentLoadout::query()->updateOrCreate(
                [
                    'player_id' => $safePlayerId,
                    'slot_type' => $safeTargetSlotType,
                ],
                [
                    'instance_id' => $safeInstanceId,
                ],
            );

            return $this->success([
                'equipped_instance_id' => $safeInstanceId,
                'target_slot_type' => $safeTargetSlotType,
                'replaced_instance_id' => $replacedInstanceId,
            ]);
        });
    }

    private function isCompatibleTargetSlot(string $instanceSlotType, string $targetSlotType): bool
    {
        $safeInstanceSlotType = trim($instanceSlotType);

        if (in_array($safeInstanceSlotType, ['ring_1', 'ring_2'], true)) {
            return in_array($targetSlotType, ['ring_1', 'ring_2'], true);
        }

        if (in_array($safeInstanceSlotType, ['bracelet_1', 'bracelet_2'], true)) {
            return in_array($targetSlotType, ['bracelet_1', 'bracelet_2'], true);
        }

        return $safeInstanceSlotType === $targetSlotType;
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
