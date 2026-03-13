<?php

namespace App\Services;

use App\Models\Item;
use App\Models\MainStageChapter;
use App\Models\Milestone;
use App\Models\PlayerMilestone;
use App\Models\ShopPlayerProfile;
use App\Support\ShopGoodsSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MilestonePlayerService
{
    public function syncForProfile(ShopPlayerProfile $profile): array
    {
        return $this->syncForPlayer((string) $profile->player_id);
    }

    public function syncForPlayer(string $playerId): array
    {
        $safePlayerId = trim($playerId);
        if ($safePlayerId === '') {
            return $this->emptySummary();
        }

        return DB::transaction(function () use ($safePlayerId): array {
            /** @var ShopPlayerProfile|null $profile */
            $profile = ShopPlayerProfile::query()
                ->where('player_id', $safePlayerId)
                ->lockForUpdate()
                ->first();

            if (! $profile instanceof ShopPlayerProfile) {
                return $this->emptySummary();
            }

            return $this->syncLockedProfile($profile);
        });
    }

    public function claim(string $playerId, string $milestoneId): array
    {
        $safePlayerId = trim($playerId);
        $safeMilestoneId = trim($milestoneId);

        if ($safePlayerId === '' || $safeMilestoneId === '') {
            return ['ok' => false, 'reason' => 'invalid_payload'];
        }

        return DB::transaction(function () use ($safePlayerId, $safeMilestoneId): array {
            /** @var ShopPlayerProfile|null $profile */
            $profile = ShopPlayerProfile::query()
                ->where('player_id', $safePlayerId)
                ->lockForUpdate()
                ->first();

            if (! $profile instanceof ShopPlayerProfile) {
                return ['ok' => false, 'reason' => 'player_not_found'];
            }

            /** @var Milestone|null $milestone */
            $milestone = Milestone::query()
                ->where('milestone_id', $safeMilestoneId)
                ->lockForUpdate()
                ->first();

            if (! $milestone instanceof Milestone) {
                return ['ok' => false, 'reason' => 'milestone_not_found'];
            }

            if (! (bool) $milestone->is_enabled) {
                return ['ok' => false, 'reason' => 'milestone_disabled'];
            }

            $this->syncLockedProfile($profile);

            /** @var PlayerMilestone|null $state */
            $state = PlayerMilestone::query()
                ->where('player_id', $safePlayerId)
                ->where('milestone_id', $safeMilestoneId)
                ->lockForUpdate()
                ->first();

            if (! $state instanceof PlayerMilestone || ! (bool) $state->is_unlocked) {
                return ['ok' => false, 'reason' => 'milestone_not_unlocked'];
            }

            if ((bool) $state->is_claimed) {
                return ['ok' => false, 'reason' => 'milestone_already_claimed'];
            }

            /** @var Item|null $rewardItem */
            $rewardItem = Item::query()->where('item_id', (string) $milestone->reward_item_id)->first();
            if (! $rewardItem instanceof Item || ! (bool) $rewardItem->is_enabled) {
                return ['ok' => false, 'reason' => 'reward_item_missing'];
            }

            $rewardCount = max(1, (int) $milestone->reward_count);
            $this->grantReward($profile, $rewardItem, $rewardCount);
            $profile->save();

            $state->is_unlocked = true;
            $state->is_claimed = true;
            $state->claimed_at = now();
            $state->save();

            $claimedIds = PlayerMilestone::query()
                ->where('player_id', $safePlayerId)
                ->where('is_claimed', true)
                ->orderBy('milestone_id')
                ->pluck('milestone_id')
                ->all();
            $profile->claimed_milestones = array_values($claimedIds);
            $profile->save();

            return [
                'ok' => true,
                'milestone_id' => $safeMilestoneId,
                'reward_item_id' => (string) $milestone->reward_item_id,
                'reward_count' => $rewardCount,
                'granted_items' => [[
                    'item_id' => (string) $milestone->reward_item_id,
                    'count' => $rewardCount,
                ]],
                'updated_currencies' => $this->currencySnapshot($profile),
                'milestone_state' => [
                    'is_unlocked' => true,
                    'is_claimed' => true,
                    'claimed_at' => $state->claimed_at?->toDateTimeString(),
                ],
                'summary' => $this->summaryForPlayer($safePlayerId),
                'profile' => [
                    'player_id' => (string) $profile->player_id,
                    'level' => (int) $profile->level,
                ],
            ];
        });
    }

    public function summaryForPlayer(string $playerId): array
    {
        $safePlayerId = trim($playerId);
        if ($safePlayerId === '') {
            return $this->emptySummary();
        }

        $enabledMilestoneIds = Milestone::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('milestone_id')
            ->pluck('milestone_id')
            ->all();

        if ($enabledMilestoneIds === []) {
            return $this->emptySummary();
        }

        $states = PlayerMilestone::query()
            ->where('player_id', $safePlayerId)
            ->whereIn('milestone_id', $enabledMilestoneIds)
            ->get();

        $claimedIds = $states
            ->where('is_claimed', true)
            ->sortBy('milestone_id')
            ->pluck('milestone_id')
            ->values()
            ->all();

        $unlockedCount = $states->where('is_unlocked', true)->count();
        $claimedCount = $states->where('is_claimed', true)->count();

        return [
            'milestone_total' => count($enabledMilestoneIds),
            'milestone_unlocked' => $unlockedCount,
            'milestone_claimable' => $states->where('is_unlocked', true)->where('is_claimed', false)->count(),
            'claimed_milestone_count' => $claimedCount,
            'claimed_milestones' => $claimedIds,
        ];
    }

    private function syncLockedProfile(ShopPlayerProfile $profile): array
    {
        /** @var Collection<int, Milestone> $milestones */
        $milestones = Milestone::query()
            ->orderBy('sort_order')
            ->orderBy('milestone_id')
            ->get();

        $milestoneIds = $milestones->pluck('milestone_id')->all();
        $existingStates = PlayerMilestone::query()
            ->where('player_id', (string) $profile->player_id)
            ->lockForUpdate()
            ->get()
            ->keyBy('milestone_id');

        $legacyClaimed = array_fill_keys(
            is_array($profile->claimed_milestones) ? array_values($profile->claimed_milestones) : [],
            true,
        );

        $chapterSortMap = MainStageChapter::query()
            ->where('is_enabled', true)
            ->where('has_combat', true)
            ->pluck('sort_order', 'chapter_id')
            ->map(fn (mixed $value): int => (int) $value)
            ->all();

        $milestoneById = $milestones->keyBy('milestone_id');
        $resolvedUnlocked = [];
        $resolveUnlocked = function (string $milestoneId) use (&$resolveUnlocked, &$resolvedUnlocked, $milestoneById, $profile, $chapterSortMap): bool {
            if (isset($resolvedUnlocked[$milestoneId])) {
                return $resolvedUnlocked[$milestoneId];
            }

            /** @var Milestone|null $milestone */
            $milestone = $milestoneById->get($milestoneId);
            if (! $milestone instanceof Milestone || ! (bool) $milestone->is_enabled) {
                return $resolvedUnlocked[$milestoneId] = false;
            }

            $preMilestoneId = trim((string) ($milestone->pre_milestone_id ?? ''));
            $preUnlocked = $preMilestoneId === '' ? true : $resolveUnlocked($preMilestoneId);

            return $resolvedUnlocked[$milestoneId] = $preUnlocked && $this->conditionMet($milestone, $profile, $chapterSortMap);
        };

        foreach ($milestones as $milestone) {
            $milestoneId = (string) $milestone->milestone_id;
            /** @var PlayerMilestone $state */
            $state = $existingStates->get($milestoneId) ?? new PlayerMilestone([
                'player_id' => (string) $profile->player_id,
                'milestone_id' => $milestoneId,
            ]);

            $claimedSeed = (bool) ($legacyClaimed[$milestoneId] ?? false);
            $calculatedUnlocked = $resolveUnlocked($milestoneId);

            $state->is_unlocked = (bool) $state->is_unlocked || (bool) $state->is_claimed || $claimedSeed || $calculatedUnlocked;
            $state->is_claimed = (bool) $state->is_claimed || $claimedSeed;
            if ((bool) $state->is_claimed && $state->claimed_at === null) {
                $state->claimed_at = now();
            }
            if (! (bool) $state->is_claimed) {
                $state->claimed_at = null;
            }
            $state->save();
        }

        if ($milestoneIds === []) {
            PlayerMilestone::query()->where('player_id', (string) $profile->player_id)->delete();
            $profile->claimed_milestones = [];
            $profile->save();

            return $this->emptySummary();
        }

        PlayerMilestone::query()
            ->where('player_id', (string) $profile->player_id)
            ->whereNotIn('milestone_id', $milestoneIds)
            ->delete();

        $summary = $this->summaryForPlayer((string) $profile->player_id);
        $profile->claimed_milestones = $summary['claimed_milestones'];
        $profile->save();

        return $summary;
    }

    /**
     * @param  array<string, int>  $chapterSortMap
     */
    private function conditionMet(Milestone $milestone, ShopPlayerProfile $profile, array $chapterSortMap): bool
    {
        return match ((string) $milestone->condition_type) {
            'player_level_reached' => (int) $profile->level >= max(1, (int) $milestone->condition_value),
            'chapter_cleared' => $this->chapterCleared((string) $milestone->condition_value, (string) ($profile->highest_cleared_stage_id ?? ''), $chapterSortMap),
            default => false,
        };
    }

    /**
     * @param  array<string, int>  $chapterSortMap
     */
    private function chapterCleared(string $targetChapterId, string $highestClearedChapterId, array $chapterSortMap): bool
    {
        $safeTarget = trim($targetChapterId);
        $safeHighest = trim($highestClearedChapterId);

        if ($safeTarget === '' || $safeHighest === '') {
            return false;
        }

        $targetSort = $chapterSortMap[$safeTarget] ?? null;
        $highestSort = $chapterSortMap[$safeHighest] ?? null;

        if ($targetSort === null || $highestSort === null) {
            return $safeTarget === $safeHighest;
        }

        return $highestSort >= $targetSort;
    }

    private function grantReward(ShopPlayerProfile $profile, Item $rewardItem, int $count): void
    {
        $safeCount = max(0, $count);
        if ($safeCount <= 0) {
            return;
        }

        $currencyField = ShopGoodsSupport::profileCurrencyField((string) $rewardItem->item_id);
        if ((string) $rewardItem->main_type === 'currency' && $currencyField !== null) {
            $this->addCurrency($profile, $currencyField, $safeCount);

            return;
        }

        $inventory = is_array($profile->inventory) ? $profile->inventory : [];
        $inventory[(string) $rewardItem->item_id] = max(0, (int) ($inventory[(string) $rewardItem->item_id] ?? 0)) + $safeCount;
        ksort($inventory);
        $profile->inventory = $inventory;
    }

    private function addCurrency(ShopPlayerProfile $profile, string $currencyField, int $amount): void
    {
        $safeAmount = max(0, $amount);

        match ($currencyField) {
            'gold' => $profile->gold = max(0, (int) $profile->gold + $safeAmount),
            'crystal' => $profile->crystal = max(0, (int) $profile->crystal + $safeAmount),
            'contribution' => $profile->contribution = max(0, (int) $profile->contribution + $safeAmount),
            default => null,
        };
    }

    private function currencySnapshot(ShopPlayerProfile $profile): array
    {
        return [
            'gold' => max(0, (int) $profile->gold),
            'crystal' => max(0, (int) $profile->crystal),
            'contribution' => max(0, (int) $profile->contribution),
        ];
    }

    private function emptySummary(): array
    {
        return [
            'milestone_total' => 0,
            'milestone_unlocked' => 0,
            'milestone_claimable' => 0,
            'claimed_milestone_count' => 0,
            'claimed_milestones' => [],
        ];
    }
}
