<?php

namespace App\Services\Game\Battle;

use App\Models\BattleResult;
use App\Models\Item;
use App\Models\MainStageChapter;
use App\Models\MainStageDifficulty;
use App\Models\Monster;
use App\Models\MonsterDropItem;
use App\Models\PlayerMainStageFirstClearClaim;
use App\Models\ShopPlayerProfile;
use App\Support\ShopGoodsSupport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RewardResolutionService
{
    public function execute(string|int $playerId, BattleResult|array $battleResultRecord, array $battleContext): array
    {
        $safePlayerId = trim((string) $playerId);
        $record = $battleResultRecord instanceof BattleResult
            ? $battleResultRecord
            : BattleResult::query()->find($battleResultRecord['id'] ?? 0);

        if ($safePlayerId === '' || ! $record instanceof BattleResult) {
            return $this->failure('battle result record not found');
        }

        try {
            return DB::transaction(function () use ($safePlayerId, $record, $battleContext): array {
                $profile = ShopPlayerProfile::query()->lockForUpdate()->firstOrCreate(
                    ['player_id' => $safePlayerId],
                    [
                        'level' => 1,
                        'exp' => 0,
                        'gold' => 0,
                        'crystal' => 0,
                        'contribution' => 0,
                        'free_attr_points' => 0,
                        'skill_points' => 0,
                        'inventory' => [],
                        'equipment' => [],
                        'claimed_milestones' => [],
                    ],
                );

                $rewardItems = [];
                $debugRewardSources = [];
                $firstClearGranted = false;
                $nextUnlocks = [];
                $firstClearClaim = null;

                if ((string) $record->battle_result === 'victory') {
                    $firstClearResult = $this->resolveMainStageFirstClear($safePlayerId, $record, $battleContext);
                    $rewardItems = $this->mergeRewardItems($rewardItems, $firstClearResult['reward_items']);
                    $debugRewardSources = array_values(array_merge($debugRewardSources, $firstClearResult['debug_reward_sources']));
                    $firstClearGranted = $firstClearResult['first_clear_granted'];
                    $nextUnlocks = $firstClearResult['next_unlocks'];
                    $firstClearClaim = $firstClearResult['first_clear_claim'];

                    $bossDropResult = $this->resolveBossDrops($record, $battleContext);
                    $rewardItems = $this->mergeRewardItems($rewardItems, $bossDropResult['reward_items']);
                    $debugRewardSources = array_values(array_merge($debugRewardSources, $bossDropResult['debug_reward_sources']));
                }

                $this->grantResolvedRewards($profile, $rewardItems);

                if (is_array($firstClearClaim)) {
                    PlayerMainStageFirstClearClaim::query()->create($firstClearClaim);
                }

                $this->refreshHighestClear($profile, $record);
                $profile->save();

                return $this->success([
                    'reward_items' => array_values($rewardItems),
                    'first_clear_granted' => $firstClearGranted,
                    'next_unlocks' => array_values($nextUnlocks),
                    'debug_reward_sources' => array_values($debugRewardSources),
                ]);
            });
        } catch (RuntimeException $exception) {
            return $this->failure($exception->getMessage());
        }
    }

    private function resolveMainStageFirstClear(string $playerId, BattleResult $record, array $battleContext): array
    {
        if ((string) $record->battle_type !== 'main_stage') {
            return $this->emptyRewardResult();
        }

        $difficultyId = trim((string) ($record->difficulty_id ?? $battleContext['difficulty_id'] ?? ''));
        $stageId = trim((string) ($record->stage_id ?? $battleContext['stage_id'] ?? ''));

        if ($difficultyId === '' || $stageId === '') {
            return $this->emptyRewardResult();
        }

        $alreadyClaimed = PlayerMainStageFirstClearClaim::query()
            ->where('player_id', $playerId)
            ->where('difficulty_id', $difficultyId)
            ->exists();

        if ($alreadyClaimed) {
            return $this->emptyRewardResult();
        }

        $difficulty = MainStageDifficulty::query()
            ->with(['firstClearRewards' => fn ($query) => $query->where('is_enabled', true)->orderBy('sort_order')])
            ->where('difficulty_id', $difficultyId)
            ->where('is_enabled', true)
            ->first();

        if (! $difficulty instanceof MainStageDifficulty) {
            return $this->emptyRewardResult();
        }

        $rewardItems = [];
        foreach ($difficulty->firstClearRewards as $reward) {
            $rewardItems[] = [
                'item_id' => (string) $reward->item_id,
                'count' => max(1, (int) $reward->count),
            ];
        }

        if ($rewardItems === []) {
            return $this->emptyRewardResult();
        }

        return [
            'reward_items' => $rewardItems,
            'first_clear_granted' => true,
            'next_unlocks' => $this->resolveNextUnlocks($stageId),
            'first_clear_claim' => [
                'player_id' => $playerId,
                'stage_id' => $stageId,
                'difficulty_id' => $difficultyId,
                'battle_result_id' => $record->id,
                'claimed_at' => Carbon::now(),
            ],
            'debug_reward_sources' => [[
                'type' => 'main_stage_first_clear',
                'source' => sprintf('%s_%s_first_clear', $stageId, $difficultyId),
            ]],
        ];
    }

    private function resolveBossDrops(BattleResult $record, array $battleContext): array
    {
        $bossMonsterIds = $this->resolveDefeatedBossMonsterIds($battleContext);
        if ($bossMonsterIds === []) {
            return $this->emptyRewardResult();
        }

        $bosses = Monster::query()
            ->with(['drops' => fn ($query) => $query->where('is_enabled', true)->orderBy('sort_order')])
            ->whereIn('monster_id', $bossMonsterIds)
            ->where('monster_type', 'boss')
            ->get();

        $rewardItems = [];
        $debugRewardSources = [];

        foreach ($bosses as $boss) {
            $resolvedDrops = $boss->drops
                ->filter(fn (MonsterDropItem $drop): bool => $this->isDeterministicBossDrop($drop))
                ->map(fn (MonsterDropItem $drop): array => [
                    'item_id' => (string) $drop->item_id,
                    'count' => max(1, (int) $drop->count_min),
                ])
                ->all();

            $rewardItems = $this->mergeRewardItems($rewardItems, $resolvedDrops);

            if ($resolvedDrops !== []) {
                $debugRewardSources[] = [
                    'type' => 'monster_drop_config',
                    'source' => (string) $boss->monster_id,
                ];
            }
        }

        return [
            'reward_items' => array_values($rewardItems),
            'first_clear_granted' => false,
            'next_unlocks' => [],
            'first_clear_claim' => null,
            'debug_reward_sources' => array_values($debugRewardSources),
        ];
    }

    private function resolveDefeatedBossMonsterIds(array $battleContext): array
    {
        $ids = [];

        foreach (is_array($battleContext['defeated_boss_monster_ids'] ?? null) ? $battleContext['defeated_boss_monster_ids'] : [] as $monsterId) {
            $safeMonsterId = trim((string) $monsterId);
            if ($safeMonsterId !== '') {
                $ids[] = $safeMonsterId;
            }
        }

        foreach (is_array($battleContext['defeated_monsters'] ?? null) ? $battleContext['defeated_monsters'] : [] as $monster) {
            if (! is_array($monster)) {
                continue;
            }

            $monsterType = trim((string) ($monster['monster_type'] ?? $monster['kind'] ?? ''));
            $monsterId = trim((string) ($monster['monster_id'] ?? ''));
            $defeated = (bool) ($monster['defeated'] ?? true);

            if ($defeated && $monsterType === 'boss' && $monsterId !== '') {
                $ids[] = $monsterId;
            }
        }

        return array_values(array_unique($ids));
    }

    private function isDeterministicBossDrop(MonsterDropItem $drop): bool
    {
        $dropType = (string) $drop->drop_type;
        $dropRate = (float) $drop->drop_rate;

        return in_array($dropType, ['fixed', 'guarantee'], true) || $dropRate >= 1.0;
    }

    private function resolveNextUnlocks(string $stageId): array
    {
        return MainStageChapter::query()
            ->where('unlock_prev_chapter_id', $stageId)
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->get(['chapter_id'])
            ->map(fn (MainStageChapter $chapter): array => [
                'type' => 'main_stage',
                'id' => (string) $chapter->chapter_id,
            ])
            ->all();
    }

    private function mergeRewardItems(array $baseItems, array $incomingItems): array
    {
        $merged = [];

        foreach (array_merge($baseItems, $incomingItems) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $itemId = trim((string) ($row['item_id'] ?? ''));
            $count = max(0, (int) ($row['count'] ?? 0));
            if ($itemId === '' || $count <= 0) {
                continue;
            }

            $merged[$itemId] = [
                'item_id' => $itemId,
                'count' => max(0, (int) ($merged[$itemId]['count'] ?? 0)) + $count,
            ];
        }

        ksort($merged);

        return array_values($merged);
    }

    private function grantResolvedRewards(ShopPlayerProfile $profile, array $rewardItems): void
    {
        foreach ($rewardItems as $rewardItem) {
            $itemId = trim((string) ($rewardItem['item_id'] ?? ''));
            $count = max(0, (int) ($rewardItem['count'] ?? 0));

            if ($itemId === '' || $count <= 0) {
                continue;
            }

            $item = Item::query()->where('item_id', $itemId)->where('is_enabled', true)->first();
            if (! $item instanceof Item) {
                throw new RuntimeException(sprintf('reward item not found: %s', $itemId));
            }

            $currencyField = ShopGoodsSupport::profileCurrencyField((string) $item->item_id);
            if ((string) $item->main_type === 'currency' && $currencyField !== null) {
                match ($currencyField) {
                    'gold' => $profile->gold = max(0, (int) $profile->gold + $count),
                    'crystal' => $profile->crystal = max(0, (int) $profile->crystal + $count),
                    'contribution' => $profile->contribution = max(0, (int) $profile->contribution + $count),
                    default => null,
                };

                continue;
            }

            $inventory = is_array($profile->inventory) ? $profile->inventory : [];
            $inventory[$itemId] = max(0, (int) ($inventory[$itemId] ?? 0)) + $count;
            ksort($inventory);
            $profile->inventory = $inventory;
        }
    }

    private function refreshHighestClear(ShopPlayerProfile $profile, BattleResult $record): void
    {
        if ((string) $record->battle_result !== 'victory' || (string) $record->battle_type !== 'main_stage') {
            return;
        }

        $stageId = trim((string) ($record->stage_id ?? ''));
        $difficultyId = trim((string) ($record->difficulty_id ?? ''));

        if ($stageId === '') {
            return;
        }

        $profile->highest_cleared_stage_id = $stageId;
        $profile->highest_cleared_difficulty = $this->resolveDifficultySortOrder($difficultyId);
    }

    private function resolveDifficultySortOrder(string $difficultyId): int
    {
        if ($difficultyId === '') {
            return 0;
        }

        return (int) (MainStageDifficulty::query()
            ->where('difficulty_id', $difficultyId)
            ->value('sort_order') ?? 0);
    }

    private function emptyRewardResult(): array
    {
        return [
            'reward_items' => [],
            'first_clear_granted' => false,
            'next_unlocks' => [],
            'first_clear_claim' => null,
            'debug_reward_sources' => [],
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
