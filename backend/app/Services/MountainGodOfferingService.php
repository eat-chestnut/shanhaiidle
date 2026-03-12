<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Item;
use App\Models\Stage;
use Illuminate\Validation\ValidationException;

class MountainGodOfferingService
{
    public const SETTING_KEY = 'mountain_god_offerings';

    public static function defaultConfig(): array
    {
        return [
            'god_id' => 'nanshan_guardian',
            'name' => '南山山神',
            'unlock_stage_id' => 'nan_05',
            'description' => '通关南山终段后，可在山神殿进行轻量供奉，换取每日额外收益。',
            'offerings' => [
                [
                    'offering_id' => 'offering_rumi',
                    'name' => '稌米供',
                    'offering_item_id' => '稌米供',
                    'exchange_cost' => ['gold' => 120, 'sect_contribution' => 0],
                    'rewards' => ['gold' => 80, 'spirit_stone' => 0, 'sect_contribution' => 12, 'skill_points' => 0, 'items' => []],
                    'daily_limit' => 1,
                    'sort_order' => 10,
                    'is_enabled' => true,
                ],
                [
                    'offering_id' => 'offering_baijian',
                    'name' => '白菅席',
                    'offering_item_id' => '白菅席',
                    'exchange_cost' => ['gold' => 160, 'sect_contribution' => 10],
                    'rewards' => ['gold' => 0, 'spirit_stone' => 8, 'sect_contribution' => 16, 'skill_points' => 0, 'items' => []],
                    'daily_limit' => 1,
                    'sort_order' => 20,
                    'is_enabled' => true,
                ],
                [
                    'offering_id' => 'offering_zhangyu',
                    'name' => '璋玉符',
                    'offering_item_id' => '璋玉符',
                    'exchange_cost' => ['gold' => 220, 'sect_contribution' => 20],
                    'rewards' => ['gold' => 0, 'spirit_stone' => 10, 'sect_contribution' => 18, 'skill_points' => 1, 'items' => []],
                    'daily_limit' => 1,
                    'sort_order' => 30,
                    'is_enabled' => true,
                ],
                [
                    'offering_id' => 'offering_beast_ticket',
                    'name' => '山牲券',
                    'offering_item_id' => '山牲券',
                    'exchange_cost' => ['gold' => 260, 'sect_contribution' => 24],
                    'rewards' => ['gold' => 0, 'spirit_stone' => 0, 'sect_contribution' => 20, 'skill_points' => 0, 'items' => [
                        ['item_id' => '鹿蜀纹角碎片', 'count' => 2],
                        ['item_id' => '白玉髓', 'count' => 2],
                    ]],
                    'daily_limit' => 1,
                    'sort_order' => 40,
                    'is_enabled' => true,
                ],
            ],
        ];
    }

