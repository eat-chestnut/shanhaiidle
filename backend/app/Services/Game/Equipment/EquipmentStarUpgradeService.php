<?php

namespace App\Services\Game\Equipment;

use App\Models\EquipmentStarSlotUnlock;
use App\Models\EquipmentStarUpgradeCost;
use App\Models\Item;
use App\Models\PlayerEquipmentGemSlot;
use App\Models\PlayerEquipmentInstance;
use App\Models\ShopPlayerProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EquipmentStarUpgradeService
{
    public function __construct(
        private readonly EquipmentGemSlotResolver $gemSlotResolver,
    ) {
    }

    public function execute(int|string $playerId, string $instanceId): array
    {
        $safePlayerId = (int) $playerId;
        $safeProfilePlayerId = trim((string) $playerId);
        $safeInstanceId = trim($instanceId);

        if ($safePlayerId < 1 || $safeInstanceId === '') {
            return $this->failure('invalid input');
        }

        return DB::transaction(function () use ($safePlayerId, $safeProfilePlayerId, $safeInstanceId): array {
            /** @var PlayerEquipmentInstance|null $instance */
            $instance = PlayerEquipmentInstance::query()
                ->where('player_id', $safePlayerId)
                ->where('instance_id', $safeInstanceId)
                ->lockForUpdate()
                ->first();

            if (! $instance instanceof PlayerEquipmentInstance) {
                return $this->failure('equipment instance does not belong to player');
            }

            if ((string) $instance->equipment_source_type !== 'set_equipment') {
                return $this->failure('star upgrade supports set equipment only');
            }

            $oldStar = (int) $instance->star;
            $maxStar = (int) $instance->max_star;

            if ($oldStar >= $maxStar) {
                return $this->failure(sprintf('current star %d already reached max star %d', $oldStar, $maxStar));
            }

            $newStar = $oldStar + 1;

            $costRows = EquipmentStarUpgradeCost::query()
                ->where('set_level', (int) $instance->set_level)
                ->where('from_star', $oldStar)
                ->where('to_star', $newStar)
                ->where('is_enabled', true)
                ->orderBy('sort_order')
                ->lockForUpdate()
                ->get();

            if ($costRows->isEmpty()) {
                return $this->failure(sprintf(
                    'star upgrade cost config is missing for set level %d from %d to %d',
                    (int) $instance->set_level,
                    $oldStar,
                    $newStar,
                ));
            }

            /** @var ShopPlayerProfile|null $profile */
            $profile = ShopPlayerProfile::query()
                ->where('player_id', $safeProfilePlayerId)
                ->lockForUpdate()
                ->first();

            if (! $profile instanceof ShopPlayerProfile) {
                return $this->failure('player inventory profile not found');
            }

            $slotUnlockRules = EquipmentStarSlotUnlock::query()
                ->where('is_enabled', true)
                ->orderBy('required_star')
                ->lockForUpdate()
                ->get(['slot_index', 'slot_group', 'required_star'])
                ->map(fn (EquipmentStarSlotUnlock $row): array => [
                    'slot_index' => (int) $row->slot_index,
                    'slot_group' => (string) $row->slot_group,
                    'required_star' => (int) $row->required_star,
                ])
                ->all();

            $resolverResult = $this->gemSlotResolver->resolveUnlockedSlots([
                'instance_id' => (string) $instance->instance_id,
                'star' => $newStar,
            ], $slotUnlockRules);

            if (! ($resolverResult['ok'] ?? false)) {
                return $this->failure('gem slot unlock rules are invalid');
            }

            $materialValidation = $this->validateCostRows($profile, $costRows);
            if ($materialValidation !== null) {
                return $this->failure($materialValidation);
            }

            $this->spendCostRows($profile, $costRows);
            $profile->save();

            $instance->star = $newStar;
            $instance->save();

            $unlockedSlotIndexes = collect($resolverResult['data']['unlocked_slots'] ?? [])
                ->map(fn (array $row): int => (int) ($row['slot_index'] ?? 0))
                ->filter(fn (int $slotIndex): bool => $slotIndex > 0)
                ->values();

            $existingSlots = PlayerEquipmentGemSlot::query()
                ->where('instance_id', $safeInstanceId)
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (PlayerEquipmentGemSlot $slot): int => (int) $slot->slot_index);

            $newlyUnlockedSlots = [];
            foreach ($slotUnlockRules as $rule) {
                $slotIndex = (int) $rule['slot_index'];
                $shouldUnlock = $unlockedSlotIndexes->contains($slotIndex);

                /** @var PlayerEquipmentGemSlot $slot */
                $slot = $existingSlots->get($slotIndex) ?? new PlayerEquipmentGemSlot([
                    'instance_id' => $safeInstanceId,
                    'slot_index' => $slotIndex,
                ]);

                $wasUnlocked = (bool) $slot->is_unlocked;
                $slot->slot_group = (string) $rule['slot_group'];
                $slot->required_star = (int) $rule['required_star'];
                $slot->is_unlocked = $shouldUnlock;
                $slot->save();

                if (! $wasUnlocked && $shouldUnlock) {
                    $newlyUnlockedSlots[] = $slotIndex;
                }
            }

            sort($newlyUnlockedSlots);

            return $this->success([
                'instance_id' => $safeInstanceId,
                'old_star' => $oldStar,
                'new_star' => $newStar,
                'newly_unlocked_slots' => array_values($newlyUnlockedSlots),
            ]);
        });
    }

    private function validateCostRows(ShopPlayerProfile $profile, Collection $costRows): ?string
    {
        foreach ($costRows as $costRow) {
            $itemId = (string) $costRow->item_id;
            $requiredCount = max(0, (int) $costRow->count);
            $owned = $this->ownedCount($profile, $itemId);

            if ($owned < $requiredCount) {
                return sprintf('material %s is insufficient: need %d, owned %d', $itemId, $requiredCount, $owned);
            }
        }

        return null;
    }

    private function spendCostRows(ShopPlayerProfile $profile, Collection $costRows): void
    {
        foreach ($costRows as $costRow) {
            $this->spendOwnedItem($profile, (string) $costRow->item_id, (int) $costRow->count);
        }
    }

    private function ownedCount(ShopPlayerProfile $profile, string $itemId): int
    {
        /** @var Item|null $item */
        $item = Item::query()->where('item_id', $itemId)->first();
        if (! $item instanceof Item) {
            return 0;
        }

        return match ((string) $item->item_id) {
            'cur_gold' => max(0, (int) $profile->gold),
            'cur_premium_jade' => max(0, (int) $profile->crystal),
            'cur_sect_contribution' => max(0, (int) $profile->contribution),
            default => max(0, (int) (($profile->inventory ?? [])[$itemId] ?? 0)),
        };
    }

    private function spendOwnedItem(ShopPlayerProfile $profile, string $itemId, int $count): void
    {
        $safeCount = max(0, $count);
        if ($safeCount === 0) {
            return;
        }

        match ($itemId) {
            'cur_gold' => $profile->gold = max(0, (int) $profile->gold - $safeCount),
            'cur_premium_jade' => $profile->crystal = max(0, (int) $profile->crystal - $safeCount),
            'cur_sect_contribution' => $profile->contribution = max(0, (int) $profile->contribution - $safeCount),
            default => $profile->inventory = $this->spendInventoryItem($profile, $itemId, $safeCount),
        };
    }

    private function spendInventoryItem(ShopPlayerProfile $profile, string $itemId, int $count): array
    {
        $inventory = is_array($profile->inventory) ? $profile->inventory : [];
        $remaining = max(0, (int) ($inventory[$itemId] ?? 0) - $count);

        if ($remaining === 0) {
            unset($inventory[$itemId]);
        } else {
            $inventory[$itemId] = $remaining;
        }

        ksort($inventory);

        return $inventory;
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
