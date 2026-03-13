<?php

namespace App\Support;

use App\Models\BossCore;
use App\Models\Item;
use App\Models\Monster;
use App\Services\ItemCatalogImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class BossCoreModuleSupport
{
    public static function rulesPayload(): array
    {
        return [
            'item_anchor_rule' => 'Boss 核心成品统一通过 items.item_id 承接，且 main_type = boss_core、sub_type = boss_core。',
            'module_boundary' => [
                'Boss 核心独立于宝石模块。',
                'Boss 核心独立于套装件数效果，不占套装效果栏。',
                '首版每个核心仅 1 条主效果。',
            ],
            'recommended_sect_options' => BossCore::RECOMMENDED_SECT_OPTIONS,
            'recommended_build_options' => BossCore::RECOMMENDED_BUILD_OPTIONS,
            'effect_key_options' => BossCore::EFFECT_KEY_OPTIONS,
        ];
    }

    public static function carrierItemOptions(): array
    {
        return AdminOptions::itemOptions(fn (Builder $query): Builder => $query
            ->where('main_type', 'boss_core')
            ->where('sub_type', 'boss_core'));
    }

    public static function normalizeSingleCoreFormOrFail(array $data): array
    {
        $normalized = self::normalizeRowsOrFail(
            [$data],
            is_array($data['effects'] ?? null) ? $data['effects'] : [],
        );

        return [
            'core' => $normalized['boss_cores'][0],
            'effects' => $normalized['boss_core_effects'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $cores
     * @param  array<int, array<string, mixed>>  $effects
     * @return array{boss_cores: array<int, array<string, mixed>>, boss_core_effects: array<int, array<string, mixed>>}
     */
    public static function normalizeRowsOrFail(array $cores, array $effects): array
    {
        $errors = [];

        $normalizedCores = [];
        $seenCoreIds = [];
        foreach ($cores as $index => $row) {
            $normalized = self::normalizeCoreRow($row, "boss_cores.{$index}", $errors);
            if ($normalized === null) {
                continue;
            }

            $coreId = (string) $normalized['core_id'];
            if (isset($seenCoreIds[$coreId])) {
                $errors["boss_cores.{$index}.core_id"] = sprintf('core_id 重复：%s。', $coreId);
                continue;
            }

            $seenCoreIds[$coreId] = true;
            $normalizedCores[] = $normalized;
        }

        $normalizedEffects = [];
        foreach ($effects as $index => $row) {
            $normalized = self::normalizeEffectRow($row, "boss_core_effects.{$index}", $errors);
            if ($normalized !== null) {
                $normalizedEffects[] = $normalized;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        self::validateRelationshipsOrFail($normalizedCores, $normalizedEffects);

        return [
            'boss_cores' => array_values($normalizedCores),
            'boss_core_effects' => array_values($normalizedEffects),
        ];
    }

    public static function exportCore(\App\Models\BossCore $core): array
    {
        return [
            'core_id' => (string) $core->core_id,
            'item_id' => (string) $core->item_id,
            'core_name' => (string) $core->core_name,
            'display_name' => (string) $core->display_name,
            'source_boss_id' => (string) $core->source_boss_id,
            'quality' => (string) $core->quality,
            'rarity' => (string) $core->rarity,
            'recommended_sect' => (string) $core->recommended_sect,
            'recommended_build' => (string) $core->recommended_build,
            'icon' => $core->icon !== null ? (string) $core->icon : '',
            'summary' => $core->summary !== null ? (string) $core->summary : '',
            'drop_rate_note' => (string) $core->drop_rate_note,
            'sort_order' => (int) $core->sort_order,
            'is_enabled' => (bool) $core->is_enabled,
            'remark' => $core->remark !== null ? (string) $core->remark : '',
        ];
    }

    public static function exportEffect(\App\Models\BossCoreEffect $effect): array
    {
        return [
            'core_id' => (string) $effect->core_id,
            'effect_key' => (string) $effect->effect_key,
            'value_type' => (string) $effect->value_type,
            'value' => EquipmentSetModuleSupport::normalizeNumericValue((float) $effect->value),
            'summary' => $effect->summary !== null ? (string) $effect->summary : '',
            'sort_order' => (int) $effect->sort_order,
            'is_enabled' => (bool) $effect->is_enabled,
            'remark' => $effect->remark !== null ? (string) $effect->remark : '',
        ];
    }

    private static function normalizeCoreRow(mixed $row, string $path, array &$errors): ?array
    {
        if (! is_array($row)) {
            $errors[$path] = 'Boss 核心条目格式错误。';
            return null;
        }

        $coreId = trim((string) ($row['core_id'] ?? ''));
        $itemId = trim((string) ($row['item_id'] ?? ''));
        $coreName = trim((string) ($row['core_name'] ?? ''));
        $displayName = trim((string) ($row['display_name'] ?? ''));
        $sourceBossId = trim((string) ($row['source_boss_id'] ?? ''));
        $quality = ItemCatalogImportService::normalizeTier((string) ($row['quality'] ?? 'white'));
        $rarity = ItemCatalogImportService::normalizeTier((string) ($row['rarity'] ?? 'white'));
        $recommendedSect = trim((string) ($row['recommended_sect'] ?? 'none'));
        $recommendedBuild = trim((string) ($row['recommended_build'] ?? 'burst'));

        if ($coreId === '') {
            $errors["{$path}.core_id"] = '请填写 core_id。';
        }
        if ($itemId === '') {
            $errors["{$path}.item_id"] = '请填写 item_id。';
        }
        if ($coreName === '') {
            $errors["{$path}.core_name"] = '请填写 core_name。';
        }
        if ($displayName === '') {
            $errors["{$path}.display_name"] = '请填写 display_name。';
        }
        if ($sourceBossId === '') {
            $errors["{$path}.source_boss_id"] = '请填写 source_boss_id。';
        }
        if (! array_key_exists($quality, Item::QUALITY_OPTIONS)) {
            $errors["{$path}.quality"] = 'quality 非法。';
        }
        if (! array_key_exists($rarity, Item::RARITY_OPTIONS)) {
            $errors["{$path}.rarity"] = 'rarity 非法。';
        }
        if (! array_key_exists($recommendedSect, BossCore::RECOMMENDED_SECT_OPTIONS)) {
            $errors["{$path}.recommended_sect"] = 'recommended_sect 非法。';
        }
        if (! array_key_exists($recommendedBuild, BossCore::RECOMMENDED_BUILD_OPTIONS)) {
            $errors["{$path}.recommended_build"] = 'recommended_build 非法。';
        }

        return [
            'core_id' => $coreId,
            'item_id' => $itemId,
            'core_name' => $coreName,
            'display_name' => $displayName,
            'source_boss_id' => $sourceBossId,
            'quality' => $quality,
            'rarity' => $rarity,
            'recommended_sect' => $recommendedSect,
            'recommended_build' => $recommendedBuild,
            'icon' => self::nullableString($row['icon'] ?? null),
            'summary' => self::nullableString($row['summary'] ?? null),
            'drop_rate_note' => trim((string) ($row['drop_rate_note'] ?? '')),
            'sort_order' => max(0, (int) ($row['sort_order'] ?? 0)),
            'is_enabled' => (bool) ($row['is_enabled'] ?? true),
            'remark' => self::nullableString($row['remark'] ?? null),
        ];
    }

    private static function normalizeEffectRow(mixed $row, string $path, array &$errors): ?array
    {
        if (! is_array($row)) {
            $errors[$path] = 'Boss 核心效果条目格式错误。';
            return null;
        }

        $coreId = trim((string) ($row['core_id'] ?? ''));
        $effectKey = trim((string) ($row['effect_key'] ?? ''));
        $valueType = trim((string) ($row['value_type'] ?? ''));

        if ($coreId === '') {
            $errors["{$path}.core_id"] = '请填写 core_id。';
        }
        if (! array_key_exists($effectKey, BossCore::EFFECT_KEY_OPTIONS)) {
            $errors["{$path}.effect_key"] = 'effect_key 非法。';
        }
        if (! array_key_exists($valueType, BossCore::VALUE_TYPE_OPTIONS)) {
            $errors["{$path}.value_type"] = 'value_type 非法。';
        }

        return [
            'core_id' => $coreId,
            'effect_key' => $effectKey,
            'value_type' => $valueType,
            'value' => (float) ($row['value'] ?? 0),
            'summary' => self::nullableString($row['summary'] ?? null),
            'sort_order' => max(0, (int) ($row['sort_order'] ?? 0)),
            'is_enabled' => (bool) ($row['is_enabled'] ?? true),
            'remark' => self::nullableString($row['remark'] ?? null),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $cores
     * @param  array<int, array<string, mixed>>  $effects
     */
    private static function validateRelationshipsOrFail(array $cores, array $effects): void
    {
        $errors = [];
        $coreMap = collect($cores)->keyBy('core_id');
        $itemIds = collect($cores)->pluck('item_id')->filter()->unique()->values();
        $bossIds = collect($cores)->pluck('source_boss_id')->filter()->unique()->values();

        $itemsById = Item::query()->whereIn('item_id', $itemIds->all())->get()->keyBy('item_id');
        $bossesById = Monster::query()->whereIn('monster_id', $bossIds->all())->get()->keyBy('monster_id');

        foreach ($cores as $index => $row) {
            $item = $itemsById->get($row['item_id']);
            if (! $item instanceof Item) {
                $errors["boss_cores.{$index}.item_id"] = 'item_id 必须命中已存在的 items.item_id。';
            } elseif ($item->main_type !== 'boss_core' || $item->sub_type !== 'boss_core') {
                $errors["boss_cores.{$index}.item_id"] = 'Boss 核心 item 必须是 main_type=boss_core 且 sub_type=boss_core。';
            }

            $boss = $bossesById->get($row['source_boss_id']);
            if (! $boss instanceof Monster) {
                $errors["boss_cores.{$index}.source_boss_id"] = 'source_boss_id 必须命中 monsters.monster_id。';
            } elseif ((string) $boss->monster_type !== 'boss') {
                $errors["boss_cores.{$index}.source_boss_id"] = 'source_boss_id 必须指向 boss 类型怪物。';
            }
        }

        $effectsByCore = collect($effects)->groupBy('core_id');
        foreach ($effects as $index => $row) {
            if (! $coreMap->has($row['core_id'])) {
                $errors["boss_core_effects.{$index}.core_id"] = 'core_id 未命中 boss_cores.core_id。';
            }
        }

        foreach ($cores as $index => $row) {
            $count = $effectsByCore->get($row['core_id'], collect())->count();
            if ($count !== 1) {
                $errors["boss_cores.{$index}.effects"] = sprintf('Boss 核心 %s 首版必须且只能配置 1 条主效果。', $row['core_id']);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private static function nullableString(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }
}
