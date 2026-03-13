<?php

namespace App\Services\Game\Equipment;

use App\Models\EquipmentStarSlotUnlock;
use App\Models\Gem;
use App\Models\PlayerEquipmentGemSlot;
use App\Models\PlayerEquipmentInstance;
use App\Models\ShopPlayerProfile;
use Illuminate\Support\Facades\DB;

class EquipmentGemSocketService
{
    public function __construct(
        private readonly EquipmentGemSlotResolver $gemSlotResolver,
    ) {
    }

    public function execute(int|string $playerId, string $instanceId, int $slotIndex, string $gemItemId): array
    {
        $safePlayerId = (int) $playerId;
        $safeProfilePlayerId = trim((string) $playerId);
        $safeInstanceId = trim($instanceId);
        $safeGemItemId = trim($gemItemId);

        if ($safePlayerId < 1 || $safeInstanceId === '' || $slotIndex < 1 || $safeGemItemId === '') {
            return $this->failure('invalid input');
        }

        return DB::transaction(function () use ($safePlayerId, $safeProfilePlayerId, $safeInstanceId, $slotIndex, $safeGemItemId): array {
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

            if (! (bool) $slot->is_unlocked) {
                return $this->failure(sprintf('slot %d is not unlocked', $slotIndex));
            }

            if ($slot->gem_item_id !== null) {
                return $this->failure(sprintf('slot %d already has a gem', $slotIndex));
            }

            /** @var ShopPlayerProfile|null $profile */
            $profile = ShopPlayerProfile::query()
                ->where('player_id', $safeProfilePlayerId)
                ->lockForUpdate()
                ->first();

            if (! $profile instanceof ShopPlayerProfile) {
                return $this->failure('player inventory profile not found');
            }

            $ownedCount = max(0, (int) (($profile->inventory ?? [])[$safeGemItemId] ?? 0));
            if ($ownedCount < 1) {
                return $this->failure(sprintf('player does not own gem item %s', $safeGemItemId));
            }

            /** @var Gem|null $gem */
            $gem = Gem::query()
                ->where('item_id', $safeGemItemId)
                ->where('is_enabled', true)
                ->first();

            if (! $gem instanceof Gem) {
                return $this->failure(sprintf('gem item %s was not found', $safeGemItemId));
            }

            $slotUnlockRules = EquipmentStarSlotUnlock::query()
                ->where('is_enabled', true)
                ->orderBy('required_star')
                ->get(['slot_index', 'slot_group', 'required_star'])
                ->map(fn (EquipmentStarSlotUnlock $row): array => [
                    'slot_index' => (int) $row->slot_index,
                    'slot_group' => (string) $row->slot_group,
                    'required_star' => (int) $row->required_star,
                ])
                ->all();

            $resolverResult = $this->gemSlotResolver->canSocketGem(
                $this->instanceResolverPayload($instance),
                $slotIndex,
                ['item_id' => $safeGemItemId],
                [
                    'item_id' => (string) $gem->item_id,
                    'slot_group' => (string) $gem->slot_group,
                ],
                $slotUnlockRules,
            );

            if (! ($resolverResult['ok'] ?? false)) {
                return $this->failure($this->mapResolverFailure($resolverResult, $slotIndex, $safeGemItemId));
            }

            $slot->gem_item_id = $safeGemItemId;
            $slot->save();

            return $this->success([
                'instance_id' => $safeInstanceId,
                'slot_index' => $slotIndex,
                'gem_item_id' => $safeGemItemId,
            ]);
        });
    }

    private function mapResolverFailure(array $resolverResult, int $slotIndex, string $gemItemId): string
    {
        return match ((string) ($resolverResult['reason'] ?? 'socket_not_allowed')) {
            'slot_not_found' => sprintf('slot %d was not found', $slotIndex),
            'slot_not_unlocked' => sprintf('slot %d is not unlocked', $slotIndex),
            'gem_slot_group_mismatch' => sprintf('gem item %s does not match slot %d type', $gemItemId, $slotIndex),
            'gem_item_missing' => 'gem item is missing',
            'gem_config_missing' => sprintf('gem item %s was not found', $gemItemId),
            default => (string) ($resolverResult['reason'] ?? 'socket_not_allowed'),
        };
    }

    private function instanceResolverPayload(PlayerEquipmentInstance $instance): array
    {
        return [
            'instance_id' => (string) $instance->instance_id,
            'item_id' => (string) $instance->item_id,
            'equipment_source_type' => (string) $instance->equipment_source_type,
            'slot_type' => (string) $instance->slot_type,
            'set_level' => $instance->set_level !== null ? (int) $instance->set_level : null,
            'star' => (int) $instance->star,
            'max_star' => (int) $instance->max_star,
        ];
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
