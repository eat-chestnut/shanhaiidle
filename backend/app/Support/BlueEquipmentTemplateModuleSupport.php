<?php

namespace App\Support;

use App\Models\BlueEquipmentTemplate;
use App\Models\BlueEquipmentTemplateBaseStat;
use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class BlueEquipmentTemplateModuleSupport
{
    public const MODULE = 'blue_equipment_templates';

    public const VERSION = 'v1';

    public const QUALITY = 'blue';

    public const RARITY = 'blue';

    public const BRACELET_MIN_LEVEL_BAND = 35;

    public const RING_MIN_LEVEL_BAND = 45;

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

    public const LEVEL_BAND_OPTIONS = [
        20 => '20级档',
        35 => '35级档',
        45 => '45级档',
        50 => '50级档',
        60 => '60级档',
    ];

    public const VALUE_TYPE_OPTIONS = [
        'flat' => '固定值',
        'percent' => '百分比',
    ];

    public const STAT_KEY_OPTIONS = [
        'MELEE_ATK' => '近战攻击（MELEE_ATK）',
        'HP' => '生命（HP）',
        'DEF' => '防御（DEF）',
        'ATK_SPEED' => '攻击速度（ATK_SPEED）',
        'CRIT_RATE' => '暴击率（CRIT_RATE）',
        'SKILL_DMG' => '技能伤害（SKILL_DMG）',
    ];

    public static function rulesPayload(): array
    {
        return [
            'talisman_excluded' => true,
            'bracelet_min_level_band' => self::BRACELET_MIN_LEVEL_BAND,
            'ring_min_level_band' => self::RING_MIN_LEVEL_BAND,
            'quality' => self::QUALITY,
            'rarity' => self::RARITY,
        ];
    }

    public static function resultItemOptions(): array
    {
        return AdminOptions::itemOptions(
            fn (Builder $query): Builder => $query
                ->where('main_type', 'equipment')
                ->where('sub_type', 'blue_equipment')
        );
    }

    /**
     * @return array{template: array<string, mixed>, base_stats: array<int, array<string, mixed>>}
     */
    public static function normalizeSingleTemplateFormOrFail(array $row, ?BlueEquipmentTemplate $record = null): array
    {
        $errors = [];
        $template = self::normalizeTemplateRow($row, 'template', $errors, true);
        $baseStats = self::normalizeBaseStatRows($row['base_stats'] ?? null, 'base_stats', $errors);

        if ($template !== null) {
            self::validateTemplateRecordConflicts($template, $errors, $record);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'template' => $template,
            'base_stats' => array_map(
                fn (array $baseStat): array => ['template_id' => (string) $template['template_id']] + $baseStat,
                $baseStats,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     templates: array<int, array<string, mixed>>,
     *     base_stats: array<int, array<string, mixed>>
     * }
     */
    public static function normalizeProjectPayloadOrFail(array $payload): array
    {
        $errors = [];

        $version = trim((string) ($payload['version'] ?? ''));
        if ($version !== self::VERSION) {
            $errors['version'] = sprintf('version 必须为 %s。', self::VERSION);
        }

        $module = trim((string) ($payload['module'] ?? ''));
        if ($module !== self::MODULE) {
            $errors['module'] = sprintf('module 必须为 %s。', self::MODULE);
        }

        $rules = $payload['rules'] ?? null;
        if (! is_array($rules)) {
            $errors['rules'] = 'rules 节点缺失。';
        } else {
            if (($rules['talisman_excluded'] ?? null) !== true) {
                $errors['rules.talisman_excluded'] = 'rules.talisman_excluded 必须为 true。';
            }
            if ((int) ($rules['bracelet_min_level_band'] ?? 0) !== self::BRACELET_MIN_LEVEL_BAND) {
                $errors['rules.bracelet_min_level_band'] = sprintf('手镯最低等级档必须为 %d。', self::BRACELET_MIN_LEVEL_BAND);
            }
            if ((int) ($rules['ring_min_level_band'] ?? 0) !== self::RING_MIN_LEVEL_BAND) {
                $errors['rules.ring_min_level_band'] = sprintf('戒指最低等级档必须为 %d。', self::RING_MIN_LEVEL_BAND);
            }
            if (trim((string) ($rules['quality'] ?? '')) !== self::QUALITY) {
                $errors['rules.quality'] = sprintf('quality 必须为 %s。', self::QUALITY);
            }
            if (trim((string) ($rules['rarity'] ?? '')) !== self::RARITY) {
                $errors['rules.rarity'] = sprintf('rarity 必须为 %s。', self::RARITY);
            }
        }

        $rows = $payload['templates'] ?? null;
        if (! is_array($rows)) {
            $errors['templates'] = 'templates 节点缺失。';
        }

        $normalizedTemplates = [];
        $normalizedBaseStats = [];
        $seenTemplateIds = [];
        $seenSlotBands = [];
        $seenResultItemIds = [];

        foreach (is_array($rows) ? array_values($rows) : [] as $index => $row) {
            $template = self::normalizeTemplateRow($row, "templates.{$index}", $errors, false);
            $baseStats = self::normalizeBaseStatRows(
                is_array($row) ? ($row['base_stats'] ?? null) : null,
                "templates.{$index}.base_stats",
                $errors,
            );

            if ($template === null) {
                continue;
            }

            $templateId = (string) $template['template_id'];
            if (isset($seenTemplateIds[$templateId])) {
                $errors["templates.{$index}.template_id"] = sprintf('template_id 重复：%s。', $templateId);
                continue;
            }
            $seenTemplateIds[$templateId] = true;

            $slotBand = sprintf('%s:%d', (string) $template['slot_type'], (int) $template['level_band']);
            if (isset($seenSlotBands[$slotBand])) {
                $errors["templates.{$index}.level_band"] = sprintf(
                    'slot_type 与 level_band 组合重复：%s / %d。',
                    (string) $template['slot_type'],
                    (int) $template['level_band'],
                );
                continue;
            }
            $seenSlotBands[$slotBand] = true;

            $resultItemId = (string) $template['result_item_id'];
            if (isset($seenResultItemIds[$resultItemId])) {
                $errors["templates.{$index}.result_item_id"] = sprintf('result_item_id 重复：%s。', $resultItemId);
                continue;
            }
            $seenResultItemIds[$resultItemId] = true;

            $normalizedTemplates[] = $template;

            foreach ($baseStats as $baseStat) {
                $normalizedBaseStats[] = ['template_id' => $templateId] + $baseStat;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'templates' => array_values($normalizedTemplates),
            'base_stats' => array_values($normalizedBaseStats),
        ];
    }

    public static function validateTemplateModelOrFail(BlueEquipmentTemplate $template): void
    {
        $errors = [];
        $normalized = self::normalizeTemplateRow(
            [
                'template_id' => $template->template_id,
                'template_name' => $template->template_name,
                'display_name' => $template->display_name,
                'slot_type' => $template->slot_type,
                'level_band' => $template->level_band,
                'result_item_id' => $template->result_item_id,
                'blue_affix_count_min' => $template->blue_affix_count_min,
                'blue_affix_count_max' => $template->blue_affix_count_max,
                'quality' => $template->quality,
                'rarity' => $template->rarity,
                'unlock_level' => $template->unlock_level,
                'icon' => $template->icon,
                'summary' => $template->summary,
                'sort_order' => $template->sort_order,
                'is_enabled' => $template->is_enabled,
                'remark' => $template->remark,
            ],
            'template',
            $errors,
            true,
        );

        if ($normalized !== null) {
            self::validateTemplateRecordConflicts($normalized, $errors, $template->exists ? $template : null);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $template->fill($normalized);
    }

    public static function validateBaseStatModelOrFail(BlueEquipmentTemplateBaseStat $baseStat): void
    {
        $errors = [];
        $rows = self::normalizeBaseStatRows(
            [[
                'stat_key' => $baseStat->stat_key,
                'value_type' => $baseStat->value_type,
                'value' => $baseStat->value,
                'sort_order' => $baseStat->sort_order,
                'is_enabled' => $baseStat->is_enabled,
                'remark' => $baseStat->remark,
            ]],
            'base_stats',
            $errors,
        );

        $templateId = trim((string) $baseStat->template_id);
        if ($templateId === '') {
            $errors['template_id'] = '请填写 template_id。';
        } elseif (! BlueEquipmentTemplate::query()->where('template_id', $templateId)->exists()) {
            $errors['template_id'] = sprintf('template_id 不存在：%s。', $templateId);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $normalized = $rows[0];
        $baseStat->template_id = $templateId;
        $baseStat->stat_key = (string) $normalized['stat_key'];
        $baseStat->value_type = (string) $normalized['value_type'];
        $baseStat->value = (float) $normalized['value'];
        $baseStat->sort_order = (int) $normalized['sort_order'];
        $baseStat->is_enabled = (bool) $normalized['is_enabled'];
        $baseStat->remark = $normalized['remark'];
    }

    public static function exportTemplate(BlueEquipmentTemplate $template): array
    {
        return [
            'template_id' => (string) $template->template_id,
            'template_name' => (string) $template->template_name,
            'display_name' => (string) $template->display_name,
            'slot_type' => (string) $template->slot_type,
            'level_band' => (int) $template->level_band,
            'result_item_id' => (string) $template->result_item_id,
            'blue_affix_count_min' => (int) $template->blue_affix_count_min,
            'blue_affix_count_max' => (int) $template->blue_affix_count_max,
            'quality' => (string) $template->quality,
            'rarity' => (string) $template->rarity,
            'unlock_level' => (int) $template->unlock_level,
            'icon' => $template->icon !== null ? (string) $template->icon : '',
            'summary' => $template->summary !== null ? (string) $template->summary : '',
            'sort_order' => (int) $template->sort_order,
            'is_enabled' => (bool) $template->is_enabled,
            'remark' => $template->remark !== null ? (string) $template->remark : '',
            'base_stats' => $template->baseStats
                ->map(fn (BlueEquipmentTemplateBaseStat $baseStat): array => [
                    'stat_key' => (string) $baseStat->stat_key,
                    'value_type' => (string) $baseStat->value_type,
                    'value' => self::normalizeNumericValue((float) $baseStat->value),
                    'sort_order' => (int) $baseStat->sort_order,
                    'is_enabled' => (bool) $baseStat->is_enabled,
                    'remark' => $baseStat->remark !== null ? (string) $baseStat->remark : '',
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $template
     * @return array<string, mixed>
     */
    public static function minimalResultItemPayload(array $template): array
    {
        $itemName = trim((string) ($template['display_name'] ?? ''));
        $requiredLevel = max(1, (int) ($template['unlock_level'] ?? $template['level_band'] ?? 1));

        return [
            'item_id' => (string) $template['result_item_id'],
            'item_name' => $itemName,
            'display_name' => $itemName,
            'main_type' => 'equipment',
            'sub_type' => 'blue_equipment',
            'quality' => self::QUALITY,
            'rarity' => self::RARITY,
            'icon' => filled($template['icon'] ?? null) ? trim((string) $template['icon']) : '',
            'desc' => filled($template['summary'] ?? null) ? trim((string) $template['summary']) : null,
            'is_stackable' => false,
            'max_stack' => 1,
            'is_enabled' => (bool) ($template['is_enabled'] ?? true),
            'sort_order' => 910000 + max(0, (int) ($template['sort_order'] ?? 0)),
            'remark' => filled($template['remark'] ?? null) ? trim((string) $template['remark']) : '',
            'source_library' => 'equipment_catalog',
            'required_level' => $requiredLevel,
            'bind_type' => 'none',
            'sell_price' => 0,
            'use_type' => 'equip',
            'rarity_frame_key' => '',
            'type' => 'item',
            'material_type' => '',
            'trait' => '',
            'effect_type' => '',
            'target_scope' => '',
            'effect_payload' => null,
            'drop_unlock_level' => $requiredLevel,
            'socket_limit' => [],
            'source_tags' => [],
            'use_tags' => [],
            'stack_limit' => 1,
            'can_compose' => false,
            'can_reforge' => false,
        ];
    }

    public static function normalizeNumericValue(float $value): int|float
    {
        return floor($value) === $value ? (int) $value : round($value, 4);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function validateTemplateRecordConflicts(array $row, array &$errors, ?BlueEquipmentTemplate $record = null): void
    {
        $templateId = (string) $row['template_id'];
        $slotType = (string) $row['slot_type'];
        $levelBand = (int) $row['level_band'];
        $resultItemId = (string) $row['result_item_id'];
        $recordId = $record?->getKey();

        if (BlueEquipmentTemplate::query()
            ->when($recordId !== null, fn (Builder $query): Builder => $query->where($query->getModel()->getKeyName(), '!=', $recordId))
            ->where('template_id', $templateId)
            ->exists()) {
            $errors['template.template_id'] = sprintf('template_id 已存在：%s。', $templateId);
        }

        if (BlueEquipmentTemplate::query()
            ->when($recordId !== null, fn (Builder $query): Builder => $query->where($query->getModel()->getKeyName(), '!=', $recordId))
            ->where('slot_type', $slotType)
            ->where('level_band', $levelBand)
            ->exists()) {
            $errors['template.level_band'] = sprintf('slot_type 与 level_band 组合已存在：%s / %d。', $slotType, $levelBand);
        }

        if (BlueEquipmentTemplate::query()
            ->when($recordId !== null, fn (Builder $query): Builder => $query->where($query->getModel()->getKeyName(), '!=', $recordId))
            ->where('result_item_id', $resultItemId)
            ->exists()) {
            $errors['template.result_item_id'] = sprintf('result_item_id 已存在：%s。', $resultItemId);
        }
    }

    /**
     * @param  mixed  $row
     * @return array<string, mixed>|null
     */
    private static function normalizeTemplateRow(mixed $row, string $path, array &$errors, bool $requireExistingResultItem): ?array
    {
        if (! is_array($row)) {
            $errors[$path] = '蓝装模板条目格式错误。';
            return null;
        }

        $templateId = trim((string) ($row['template_id'] ?? ''));
        $templateName = trim((string) ($row['template_name'] ?? ''));
        $displayName = trim((string) ($row['display_name'] ?? ''));
        $slotType = trim((string) ($row['slot_type'] ?? ''));
        $levelBand = (int) ($row['level_band'] ?? 0);
        $resultItemId = trim((string) ($row['result_item_id'] ?? ''));
        $blueAffixCountMin = max(0, (int) ($row['blue_affix_count_min'] ?? 0));
        $blueAffixCountMax = max(0, (int) ($row['blue_affix_count_max'] ?? 0));
        $quality = trim((string) ($row['quality'] ?? ''));
        $rarity = trim((string) ($row['rarity'] ?? ''));
        $unlockLevel = max(1, (int) ($row['unlock_level'] ?? $levelBand));
        $sortOrder = max(0, (int) ($row['sort_order'] ?? 0));

        if ($templateId === '') {
            $errors["{$path}.template_id"] = '请填写 template_id。';
        }

        if ($templateName === '') {
            $errors["{$path}.template_name"] = '请填写 template_name。';
        }

        if ($displayName === '') {
            $errors["{$path}.display_name"] = '请填写 display_name。';
        }

        if ($slotType === 'talisman') {
            $errors["{$path}.slot_type"] = '护符没有蓝色装备，slot_type 不能为 talisman。';
        } elseif (! array_key_exists($slotType, self::SLOT_OPTIONS)) {
            $errors["{$path}.slot_type"] = 'slot_type 非法。';
        }

        if (! array_key_exists($levelBand, self::LEVEL_BAND_OPTIONS)) {
            $errors["{$path}.level_band"] = 'level_band 非法。';
        } else {
            self::validateSlotAndLevelBand($slotType, $levelBand, $path, $errors);
        }

        if ($resultItemId === '') {
            $errors["{$path}.result_item_id"] = 'result_item_id 不允许为空。';
        } elseif ($requireExistingResultItem && ! Item::query()->where('item_id', $resultItemId)->exists()) {
            $errors["{$path}.result_item_id"] = sprintf('result_item_id 未关联到 items.item_id：%s。', $resultItemId);
        }

        if ($blueAffixCountMin > $blueAffixCountMax) {
            $errors["{$path}.blue_affix_count_min"] = 'blue_affix_count_min 必须小于等于 blue_affix_count_max。';
        }

        if ($quality !== self::QUALITY) {
            $errors["{$path}.quality"] = sprintf('quality 必须为 %s。', self::QUALITY);
        }

        if ($rarity !== self::RARITY) {
            $errors["{$path}.rarity"] = sprintf('rarity 必须为 %s。', self::RARITY);
        }

        return [
            'template_id' => $templateId,
            'template_name' => $templateName,
            'display_name' => $displayName,
            'slot_type' => $slotType,
            'level_band' => $levelBand,
            'result_item_id' => $resultItemId,
            'blue_affix_count_min' => $blueAffixCountMin,
            'blue_affix_count_max' => $blueAffixCountMax,
            'quality' => self::QUALITY,
            'rarity' => self::RARITY,
            'unlock_level' => $unlockLevel,
            'icon' => filled($row['icon'] ?? null) ? trim((string) $row['icon']) : null,
            'summary' => filled($row['summary'] ?? null) ? trim((string) $row['summary']) : null,
            'sort_order' => $sortOrder,
            'is_enabled' => (bool) ($row['is_enabled'] ?? true),
            'remark' => filled($row['remark'] ?? null) ? trim((string) $row['remark']) : null,
        ];
    }

    /**
     * @param  mixed  $rows
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeBaseStatRows(mixed $rows, string $path, array &$errors): array
    {
        if (! is_array($rows)) {
            $errors[$path] = 'base_stats 必须为数组。';
            return [];
        }

        if ($rows === []) {
            $errors[$path] = '请至少配置一条白字属性。';
            return [];
        }

        $normalized = [];

        foreach (array_values($rows) as $index => $row) {
            if (! is_array($row)) {
                $errors["{$path}.{$index}"] = '白字条目格式错误。';
                continue;
            }

            $statKey = trim((string) ($row['stat_key'] ?? ''));
            $valueType = trim((string) ($row['value_type'] ?? ''));
            $value = $row['value'] ?? null;
            $sortOrder = max(0, (int) ($row['sort_order'] ?? 0));

            if (! array_key_exists($statKey, self::STAT_KEY_OPTIONS)) {
                $errors["{$path}.{$index}.stat_key"] = 'stat_key 非法。';
            }

            if (! array_key_exists($valueType, self::VALUE_TYPE_OPTIONS)) {
                $errors["{$path}.{$index}.value_type"] = 'value_type 非法。';
            }

            if (! is_numeric($value)) {
                $errors["{$path}.{$index}.value"] = 'value 必须为数字。';
            }

            $normalized[] = [
                'stat_key' => $statKey,
                'value_type' => $valueType,
                'value' => self::normalizeNumericValue((float) $value),
                'sort_order' => $sortOrder,
                'is_enabled' => (bool) ($row['is_enabled'] ?? true),
                'remark' => filled($row['remark'] ?? null) ? trim((string) $row['remark']) : null,
            ];
        }

        return array_values($normalized);
    }

    private static function validateSlotAndLevelBand(string $slotType, int $levelBand, string $path, array &$errors): void
    {
        if ($slotType === 'bracelet' && $levelBand < self::BRACELET_MIN_LEVEL_BAND) {
            $errors["{$path}.level_band"] = sprintf('手镯从 %d 级档开始。', self::BRACELET_MIN_LEVEL_BAND);
        }

        if ($slotType === 'ring' && $levelBand < self::RING_MIN_LEVEL_BAND) {
            $errors["{$path}.level_band"] = sprintf('戒指从 %d 级档开始。', self::RING_MIN_LEVEL_BAND);
        }
    }
}
