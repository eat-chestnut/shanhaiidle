<?php

namespace App\Services\Game\Equipment;

use App\Models\EquipmentSetCraftRecipe;
use App\Models\EquipmentStageProgressionRule;
use App\Models\Item;
use App\Models\PlayerEquipmentInstance;
use App\Models\ShopPlayerProfile;
use App\Support\PlayerEquipmentInstanceModuleSupport;
use Illuminate\Support\Facades\DB;

class EquipmentStageProgressionService
{
    public function __construct(
        private readonly EquipmentStageProgressionResolver $progressionResolver,
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

            $progressionRules = EquipmentStageProgressionRule::query()
                ->where('is_enabled', true)
                ->orderBy('from_set_level')
                ->lockForUpdate()
                ->get([
                    'from_set_level',
                    'to_set_level',
                    'required_max_star',
                    'star_keep_mode',
                ]);

            $progressionRuleMap = $progressionRules
                ->keyBy(fn (EquipmentStageProgressionRule $rule): int => (int) $rule->to_set_level);

            $recipes = EquipmentSetCraftRecipe::query()
                ->with(['equipmentSet:id,set_id,set_level', 'costItems'])
                ->where('slot_type', (string) $instance->slot_type)
                ->where('required_base_item_id', (string) $instance->item_id)
                ->where('is_enabled', true)
                ->lockForUpdate()
                ->get();

            $recipeMap = $recipes
                ->map(function (EquipmentSetCraftRecipe $recipe) use ($progressionRuleMap): ?array {
                    $toSetLevel = (int) ($recipe->equipmentSet?->set_level ?? 0);
                    /** @var EquipmentStageProgressionRule|null $rule */
                    $rule = $progressionRuleMap->get($toSetLevel);
                    if (! $rule instanceof EquipmentStageProgressionRule) {
                        return null;
                    }

                    return [
                        'from_set_level' => (int) $rule->from_set_level,
                        'to_set_level' => $toSetLevel,
                        'slot_type' => (string) $recipe->slot_type,
                        'result_item_id' => (string) $recipe->result_item_id,
                    ];
                })
                ->filter()
                ->values()
                ->all();

            $resolverResult = $this->progressionResolver->resolve(
                $this->instanceResolverPayload($instance),
                $progressionRules->map(fn (EquipmentStageProgressionRule $rule): array => [
                    'from_set_level' => (int) $rule->from_set_level,
                    'to_set_level' => (int) $rule->to_set_level,
                    'required_max_star' => (int) $rule->required_max_star,
                    'star_keep_mode' => (string) $rule->star_keep_mode,
                ])->all(),
                $recipeMap,
            );

            if (! ($resolverResult['ok'] ?? false)) {
                return $this->failure($this->mapResolverFailure($resolverResult, $instance));
            }

            $nextState = $resolverResult['data']['next_state'] ?? null;
            if (! is_array($nextState)) {
                return $this->failure('next progression state is missing');
            }

            $nextItemId = (string) ($nextState['item_id'] ?? '');
            $nextSetLevel = (int) ($nextState['set_level'] ?? 0);

            /** @var EquipmentSetCraftRecipe|null $recipe */
            $recipe = $recipes->first(
                fn (EquipmentSetCraftRecipe $row): bool => (string) $row->result_item_id === $nextItemId
                    && (int) ($row->equipmentSet?->set_level ?? 0) === $nextSetLevel
            );

            if (! $recipe instanceof EquipmentSetCraftRecipe) {
                return $this->failure('progression recipe not found');
            }

            /** @var ShopPlayerProfile|null $profile */
            $profile = ShopPlayerProfile::query()
                ->where('player_id', $safeProfilePlayerId)
                ->lockForUpdate()
                ->first();

            if (! $profile instanceof ShopPlayerProfile) {
                return $this->failure('player inventory profile not found');
            }

            $requiredItems = $this->buildRequiredItems($recipe);
            $materialValidation = $this->validateRequiredItems($profile, $requiredItems);
            if ($materialValidation !== null) {
                return $this->failure($materialValidation);
            }

            foreach ($requiredItems as $requiredItem) {
                $this->spendOwnedItem($profile, (string) $requiredItem['item_id'], (int) $requiredItem['count']);
            }

            $profile->save();

            $updatePayload = PlayerEquipmentInstanceModuleSupport::progressionUpdatePayloadOrFail(
                $this->instanceResolverPayload($instance),
                $nextItemId,
                $nextSetLevel,
            );

            /** @var Item $nextItem */
            $nextItem = Item::query()->where('item_id', $nextItemId)->firstOrFail();

            $oldItemId = (string) $instance->item_id;
            $oldSetLevel = (int) $instance->set_level;

            $instance->item_id = $updatePayload['item_id'];
            $instance->set_level = $updatePayload['set_level'];
            $instance->max_star = $updatePayload['max_star'];
            $instance->star = $updatePayload['star'];
            $instance->set_id = (string) $recipe->set_id;
            $instance->quality = (string) $nextItem->quality;
            $instance->rarity = (string) $nextItem->rarity;
            $instance->save();

            return $this->success([
                'instance_id' => $safeInstanceId,
                'old_item_id' => $oldItemId,
                'new_item_id' => (string) $instance->item_id,
                'old_set_level' => $oldSetLevel,
                'new_set_level' => (int) $instance->set_level,
                'star' => (int) $instance->star,
                'max_star' => (int) $instance->max_star,
            ]);
        });
    }

    private function buildRequiredItems(EquipmentSetCraftRecipe $recipe): array
    {
        $requiredItems = [];
        $blueprintItemId = trim((string) ($recipe->required_blueprint_item_id ?? ''));
        if ($blueprintItemId !== '') {
            $requiredItems[] = ['item_id' => $blueprintItemId, 'count' => 1];
        }

        foreach ($recipe->costItems->sortBy('sort_order') as $costItem) {
            if (! (bool) $costItem->is_enabled) {
                continue;
            }

            $requiredItems[] = [
                'item_id' => (string) $costItem->item_id,
                'count' => (int) $costItem->count,
            ];
        }

        return $requiredItems;
    }

    private function validateRequiredItems(ShopPlayerProfile $profile, array $requiredItems): ?string
    {
        foreach ($requiredItems as $requiredItem) {
            $itemId = (string) $requiredItem['item_id'];
            $requiredCount = max(0, (int) $requiredItem['count']);
            $owned = $this->ownedCount($profile, $itemId);

            if ($owned < $requiredCount) {
                return sprintf('material %s is insufficient: need %d, owned %d', $itemId, $requiredCount, $owned);
            }
        }

        return null;
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

    private function mapResolverFailure(array $resolverResult, PlayerEquipmentInstance $instance): string
    {
        return match ((string) ($resolverResult['reason'] ?? 'progression_not_allowed')) {
            'current_star_below_required_max_star' => sprintf(
                'current star is %d, required max star is %d',
                (int) $instance->star,
                (int) $instance->max_star,
            ),
            'set_equipment_only' => 'stage progression supports set equipment only',
            'progression_result_item_missing' => 'next result item id is missing',
            'progression_recipe_not_found' => 'progression recipe not found',
            'progression_rule_not_found' => 'progression rule not found',
            default => (string) ($resolverResult['reason'] ?? 'progression_not_allowed'),
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
