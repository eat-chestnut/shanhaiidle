<?php

namespace App\Support;

use App\Models\Item;
use App\Models\MaterialDungeon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ItemEffectRegistry
{
    public const USE_EFFECT_TYPE = 'use_effect';

    public static function isGem(?string $type): bool
    {
        return $type === 'gem';
    }

    public static function supportsUseEffect(?string $type, ?string $subType): bool
    {
        return $type === 'item' && array_key_exists((string) $subType, AdminOptions::consumableItemSubTypeOptions());
    }

    public static function supportsComposeToggle(?string $type): bool
    {
        return in_array($type, ['gem', 'blueprint_fragment'], true);
    }

    public static function supportsRulesTab(?string $type, ?string $subType): bool
    {
        return static::isGem($type) || static::supportsUseEffect($type, $subType) || static::supportsComposeToggle($type);
    }

    public static function effectTypeLabel(?string $type, ?string $subType, ?string $effectType = null): string
    {
        if (static::isGem($type)) {
            return $subType === 'skill' ? '技能效果' : '属性加成';
        }

        if (static::supportsUseEffect($type, $subType)) {
            return '使用效果';
        }

        return blank($effectType) ? '—' : (string) $effectType;
    }

    public static function defaultFormState(): array
    {
        $state = GemEffectRegistry::defaultFormState();
        $state['use_effect_template_code'] = null;

        foreach (static::useEffectParamDefinitions() as $paramKey => $definition) {
            $state[$paramKey] = $definition['default'] ?? null;
        }

        return $state;
    }

    public static function useEffectTemplates(): array
    {
        return [
            'restore_stamina' => [
                'label' => '恢复体力',
                'params' => [
                    'stamina_amount' => ['label' => '恢复体力', 'type' => 'integer', 'min' => 1, 'default' => 10],
                ],
            ],
            'grant_exp' => [
                'label' => '增加经验',
                'params' => [
                    'exp_amount' => ['label' => '经验值', 'type' => 'integer', 'min' => 1, 'default' => 100],
                ],
            ],
            'grant_item' => [
                'label' => '发放奖励物品',
                'params' => [
                    'reward_item_id' => ['label' => '奖励物品', 'type' => 'item_select'],
                    'reward_count' => ['label' => '数量', 'type' => 'integer', 'min' => 1, 'default' => 1],
                ],
            ],
            'reset_dungeon_attempt' => [
                'label' => '重置副本次数',
                'params' => [
                    'dungeon_id' => ['label' => '副本', 'type' => 'dungeon_select'],
                    'reset_count' => ['label' => '重置次数', 'type' => 'integer', 'min' => 1, 'default' => 1],
                ],
            ],
        ];
    }

    public static function useEffectTemplateOptions(): array
    {
        return Collection::make(static::useEffectTemplates())
            ->mapWithKeys(fn (array $definition, string $code): array => [$code => (string) $definition['label']])
            ->all();
    }

    public static function useEffectTemplateLabel(?string $code): string
    {
        if (blank($code)) {
            return '';
        }

        return (string) (static::useEffectTemplateOptions()[$code] ?? $code);
    }

    public static function useEffectParamDefinitions(): array
    {
        $params = [];

        foreach (static::useEffectTemplates() as $template) {
            foreach (($template['params'] ?? []) as $paramKey => $definition) {
                $params[$paramKey] = $definition;
            }
        }

        return $params;
    }

    public static function dungeonOptions(): array
    {
        return MaterialDungeon::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (MaterialDungeon $row): array => [$row->dungeon_id => (string) $row->name])
            ->all();
    }

    public static function populateRecordFormData(array $data): array
    {
        $type = trim((string) ($data['type'] ?? 'material'));
        $data['material_type'] = AdminOptions::normalizedMaterialTypeForRecord(
            $type,
            trim((string) ($data['material_type'] ?? '')),
            trim((string) ($data['sub_type'] ?? '')),
        );
        $data['sub_type'] = AdminOptions::normalizedSubTypeForRecord(
            $type,
            $data['material_type'],
            trim((string) ($data['sub_type'] ?? '')),
        );
        $subType = trim((string) ($data['sub_type'] ?? ''));

        if (static::isGem($type)) {
            return GemEffectRegistry::populateRecordFormData($data);
        }

        $data['effect_form'] = static::defaultFormState();

        if (static::supportsUseEffect($type, $subType)) {
            $payload = is_array($data['effect_payload'] ?? null) ? $data['effect_payload'] : [];
            $effectCode = trim((string) ($payload['effect_code'] ?? ''));
            $params = $payload['params'] ?? [];
            if (! is_array($params)) {
                $params = [];
            }

            $data['effect_form']['use_effect_template_code'] = $effectCode !== '' ? $effectCode : null;
            foreach (static::useEffectParamDefinitions() as $paramKey => $definition) {
                $data['effect_form'][$paramKey] = $params[$paramKey] ?? ($definition['default'] ?? null);
            }
        } else {
            $data['effect_type'] = null;
            $data['target_scope'] = null;
        }

        return $data;
    }

    public static function normalizeRecordDataOrFail(array $data): array
    {
        $type = trim((string) ($data['type'] ?? 'material'));
        $subType = trim((string) ($data['sub_type'] ?? ''));

        $data['material_type'] = AdminOptions::normalizedMaterialTypeForRecord(
            $type,
            trim((string) ($data['material_type'] ?? '')),
            $subType,
        );
        $data['sub_type'] = AdminOptions::normalizedSubTypeForRecord($type, $data['material_type'], $subType);

        if (static::isGem($type)) {
            return GemEffectRegistry::normalizeRecordDataOrFail($data);
        }

        $data['socket_limit'] = null;
        $data['can_reforge'] = false;
        $data['target_scope'] = null;

        if (static::supportsUseEffect($type, $data['sub_type'])) {
            $effectForm = $data['effect_form'] ?? [];
            if (! is_array($effectForm)) {
                $effectForm = [];
            }

            $data['effect_type'] = static::USE_EFFECT_TYPE;
            $data['effect_payload'] = static::normalizeUseEffectPayloadOrFail($effectForm);
        } else {
            $data['effect_type'] = null;
            $data['effect_payload'] = null;
        }

        if (! static::supportsComposeToggle($type)) {
            $data['can_compose'] = false;
        }

        unset($data['effect_form']);

        return $data;
    }

    public static function effectSummary(mixed $recordOrPayload, ?string $type = null, ?string $subType = null, ?string $targetScope = null): string
    {
        if ($recordOrPayload instanceof Item) {
            $payload = is_array($recordOrPayload->effect_payload) ? $recordOrPayload->effect_payload : [];
            $type = (string) $recordOrPayload->type;
            $subType = (string) ($recordOrPayload->sub_type ?? '');
            $targetScope = (string) ($recordOrPayload->target_scope ?? '');
        } else {
            $payload = is_array($recordOrPayload) ? $recordOrPayload : [];
        }

        if ($payload === []) {
            return $type === 'blueprint_fragment' && static::supportsComposeToggle($type) ? '可合成' : '';
        }

        if (static::isGem($type)) {
            return GemEffectRegistry::effectSummary($payload, $targetScope);
        }

        $effectCode = trim((string) ($payload['effect_code'] ?? ''));
        $template = static::useEffectTemplates()[$effectCode] ?? null;
        if ($template === null) {
            return '';
        }

        $params = $payload['params'] ?? [];
        if (! is_array($params)) {
            $params = [];
        }

        $parts = [];
        foreach (($template['params'] ?? []) as $paramKey => $definition) {
            $value = $params[$paramKey] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            $parts[] = sprintf('%s %s', (string) $definition['label'], static::displayParamValue($paramKey, $value, (string) ($definition['type'] ?? 'number')));
        }

        return $parts === []
            ? (string) $template['label']
            : sprintf('%s（%s）', (string) $template['label'], implode('，', $parts));
    }

    public static function targetScopeLabel(?string $type, ?string $subType, ?string $targetScope): string
    {
        if (! static::isGem($type)) {
            return '';
        }

        if ($subType === 'skill') {
            return GemEffectRegistry::skillTargetLabel($targetScope);
        }

        return '全局';
    }

    private static function normalizeUseEffectPayloadOrFail(array $effectForm): array
    {
        $templateCode = trim((string) ($effectForm['use_effect_template_code'] ?? ''));
        $template = static::useEffectTemplates()[$templateCode] ?? null;
        if ($template === null) {
            throw ValidationException::withMessages([
                'effect_form.use_effect_template_code' => '请选择使用效果模板。',
            ]);
        }

        $params = [];
        $errors = [];
        foreach (($template['params'] ?? []) as $paramKey => $definition) {
            $rawValue = $effectForm[$paramKey] ?? null;
            $type = (string) ($definition['type'] ?? 'number');

            if (in_array($type, ['item_select', 'dungeon_select'], true)) {
                $value = trim((string) $rawValue);
                if ($value === '') {
                    $errors["effect_form.{$paramKey}"] = sprintf('%s不能为空。', (string) $definition['label']);
                    continue;
                }

                $options = $type === 'item_select' ? AdminOptions::itemOptions() : static::dungeonOptions();
                if (! array_key_exists($value, $options)) {
                    $errors["effect_form.{$paramKey}"] = sprintf('%s无效。', (string) $definition['label']);
                    continue;
                }

                $params[$paramKey] = $value;

                continue;
            }

            if (! is_numeric($rawValue)) {
                $errors["effect_form.{$paramKey}"] = sprintf('%s必须填写数字。', (string) $definition['label']);
                continue;
            }

            $normalized = static::normalizeScalar($rawValue, $type);
            $min = $definition['min'] ?? null;
            if ($min !== null && $normalized < $min) {
                $errors["effect_form.{$paramKey}"] = sprintf('%s不能小于%s。', (string) $definition['label'], GemEffectRegistry::formatNumber($min));
                continue;
            }

            $params[$paramKey] = $normalized;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'effect_code' => $templateCode,
            'params' => $params,
        ];
    }

    private static function displayParamValue(string $paramKey, mixed $value, string $type): string
    {
        return match ($type) {
            'item_select' => AdminOptions::itemName((string) $value),
            'dungeon_select' => (string) (static::dungeonOptions()[(string) $value] ?? $value),
            default => GemEffectRegistry::formatNumber($value),
        };
    }

    private static function normalizeScalar(mixed $value, string $type): int|float
    {
        if ($type === 'integer') {
            return (int) round((float) $value);
        }

        $number = (float) $value;

        return abs($number - floor($number)) < 0.000001 ? (int) round($number) : $number;
    }
}