    public static function ensureDefaultSetting(): void
    {
        if (AppSetting::query()->where('key', self::SETTING_KEY)->exists()) {
            return;
        }

        AppSetting::setValue(
            self::SETTING_KEY,
            json_encode(self::defaultConfig(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }

    public static function loadConfig(): array
    {
        $raw = AppSetting::getValue(self::SETTING_KEY);
        if ($raw === null || trim($raw) === '') {
            return self::defaultConfig();
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return self::defaultConfig();
        }

        return self::mergeRecursive(self::defaultConfig(), $decoded);
    }

    public static function saveConfig(array $config): void
    {
        $normalized = self::validateAndNormalizeConfig($config);

        AppSetting::setValue(
            self::SETTING_KEY,
            json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }

    public static function exportConfig(): array
    {
        return self::validateAndNormalizeConfig(self::loadConfig());
    }

    private static function validateAndNormalizeConfig(array $config): array
    {
        $normalized = self::mergeRecursive(self::defaultConfig(), $config);
        $errors = [];

        $godId = trim((string) ($normalized['god_id'] ?? ''));
        $name = trim((string) ($normalized['name'] ?? ''));
        $unlockStageId = trim((string) ($normalized['unlock_stage_id'] ?? ''));

        if ($godId === '') {
            $errors['god_id'] = '山神 ID 不能为空。';
        }

        if ($name === '') {
            $errors['name'] = '山神名称不能为空。';
        }

        if ($unlockStageId === '' || ! Stage::query()->where('id', $unlockStageId)->where('is_enabled', true)->exists()) {
            $errors['unlock_stage_id'] = '山神解锁关卡无效。';
        }

        $normalized['offerings'] = self::normalizeOfferings($normalized['offerings'] ?? [], $errors);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $normalized;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeOfferings(mixed $rows, array &$errors): array
    {
        $normalized = [];
        $seen = [];

        foreach (is_array($rows) ? $rows : [] as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $offeringId = trim((string) ($row['offering_id'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            $offeringItemId = trim((string) ($row['offering_item_id'] ?? ''));

            if ($offeringId === '') {
                $errors["offerings.{$index}.offering_id"] = '供奉项 ID 不能为空。';
            } elseif (isset($seen[$offeringId])) {
                $errors["offerings.{$index}.offering_id"] = sprintf('供奉项 ID 重复：%s。', $offeringId);
            } else {
                $seen[$offeringId] = true;
            }

            if ($name === '') {
                $errors["offerings.{$index}.name"] = '供奉项名称不能为空。';
            }

            if ($offeringItemId === '' || ! Item::query()->where('id', $offeringItemId)->where('is_enabled', true)->exists()) {
                $errors["offerings.{$index}.offering_item_id"] = '祭品物品无效。';
            }

            $exchange = is_array($row['exchange_cost'] ?? null) ? $row['exchange_cost'] : [];
            $rewards = self::normalizeRewards($row['rewards'] ?? [], "offerings.{$index}.rewards", $errors);

            $normalized[] = [
                'offering_id' => $offeringId,
                'name' => $name,
                'offering_item_id' => $offeringItemId,
                'exchange_cost' => [
                    'gold' => max(0, (int) ($exchange['gold'] ?? 0)),
                    'sect_contribution' => max(0, (int) ($exchange['sect_contribution'] ?? 0)),
                ],
                'rewards' => $rewards,
                'daily_limit' => max(1, (int) ($row['daily_limit'] ?? 1)),
                'sort_order' => (int) ($row['sort_order'] ?? (($index + 1) * 10)),
                'is_enabled' => (bool) ($row['is_enabled'] ?? true),
            ];
        }

        return array_values($normalized);
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeRewards(mixed $value, string $field, array &$errors): array
    {
        $row = is_array($value) ? $value : [];
        $items = [];

        foreach (is_array($row['items'] ?? null) ? $row['items'] : [] as $index => $itemRow) {
            if (! is_array($itemRow)) {
                continue;
            }

            $itemId = trim((string) ($itemRow['item_id'] ?? ''));
            $count = $itemRow['count'] ?? null;

            if ($itemId === '') {
                $errors["{$field}.items.{$index}.item_id"] = '奖励物品不能为空。';
                continue;
            }

            if (! Item::query()->where('id', $itemId)->where('is_enabled', true)->exists()) {
                $errors["{$field}.items.{$index}.item_id"] = '奖励物品无效。';
                continue;
            }

            if (! is_numeric($count) || (int) $count < 1) {
                $errors["{$field}.items.{$index}.count"] = '奖励数量必须大于等于 1。';
                continue;
            }

            $items[] = [
                'item_id' => $itemId,
                'count' => (int) $count,
            ];
        }

        return [
            'gold' => max(0, (int) ($row['gold'] ?? 0)),
            'spirit_stone' => max(0, (int) ($row['spirit_stone'] ?? 0)),
            'sect_contribution' => max(0, (int) ($row['sect_contribution'] ?? 0)),
            'skill_points' => max(0, (int) ($row['skill_points'] ?? 0)),
            'items' => $items,
        ];
    }

    private static function mergeRecursive(array $defaults, array $override): array
    {
        $result = $defaults;

        foreach ($override as $key => $value) {
            if (is_array($value) && isset($result[$key]) && is_array($result[$key]) && self::isAssoc($value) && self::isAssoc($result[$key])) {
                $result[$key] = self::mergeRecursive($result[$key], $value);
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    private static function isAssoc(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }
}
