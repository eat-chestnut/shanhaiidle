<?php

namespace App\Services\Game\Battle;

use App\Services\Game\Equipment\PlayerEquipmentStatAggregator;
use App\Services\PlayerEquipmentLoadoutStatsService;

class PlayerBattleSnapshotBuilder
{
    public function __construct(
        private readonly PlayerEquipmentStatAggregator $playerEquipmentStatAggregator = new PlayerEquipmentStatAggregator(),
    ) {
    }

    public function build(int|string $playerId): array
    {
        $aggregateResult = $this->playerEquipmentStatAggregator->aggregate($playerId);
        if (! ($aggregateResult['ok'] ?? false)) {
            return $this->failure((string) ($aggregateResult['reason'] ?? 'player_equipment_stat_aggregation_failed'));
        }

        $aggregateData = is_array($aggregateResult['data'] ?? null) ? $aggregateResult['data'] : [];

        return $this->success([
            'player_id' => is_numeric((string) $playerId) ? (int) $playerId : (string) $playerId,
            'base_stats' => is_array($aggregateData['base_stats'] ?? null) ? $aggregateData['base_stats'] : [],
            'bonus_stats' => is_array($aggregateData['bonus_stats'] ?? null) ? $aggregateData['bonus_stats'] : [],
            'special_effects' => is_array($aggregateData['special_effects'] ?? null) ? array_values($aggregateData['special_effects']) : [],
            'equipment_summary' => [
                'set_counts' => $this->buildSetCounts($playerId),
                'talisman_star_links' => $this->extractTalismanStarLinks($aggregateData['sources'] ?? []),
                'equipped_boss_core_ids' => [],
            ],
            'combat_tags' => $this->extractCombatTags($aggregateData['special_effects'] ?? []),
        ]);
    }

    /**
     * @return array<int, array{set_id:string,equipped_count:int}>
     */
    private function buildSetCounts(int|string $playerId): array
    {
        $counts = PlayerEquipmentLoadoutStatsService::equippedSetPieceCounts((int) $playerId);
        ksort($counts);

        $rows = [];
        foreach ($counts as $setId => $equippedCount) {
            $safeSetId = trim((string) $setId);
            if ($safeSetId === '') {
                continue;
            }

            $rows[] = [
                'set_id' => $safeSetId,
                'equipped_count' => (int) $equippedCount,
            ];
        }

        return $rows;
    }

    /**
     * @param  mixed  $sources
     * @return array<int, int>
     */
    private function extractTalismanStarLinks(mixed $sources): array
    {
        $links = [];

        foreach (is_array($sources) ? $sources : [] as $sourceRow) {
            if (! is_array($sourceRow)) {
                continue;
            }

            if (trim((string) ($sourceRow['type'] ?? '')) !== 'talisman_star_link') {
                continue;
            }

            $source = trim((string) ($sourceRow['source'] ?? ''));
            if (! preg_match('/(\d+)$/', $source, $matches)) {
                continue;
            }

            $links[(int) $matches[1]] = (int) $matches[1];
        }

        sort($links);

        return array_values($links);
    }

    /**
     * @param  mixed  $specialEffects
     * @return array<int, string>
     */
    private function extractCombatTags(mixed $specialEffects): array
    {
        $tags = [];

        foreach (is_array($specialEffects) ? $specialEffects : [] as $effect) {
            if (! is_array($effect)) {
                continue;
            }

            $effectKey = trim((string) ($effect['effect_key'] ?? ''));
            if ($effectKey === '' || ! str_ends_with($effectKey, '_tag')) {
                continue;
            }

            $tags[$effectKey] = $effectKey;
        }

        return array_values($tags);
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
