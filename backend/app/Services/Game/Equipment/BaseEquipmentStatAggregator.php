<?php

namespace App\Services\Game\Equipment;

class BaseEquipmentStatAggregator
{
    /**
     * @param  array<int, array<string, mixed>>  $loadouts
     * @param  array<int, array<string, mixed>>  $equipmentInstances
     * @param  array<int, array<string, mixed>>  $equipmentConfigs
     * @param  array<int, array<string, mixed>>  $blueTemplates
     */
    public function aggregate(
        int|string $playerId,
        array $loadouts,
        array $equipmentInstances,
        array $equipmentConfigs,
        array $blueTemplates
    ): array {
        unset($playerId);

        $instancesById = $this->indexRowsByKey($equipmentInstances, 'instance_id');
        $equipmentConfigsByItemId = $this->indexBaseStatConfigsByItemId($equipmentConfigs, 'item_id');
        $blueTemplatesByItemId = $this->indexBaseStatConfigsByItemId($blueTemplates, 'result_item_id');

        $baseStats = [];

        foreach ($loadouts as $loadout) {
            if (! is_array($loadout)) {
                continue;
            }

            $instanceId = trim((string) ($loadout['instance_id'] ?? ''));
            if ($instanceId === '' || ! isset($instancesById[$instanceId])) {
                continue;
            }

            $instance = $instancesById[$instanceId];
            $itemId = trim((string) ($instance['item_id'] ?? ''));
            if ($itemId === '') {
                continue;
            }

            $config = $this->resolveBaseStatConfigForInstance(
                $instance,
                $equipmentConfigsByItemId[$itemId] ?? null,
                $blueTemplatesByItemId[$itemId] ?? null
            );

            if ($config === null) {
                continue;
            }

            foreach ($this->extractBaseStats($config) as $baseStat) {
                $statKey = $this->normalizeStatKey((string) ($baseStat['stat_key'] ?? ''));
                if ($statKey === '') {
                    continue;
                }

                $baseStats[$statKey] = $this->normalizeNumeric($baseStats[$statKey] ?? 0)
                    + $this->normalizeNumeric($baseStat['value'] ?? 0);
            }
        }

        ksort($baseStats);

        return $this->success($baseStats);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function indexRowsByKey(array $rows, string $key): array
    {
        $indexed = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rowKey = trim((string) ($row[$key] ?? ''));
            if ($rowKey === '') {
                continue;
            }

            $indexed[$rowKey] = $row;
        }

        return $indexed;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function indexBaseStatConfigsByItemId(array $rows, string $preferredItemKey): array
    {
        $indexed = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $itemId = trim((string) ($row[$preferredItemKey] ?? $row['item_id'] ?? ''));
            if ($itemId === '') {
                continue;
            }

            $indexed[$itemId] = $row;
        }

        return $indexed;
    }

    /**
     * @param  array<string, mixed>  $instance
     * @param  array<string, mixed>|null  $equipmentConfig
     * @param  array<string, mixed>|null  $blueTemplate
     * @return array<string, mixed>|null
     */
    private function resolveBaseStatConfigForInstance(
        array $instance,
        ?array $equipmentConfig,
        ?array $blueTemplate
    ): ?array {
        $sourceType = trim((string) ($instance['equipment_source_type'] ?? ''));

        if ($sourceType === 'blue_equipment' && $blueTemplate !== null) {
            return $blueTemplate;
        }

        return $equipmentConfig ?? $blueTemplate;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, array<string, mixed>>
     */
    private function extractBaseStats(array $config): array
    {
        $baseStats = $config['base_stats'] ?? null;
        if (is_array($baseStats)) {
            return array_values(array_filter($baseStats, 'is_array'));
        }

        $whiteStats = $config['white_stats'] ?? null;
        if (! is_array($whiteStats)) {
            return [];
        }

        $normalized = [];
        foreach ($whiteStats as $whiteStat) {
            if (! is_array($whiteStat)) {
                continue;
            }

            $normalized[] = [
                'stat_key' => $whiteStat['stat_key'] ?? $whiteStat['stat'] ?? '',
                'value_type' => $whiteStat['value_type'] ?? 'flat',
                'value' => $whiteStat['value'] ?? 0,
            ];
        }

        return $normalized;
    }

    private function normalizeStatKey(string $statKey): string
    {
        $normalized = strtoupper(trim($statKey));

        return match ($normalized) {
            'ATK' => 'MELEE_ATK',
            default => $normalized,
        };
    }

    private function normalizeNumeric(mixed $value): int|float
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (! is_numeric($value)) {
            return 0;
        }

        $numeric = (float) $value;

        return floor($numeric) === $numeric ? (int) $numeric : $numeric;
    }

    private function success(array $data): array
    {
        return [
            'ok' => true,
            'reason' => null,
            'data' => $data,
        ];
    }
}
