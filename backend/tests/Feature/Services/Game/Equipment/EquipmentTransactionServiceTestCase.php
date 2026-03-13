<?php

namespace Tests\Feature\Services\Game\Equipment;

use App\Models\EquipmentSetCraftRecipe;
use App\Models\EquipmentStarUpgradeCost;
use App\Models\Item;
use App\Models\PlayerEquipmentGemSlot;
use App\Models\PlayerEquipmentInstance;
use App\Models\PlayerEquipmentLoadout;
use App\Models\ShopPlayerProfile;
use Database\Seeders\EquipmentSetsSeeder;
use Database\Seeders\EquipmentStarModuleSeeder;
use Database\Seeders\GemsSeeder;
use Database\Seeders\ItemsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class EquipmentTransactionServiceTestCase extends TestCase
{
    use RefreshDatabase;

    protected function seedEquipmentTransactionDependencies(bool $withGems = true): void
    {
        $this->seed([ItemsSeeder::class]);
        $this->seedMissingStarItems();
        $this->seed([
            EquipmentStarModuleSeeder::class,
            EquipmentSetsSeeder::class,
        ]);

        if ($withGems) {
            $this->seed([GemsSeeder::class]);
        }
    }

    protected function createProfile(string|int $playerId, array $inventory = [], int $gold = 0, int $crystal = 0, int $contribution = 0): ShopPlayerProfile
    {
        return ShopPlayerProfile::query()->create([
            'player_id' => (string) $playerId,
            'level' => 60,
            'exp' => 0,
            'gold' => $gold,
            'crystal' => $crystal,
            'contribution' => $contribution,
            'free_attr_points' => 0,
            'skill_points' => 0,
            'inventory' => $inventory,
            'equipment' => [],
            'claimed_milestones' => [],
        ]);
    }

    protected function createInstance(
        int $playerId,
        string $instanceId,
        string $itemId,
        string $equipmentSourceType,
        string $slotType,
        ?string $setId = null,
        ?int $setLevel = null,
        int $star = 0,
        int $maxStar = 0,
        bool $isEquipped = false,
    ): PlayerEquipmentInstance {
        $item = Item::query()->where('item_id', $itemId)->firstOrFail();

        return PlayerEquipmentInstance::query()->create([
            'player_id' => $playerId,
            'instance_id' => $instanceId,
            'item_id' => $itemId,
            'equipment_source_type' => $equipmentSourceType,
            'slot_type' => $slotType,
            'set_id' => $setId,
            'set_level' => $setLevel,
            'star' => $star,
            'max_star' => $maxStar,
            'quality' => (string) $item->quality,
            'rarity' => (string) $item->rarity,
            'is_locked' => false,
            'is_equipped' => $isEquipped,
            'obtained_at' => now(),
        ]);
    }

    protected function createLoadout(int $playerId, string $slotType, string $instanceId): PlayerEquipmentLoadout
    {
        return PlayerEquipmentLoadout::query()->create([
            'player_id' => $playerId,
            'slot_type' => $slotType,
            'instance_id' => $instanceId,
        ]);
    }

    protected function createGemSlots(string $instanceId, array $unlockedSlotIndexes = [], array $gemMap = []): void
    {
        foreach (PlayerEquipmentGemSlot::SLOT_RULE_MAP as $slotIndex => $rule) {
            PlayerEquipmentGemSlot::query()->create([
                'instance_id' => $instanceId,
                'slot_index' => $slotIndex,
                'slot_group' => $rule['slot_group'],
                'required_star' => $rule['required_star'],
                'is_unlocked' => in_array($slotIndex, $unlockedSlotIndexes, true),
                'gem_item_id' => $gemMap[$slotIndex] ?? null,
            ]);
        }
    }

    protected function starUpgradeCostsFor(int $setLevel, int $fromStar, int $toStar): array
    {
        return EquipmentStarUpgradeCost::query()
            ->where('set_level', $setLevel)
            ->where('from_star', $fromStar)
            ->where('to_star', $toStar)
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (EquipmentStarUpgradeCost $cost): array => [
                'item_id' => (string) $cost->item_id,
                'count' => (int) $cost->count,
            ])
            ->all();
    }

    protected function profileStateForCosts(array $costs, int $multiplier = 1): array
    {
        $inventory = [];
        $gold = 0;
        $crystal = 0;
        $contribution = 0;

        foreach ($costs as $cost) {
            $itemId = (string) $cost['item_id'];
            $count = max(0, (int) $cost['count']) * max(1, $multiplier);

            match ($itemId) {
                'cur_gold' => $gold += $count,
                'cur_premium_jade' => $crystal += $count,
                'cur_sect_contribution' => $contribution += $count,
                default => $inventory[$itemId] = max(0, (int) ($inventory[$itemId] ?? 0)) + $count,
            };
        }

        ksort($inventory);

        return [
            'inventory' => $inventory,
            'gold' => $gold,
            'crystal' => $crystal,
            'contribution' => $contribution,
        ];
    }

    protected function progressionRecipeFor(string $requiredBaseItemId, string $slotType): EquipmentSetCraftRecipe
    {
        return EquipmentSetCraftRecipe::query()
            ->with(['equipmentSet', 'costItems'])
            ->where('required_base_item_id', $requiredBaseItemId)
            ->where('slot_type', $slotType)
            ->where('is_enabled', true)
            ->firstOrFail();
    }

    protected function progressionRequiredItems(EquipmentSetCraftRecipe $recipe): array
    {
        $requiredItems = [];

        if ($recipe->required_blueprint_item_id !== null && $recipe->required_blueprint_item_id !== '') {
            $requiredItems[] = [
                'item_id' => (string) $recipe->required_blueprint_item_id,
                'count' => 1,
            ];
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

    private function seedMissingStarItems(): void
    {
        $template = Item::query()->where('item_id', 'mat_talisman_paper_white')->firstOrFail();

        foreach ([
            'mat_star_sand_fragment_low',
            'mat_star_sand_low',
            'mat_star_sand_fragment_mid',
            'mat_star_sand_mid',
            'mat_star_sand_high_fragment',
            'mat_star_sand_high',
            'mat_star_core_extreme',
        ] as $itemId) {
            if (Item::query()->where('item_id', $itemId)->exists()) {
                continue;
            }

            $row = $template->getAttributes();
            $row['id'] = $itemId;
            $row['item_id'] = $itemId;
            $row['item_name'] = $itemId;
            $row['display_name'] = $itemId;
            $row['name'] = $itemId;
            $row['main_type'] = 'material';
            $row['sub_type'] = 'star_material';
            $row['type'] = 'material';
            $row['material_type'] = 'star_material';
            $row['desc'] = 'test seeded star material';
            $row['updated_at'] = now();
            $row['created_at'] = now();

            Item::query()->create($row);
        }
    }
}
