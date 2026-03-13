<?php

namespace App\Support;

use App\Models\BlueAffix;
use App\Models\BlueAffixSlotRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class BlueAffixModuleSupport
{
    public const MODULE = 'blue_affixes';

    public const VERSION = 'v1';

    public const QUALITY = 'blue';

    public const RARITY = 'blue';

    public const LEVEL_BAND_OPTIONS = [
        20 => '20级档',
        35 => '35级档',
        45 => '45级档',
    ];

    public const SLOT_OPTIONS = [
        'main_weapon' => '主武器',
        'sub_weapon' => '副武器',
        'armor' => '盔甲',
        'leg' => '护腿',
        'shoe' => '鞋子',
        'cloak' => '披风',
        'helmet' => '头盔',
        'necklace' => '项链',
        'bracelet' => '手镯',
        'ring' => '戒指',
    ];

    public const VALUE_TYPE_OPTIONS = [
        'flat' => '固定值',
        'percent' => '百分比',
    ];

    public const EFFECT_KEY_OPTIONS = [
        'bonus_melee_atk' => '攻击（bonus_melee_atk）',
        'bonus_hp' => '生命（bonus_hp）',
        'bonus_def' => '防御（bonus_def）',
        'bonus_crit_rate' => '暴击（bonus_crit_rate）',
        'bonus_crit_dmg' => '暴伤（bonus_crit_dmg）',
        'bonus_atk_speed' => '攻速（bonus_atk_speed）',
        'bonus_skill_dmg' => '技伤（bonus_skill_dmg）',
        'bonus_boss_dmg' => 'Boss增伤（bonus_boss_dmg）',
        'bonus_lifesteal' => '吸血（bonus_lifesteal）',
    ];

    public static function rulesPayload(): array
    {
        return [
            'talisman_excluded' => true,
            'quality' => self::QUALITY,
            'rarity' => self::RARITY,
            'supported_level_bands' => array_map('intval', array_keys(self::LEVEL_BAND_OPTIONS)),
            'supported_effect_keys' => array_keys(self::EFFECT_KEY_OPTIONS),
        ];
    }

    /**
     * @return array{affix: array<string, mixed>, slot_rules: array<int, array<string, mixed>>}
     */
    public static function normalizeSingleAffixFormOrFail(array $row, ?BlueAffix $record = null): array
    {
        $errors = [];
        $affix = self::normalizeAffixRow($row, 'affix', $errors);
        $slotRules = self::normalizeSlotRuleRows($row['slot_rules'] ?? null, 'slot_rules', $errors);

        if ($affix !== null) {
            self::validateAffixRecordConflicts($affix, $errors, $record);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'affix' => $affix,
            'slot_rules' => array_map(
                fn (array $slotRule): array => ['affix_id' => (string) $affix['affix_id']] + $slotRule,
                $slotRules,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{affixes: array<int, array<string, mixed>>, slot_rules: array<int, array<string, mixed>>}
     */
    public static function normalizeProjectPayloadOrFail(array $payload): array
    {
        $errors = [];

        if (trim((string) ($payload['version'] ?? '')) !== self::VERSION) {
            $errors['version'] = sprintf('version 必须为 %s。', self::VERSION);
        }

        if (trim((string) ($payload['module'] ?? '')) !== self::MODULE) {
            $errors['module'] = sprintf('module 必须为 %s。', self::MODULE);
        }

        $rules = $payload['rules'] ?? null;
        if (! is_array($rules)) {
            $errors['rules'] = 'rules 节点缺失。';
        } else {
            if (($rules['talisman_excluded'] ?? null) !== true) {
                $errors['rules.talisman_excluded'] = 'rules.talisman_excluded 必须为 true。';
            }
            if (trim((string) ($rules['quality'] ?? '')) !== self::QUALITY) {
                $errors['rules.quality'] = sprintf('quality 必须为 %s。', self::QUALITY);
            }
            if (trim((string) ($rules['rarity'] ?? '')) !== self::RARITY) {
                $errors['rules.rarity'] = sprintf('rarity 必须为 %s。', self::RARITY);
            }
            if (array_values((array) ($rules['supported_level_bands'] ?? [])) !== array_map('intval', array_keys(self::LEVEL_BAND_OPTIONS))) {
                $errors['rules.supported_level_bands'] = 'supported_level_bands 与当前模块定义不一致。';
            }
            if (array_values((array) ($rules['supported_effect_keys'] ?? [])) !== array_keys(self::EFFECT_KEY_OPTIONS)) {
                $errors['rules.supported_effect_keys'] = 'supported_effect_keys 与当前模块定义不一致。';
            }
        }

        $rows = $payload['affixes'] ?? null;
        if (! is_array($rows)) {
            $errors['affixes'] = 'affixes 节点缺失。';
        }

        $normalizedAffixes = [];
        $normalizedSlotRules = [];
        $seenAffixIds = [];

        foreach (is_array($rows) ? array_values($rows) : [] as $index => $row) {
            $affix = self::normalizeAffixRow($row, "affixes.{$index}", $errors);
            $slotRules = self::normalizeSlotRuleRows(
                is_array($row) ? ($row['slot_rules'] ?? null) : null,
                "affixes.{$index}.slot_rules",
                $errors,
            );

            if ($affix === null) {
                continue;
            }

            $affixId = (string) $affix['affix_id'];
            if (isset($seenAffixIds[$affixId])) {
                $errors["affixes.{$index}.affix_id"] = sprintf('affix_id 重复：%s。', $affixId);
                continue;
            }

            $seenAffixIds[$affixId] = true;
            $normalizedAffixes[] = $affix;

            foreach ($slotRules as $slotRule) {
                $normalizedSlotRules[] = ['affix_id' => $affixId] + $slotRule;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'affixes' => array_values($normalizedAffixes),
            'slot_rules' => array_values($normalizedSlotRules),
        ];
    }

    public static function validateAffixModelOrFail(BlueAffix $affix): void
    {
        $errors = [];
        $normalized = self::normalizeAffixRow(
            [
                'affix_id' => $affix->affix_id,
                'affix_name' => $affix->affix_name,
                'display_name' => $affix->display_name,
                'effect_key' => $affix->effect_key,
                'value_type' => $affix->value_type,
                'value_min' => $affix->value_min,
                'value_max' => $affix->value_max,
                'weight' => $affix->weight,
                'level_band' => $affix->level_band,
                'quality' => $affix->quality,
                'rarity' => $affix->rarity,
                'summary' => $affix->summary,
                'sort_order' => $affix->sort_order,
                'is_enabled' => $affix->is_enabled,
                'remark' => $affix->remark,
            ],
            'affix',
            $errors,
        );

        if ($normalized !== null) {
            self::validateAffixRecordConflicts($normalized, $errors, $affix->exists ? $affix : null);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $affix->fill($normalized);
    }

    public static function validateSlotRuleModelOrFail(BlueAffixSlotRule $slotRule): void
    {
        $errors = [];
        $rows = self::normalizeSlotRuleRows([[
            'slot_type' => $slotRule->slot_type,
            'sort_order' => $slotRule->sort_order,
            'is_enabled' => $slotRule->is_enabled,
            'remark' => $slotRule->remark,
        ]], 'slot_rules', $errors);

        $affixId = trim((string) $slotRule->affix_id);
        if ($affixId === '') {
            $errors['affix_id'] = '请填写 affix_id。';
        } elseif (! BlueAffix::query()->where('affix_id', $affixId)->exists()) {
            $errors['affix_id'] = sprintf('affix_id 不存在：%s。', $affixId);
        } else {
            $query = BlueAffixSlotRule::query()
                ->where('affix_id', $affixId)
                ->where('slot_type', (string) $rows[0]['slot_type']);

            if ($slotRule->exists) {
                $query->where($slotRule->getKeyName(), '!=', $slotRule->getKey());
            }

            if ($query->exists()) {
                $errors['slot_type'] = sprintf('同一 affix_id 下 slot_type 重复：%s。', (string) $rows[0]['slot_type']);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $slotRule->affix_id = $affixId;
        $slotRule->slot_type = (string) $rows[0]['slot_type'];
        $slotRule->sort_order = (int) $rows[0]['sort_order'];
        $slotRule->is_enabled = (bool) $rows[0]['is_enabled'];
        $slotRule->remark = $rows[0]['remark'];
    }

    public static function exportAffix(BlueAffix $affix): array
    {
        return [
            'affix_id' => (string) $affix->affix_id,
            'affix_name' => (string) $affix->affix_name,
            'display_name' => (string) $affix->display_name,
            'effect_key' => (string) $affix->effect_key,
            'value_type' => (string) $affix->value_type,
            'value_min' => self::normalizeNumericValue((float) $affix->value_min),
            'value_max' => self::normalizeNumericValue((float) $affix->value_max),
            'weight' => (int) $affix->weight,
            'level_band' => (int) $affix->level_band,
            'quality' => (string) $affix->quality,
            'rarity' => (string) $affix->rarity,
            'summary' => $affix->summary !== null ? (string) $affix->summary : '',
            'sort_order' => (int) $affix->sort_order,
            'is_enabled' => (bool) $affix->is_enabled,
            'remark' => $affix->remark !== null ? (string) $affix->remark : '',
            'slot_rules' => $affix->slotRules
                ->map(fn (BlueAffixSlotRule $slotRule): array => [
                    'slot_type' => (string) $slotRule->slot_type,
                    'sort_order' => (int) $slotRule->sort_order,
                    'is_enabled' => (bool) $slotRule->is_enabled,
                    'remark' => $slotRule->remark !== null ? (string) $slotRule->remark : '',
                ])
                ->values()
                ->all(),
        ];
    }

    public static function normalizeNumericValue(float $value): int|float
    {
        return floor($value) === $value ? (int) $value : round($value, 4);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function validateAffixRecordConflicts(array $row, array &$errors, ?BlueAffix $record = null): void
    {
        $affixId = (string) $row['affix_id'];
        $recordId = $record?->getKey();

        if (BlueAffix::query()
            ->when($recordId !== null, fn (Builder $query): Builder => $query->where($query->getModel()->getKeyName(), '!=', $recordId))
            ->where('affix_id', $affixId)
            ->exists()) {
            $errors['affix.affix_id'] = sprintf('affix_id 已存在：%s。', $affixId);
        }
    }

    /**
     * @param  mixed  $row
     * @return array<string, mixed>|null
     */
    private static function normalizeAffixRow(mixed $row, string $path, array &$errors): ?array
    {
        if (! is_array($row)) {
            $errors[$path] = '蓝词条条目格式错误。';
            return null;
        }

        $affixId = trim((string) ($row['affix_id'] ?? ''));
        $affixName = trim((string) ($row['affix_name'] ?? ''));
        $displayName = trim((string) ($row['display_name'] ?? ''));
        $effectKey = trim((string) ($row['effect_key'] ?? ''));
        $valueType = trim((string) ($row['value_type'] ?? ''));
        $valueMin = $row['value_min'] ?? null;
        $valueMax = $row['value_max'] ?? null;
        $weight = (int) ($row['weight'] ?? 0);
        $levelBand = (int) ($row['level_band'] ?? 0);
        $quality = trim((string) ($row['quality'] ?? ''));
        $rarity = trim((string) ($row['rarity'] ?? ''));

        if ($affixId === '') {
            $errors["{$path}.affix_id"] = '请填写 affix_id。';
        }

        if ($affixName === '') {
            $errors["{$path}.affix_name"] = '请填写 affix_name。';
        }

        if ($displayName === '') {
            $errors["{$path}.display_name"] = '请填写 display_name。';
        }

        if (! array_key_exists($effectKey, self::EFFECT_KEY_OPTIONS)) {
            $errors["{$path}.effect_key"] = 'effect_key 非法。';
        }

        if (! array_key_exists($valueType, self::VALUE_TYPE_OPTIONS)) {
            $errors["{$path}.value_type"] = 'value_type 非法。';
        }

        if (! is_numeric($valueMin)) {
            $errors["{$path}.value_min"] = 'value_min 必须为数字。';
        }

        if (! is_numeric($valueMax)) {
            $errors["{$path}.value_max"] = 'value_max 必须为数字。';
        }

        if (is_numeric($valueMin) && is_numeric($valueMax) && (float) $valueMin > (float) $valueMax) {
            $errors["{$path}.value_min"] = 'value_min 必须小于等于 value_max。';
        }

        if ($weight <= 0) {
            $errors["{$path}.weight"] = 'weight 必须大于 0。';
        }

        if (! array_key_exists($levelBand, self::LEVEL_BAND_OPTIONS)) {
            $errors["{$path}.level_band"] = 'level_band 非法。';
        }

        if ($quality !== self::QUALITY) {
            $errors["{$path}.quality"] = sprintf('quality 必须为 %s。', self::QUALITY);
        }

        if ($rarity !== self::RARITY) {
            $errors["{$path}.rarity"] = sprintf('rarity 必须为 %s。', self::RARITY);
        }

        return [
            'affix_id' => $affixId,
            'affix_name' => $affixName,
            'display_name' => $displayName,
            'effect_key' => $effectKey,
            'value_type' => $valueType,
            'value_min' => self::normalizeNumericValue((float) $valueMin),
            'value_max' => self::normalizeNumericValue((float) $valueMax),
            'weight' => $weight,
            'level_band' => $levelBand,
            'quality' => self::QUALITY,
            'rarity' => self::RARITY,
            'summary' => filled($row['summary'] ?? null) ? trim((string) $row['summary']) : null,
            'sort_order' => max(0, (int) ($row['sort_order'] ?? 0)),
            'is_enabled' => (bool) ($row['is_enabled'] ?? true),
            'remark' => filled($row['remark'] ?? null) ? trim((string) $row['remark']) : null,
        ];
    }

    /**
     * @param  mixed  $rows
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeSlotRuleRows(mixed $rows, string $path, array &$errors): array
    {
        if (! is_array($rows)) {
            $errors[$path] = 'slot_rules 必须为数组。';
            return [];
        }

        if ($rows === []) {
            $errors[$path] = '请至少配置一条适用部位规则。';
            return [];
        }

        $normalized = [];
        $seenSlots = [];

        foreach (array_values($rows) as $index => $row) {
            if (! is_array($row)) {
                $errors["{$path}.{$index}"] = '部位规则条目格式错误。';
                continue;
            }

            $slotType = trim((string) ($row['slot_type'] ?? ''));
            if ($slotType === 'talisman') {
                $errors["{$path}.{$index}.slot_type"] = '护符不参与蓝词条体系，slot_type 不能为 talisman。';
            } elseif (! array_key_exists($slotType, self::SLOT_OPTIONS)) {
                $errors["{$path}.{$index}.slot_type"] = 'slot_type 非法。';
            } elseif (isset($seenSlots[$slotType])) {
                $errors["{$path}.{$index}.slot_type"] = sprintf('同一 affix 下 slot_type 重复：%s。', $slotType);
            } else {
                $seenSlots[$slotType] = true;
            }

            $normalized[] = [
                'slot_type' => $slotType,
                'sort_order' => max(0, (int) ($row['sort_order'] ?? 0)),
                'is_enabled' => (bool) ($row['is_enabled'] ?? true),
                'remark' => filled($row['remark'] ?? null) ? trim((string) $row['remark']) : null,
            ];
        }

        return array_values($normalized);
    }
}
