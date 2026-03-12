<?php

namespace App\Support;

use App\Models\SkillCatalog;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class GemEffectRegistry
{
    public const ATTR_EFFECT_CODE = 'add_attr';

    public static function effectTypeForGemType(?string $gemType): string
    {
        return $gemType === 'skill' ? 'skill_modifier' : 'stat';
    }

    public static function defaultTargetScope(?string $gemType): ?string
    {
        return $gemType === 'skill' ? null : 'global';
    }

    public static function defaultFormState(?string $gemType = null): array
    {
        $state = [
            'stat' => null,
            'value' => null,
            'value_type' => 'flat',
            'effect_template_code' => null,
        ];

        foreach (static::skillParamDefinitions() as $paramKey => $definition) {
            $state[$paramKey] = $definition['default'] ?? null;
        }

        if ($gemType === 'attr') {
            $state['effect_template_code'] = null;
        }

        return $state;
    }

    public static function pureStatOptions(): array
    {
        return Collection::make(AdminOptions::statOptions())
            ->mapWithKeys(fn (string $label, string $stat): array => [$stat => static::pureLabel($label)])
            ->all();
    }

    public static function skillTargetOptions(): array
    {
        return SkillCatalog::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn (SkillCatalog $skill): array => [$skill->id => (string) $skill->name])
            ->all();
    }

    public static function skillTargetLabel(?string $skillId): string
    {
        if (blank($skillId)) {
            return '';
        }

        $options = static::skillTargetOptions();

        return (string) ($options[$skillId] ?? $skillId);
    }

    public static function skillTemplates(): array
    {
        return [
            'damage_up' => [
                'label' => '技能伤害提升',
                'params' => [
                    'damage_multiplier' => ['label' => '伤害倍率', 'type' => 'number', 'min' => 0, 'step' => 0.0001],
                ],
            ],
            'range_up' => [
                'label' => '技能范围提升',
                'params' => [
                    'range_multiplier' => ['label' => '范围倍率', 'type' => 'number', 'min' => 0, 'step' => 0.0001],
                ],
            ],
            'crit_up' => [
                'label' => '技能暴击提升',
                'params' => [
                    'crit_rate_bonus' => ['label' => '暴击加成', 'type' => 'number', 'min' => 0, 'step' => 0.0001],
                ],
            ],
            'shield_up' => [
                'label' => '护盾效果提升',
                'params' => [
                    'shield_multiplier' => ['label' => '护盾倍率', 'type' => 'number', 'min' => 0, 'step' => 0.0001],
                ],
            ],
            'cooldown_down' => [
                'label' => '技能冷却缩减',
                'params' => [
                    'cooldown_reduction' => ['label' => '冷却缩减', 'type' => 'number', 'min' => 0, 'step' => 0.0001],
                ],
            ],
            'duration_up' => [
                'label' => '技能持续时间提升',
                'params' => [
                    'duration_multiplier' => ['label' => '持续时间倍率', 'type' => 'number', 'min' => 0, 'step' => 0.0001],
                ],
            ],
            'burn_up' => [
                'label' => '灼烧效果提升',
                'params' => [
                    'burn_multiplier' => ['label' => '灼烧倍率', 'type' => 'number', 'min' => 0, 'step' => 0.0001],
                ],
            ],
            'slow_up' => [
                'label' => '减速效果提升',
                'params' => [
                    'slow_multiplier' => ['label' => '减速倍率', 'type' => 'number', 'min' => 0, 'step' => 0.0001],
                ],
            ],
            'mana_cost_down' => [
                'label' => '技能消耗降低',
                'params' => [
                    'mana_cost_reduction' => ['label' => '消耗降低', 'type' => 'number', 'min' => 0, 'step' => 0.0001],
                ],
            ],
        ];
    }

    public static function skillTemplateOptions(): array
    {
        return Collection::make(static::skillTemplates())
            ->mapWithKeys(fn (array $definition, string $code): array => [$code => (string) $definition['label']])
            ->all();
    }

    public static function skillTemplateLabel(?string $code): string
    {
        if (blank($code)) {
            return '';
        }

        return (string) (static::skillTemplateOptions()[$code] ?? $code);
    }

    public static function skillParamDefinitions(): array
    {
        $params = [];

        foreach (static::skillTemplates() as $template) {
            foreach (($template['params'] ?? []) as $paramKey => $definition) {
                $params[$paramKey] = $definition;
            }
        }

        return $params;
    }

    public static function formStateFromPayload(?string $gemType, mixed $payload): array
    {
        $state = static::defaultFormState($gemType);

        if (! is_array($payload) || $payload === []) {
            return $state;
        }

        $effectCode = trim((string) ($payload['effect_code'] ?? ''));
        $params = $payload['params'] ?? [];
        if (! is_array($params)) {
            $params = [];
        }

        if ($gemType === 'skill') {
            $state['effect_template_code'] = $effectCode !== '' ? $effectCode : null;
            foreach (static::skillParamDefinitions() as $paramKey => $definition) {
                $state[$paramKey] = $params[$paramKey] ?? ($definition['default'] ?? null);
            }

            return $state;
        }

        $state['stat'] = filled($params['stat'] ?? null) ? (string) $params['stat'] : null;
        $state['value'] = $params['value'] ?? null;
        $state['value_type'] = filled($params['value_type'] ?? null)
            ? (string) $params['value_type']
            : static::inferValueType((string) ($params['stat'] ?? ''));

        return $state;
    }

    public static function normalizeRecordDataOrFail(array $data): array
    {
        if (($data['type'] ?? null) !== 'gem') {
            unset($data['effect_form']);

            return $data;
        }

        $gemType = trim((string) ($data['sub_type'] ?? 'attr'));
        $effectForm = $data['effect_form'] ?? [];
        if (! is_array($effectForm)) {
            $effectForm = [];
        }

        if ($gemType === 'skill') {
            $data['effect_type'] = static::effectTypeForGemType('skill');
            $data['target_scope'] = trim((string) ($data['target_scope'] ?? ''));
            $data['effect_payload'] = static::normalizeSkillPayloadOrFail($data['target_scope'], $effectForm);
        } else {
            $data['sub_type'] = 'attr';
            $data['effect_type'] = static::effectTypeForGemType('attr');
            $data['target_scope'] = 'global';
            $data['effect_payload'] = static::normalizeAttrPayloadOrFail($effectForm);
        }

        unset($data['effect_form']);

        return $data;
    }

    public static function populateRecordFormData(array $data): array
    {
        $gemType = trim((string) ($data['sub_type'] ?? 'attr'));

        $data['effect_form'] = static::formStateFromPayload($gemType, $data['effect_payload'] ?? null);
        $data['effect_type'] = static::effectTypeForGemType($gemType);

        if ($gemType !== 'skill') {
            $data['target_scope'] = 'global';
        }

        return $data;
    }

    public static function normalizeLegacyPayloadOrFail(array $row): array
    {
        $gemType = trim((string) ($row['sub_type'] ?? 'attr'));
        $effectType = trim((string) ($row['effect_type'] ?? static::effectTypeForGemType($gemType)));
        $targetScope = trim((string) ($row['target_scope'] ?? ''));
        $payload = $row['effect_payload'] ?? null;
        $legacy = $row['gem_effect'] ?? null;

        if (is_array($payload) && isset($payload['effect_code'], $payload['params']) && is_array($payload['params'])) {
            return $payload;
        }

        $raw = is_array($payload) && $payload !== [] ? $payload : (is_array($legacy) ? $legacy : []);

        if ($effectType === 'skill_modifier' || $gemType === 'skill') {
            $templateCode = static::normalizeLegacyTemplateCode((string) ($raw['effect_code'] ?? $raw['modifier'] ?? ''));
            if ($templateCode === null) {
                throw new \RuntimeException(sprintf('宝石 %s 的技能效果模板无效。', (string) ($row['id'] ?? '')));
            }

            $template = static::skillTemplates()[$templateCode] ?? null;
            if ($template === null) {
                throw new \RuntimeException(sprintf('宝石 %s 的技能效果模板 %s 不存在。', (string) ($row['id'] ?? ''), $templateCode));
            }

            $skillId = trim((string) ($raw['skill_id'] ?? $targetScope));
            if ($skillId === '') {
                throw new \RuntimeException(sprintf('宝石 %s 缺少技能目标。', (string) ($row['id'] ?? '')));
            }

            $params = [];
            foreach (($template['params'] ?? []) as $paramKey => $definition) {
                $legacyValue = $raw[$paramKey] ?? $raw['value'] ?? $raw['val'] ?? null;
                if (! is_numeric($legacyValue)) {
                    throw new \RuntimeException(sprintf('宝石 %s 的技能参数 %s 无效。', (string) ($row['id'] ?? ''), $paramKey));
                }

                $params[$paramKey] = static::normalizeScalar($legacyValue, (string) ($definition['type'] ?? 'number'));
            }

            $row['target_scope'] = $skillId;

            return [
                'effect_code' => $templateCode,
                'params' => $params,
            ];
        }

        $stat = trim((string) ($raw['stat'] ?? ''));
        $value = $raw['value'] ?? $raw['val'] ?? null;
        if ($stat === '' || ! array_key_exists($stat, AdminOptions::statOptions()) || ! is_numeric($value)) {
            throw new \RuntimeException(sprintf('宝石 %s 的属性效果无效。', (string) ($row['id'] ?? '')));
        }

        return [
            'effect_code' => static::ATTR_EFFECT_CODE,
            'params' => [
                'stat' => $stat,
                'value' => static::normalizeScalar($value, 'number'),
                'value_type' => static::inferValueType($stat),
            ],
        ];
    }

    public static function effectSummary(?array $payload, ?string $targetScope = null): string
    {
        if (! is_array($payload) || $payload === []) {
            return '';
        }

        $effectCode = trim((string) ($payload['effect_code'] ?? ''));
        $params = $payload['params'] ?? [];
        if (! is_array($params)) {
            $params = [];
        }

        if ($effectCode === static::ATTR_EFFECT_CODE) {
            $stat = trim((string) ($params['stat'] ?? ''));
            $value = $params['value'] ?? null;
            if ($stat === '' || ! is_numeric($value)) {
                return '';
            }

            $label = static::pureStatOptions()[$stat] ?? $stat;
            $valueType = trim((string) ($params['value_type'] ?? static::inferValueType($stat)));

            return sprintf('%s +%s%s', $label, static::formatNumber($value), $valueType === 'percent' ? '%' : '');
        }

        $template = static::skillTemplates()[$effectCode] ?? null;
        if ($template === null) {
            return '';
        }

        $parts = [];
        foreach (($template['params'] ?? []) as $paramKey => $definition) {
            $value = $params[$paramKey] ?? null;
            if (! is_numeric($value)) {
                continue;
            }

            $parts[] = sprintf('%s %s', (string) $definition['label'], static::formatNumber($value));
        }

        $summary = (string) $template['label'];
        if (filled($targetScope)) {
            $summary .= '·' . static::skillTargetLabel($targetScope);
        }
        if ($parts !== []) {
            $summary .= '（' . implode('，', $parts) . '）';
        }

        return $summary;
    }

    public static function formatNumber(mixed $value): string
    {
        $number = (float) $value;
        if (abs($number - floor($number)) < 0.000001) {
            return (string) (int) round($number);
        }

        return rtrim(rtrim(number_format($number, 4, '.', ''), '0'), '.');
    }

    private static function normalizeAttrPayloadOrFail(array $effectForm): array
    {
        $stat = trim((string) ($effectForm['stat'] ?? ''));
        if ($stat === '' || ! array_key_exists($stat, AdminOptions::statOptions())) {
            throw ValidationException::withMessages([
                'effect_form.stat' => '请选择属性。',
            ]);
        }

        $value = $effectForm['value'] ?? null;
        if (! is_numeric($value)) {
            throw ValidationException::withMessages([
                'effect_form.value' => '属性数值必须填写数字。',
            ]);
        }

        $valueType = trim((string) ($effectForm['value_type'] ?? 'flat'));
        if (! array_key_exists($valueType, AdminOptions::valueModeOptions())) {
            throw ValidationException::withMessages([
                'effect_form.value_type' => '数值类型不正确。',
            ]);
        }

        return [
            'effect_code' => static::ATTR_EFFECT_CODE,
            'params' => [
                'stat' => $stat,
                'value' => static::normalizeScalar($value, 'number'),
                'value_type' => $valueType,
            ],
        ];
    }

    private static function normalizeSkillPayloadOrFail(?string $targetScope, array $effectForm): array
    {
        $skillId = trim((string) $targetScope);
        if ($skillId === '') {
            throw ValidationException::withMessages([
                'target_scope' => '技能宝石必须选择作用技能。',
            ]);
        }

        $templateCode = trim((string) ($effectForm['effect_template_code'] ?? ''));
        $template = static::skillTemplates()[$templateCode] ?? null;
        if ($template === null) {
            throw ValidationException::withMessages([
                'effect_form.effect_template_code' => '请选择效果模板。',
            ]);
        }

        $params = [];
        $errors = [];
        foreach (($template['params'] ?? []) as $paramKey => $definition) {
            $rawValue = $effectForm[$paramKey] ?? null;
            if (! is_numeric($rawValue)) {
                $errors["effect_form.{$paramKey}"] = sprintf('%s必须填写数字。', (string) $definition['label']);
                continue;
            }

            $normalized = static::normalizeScalar($rawValue, (string) ($definition['type'] ?? 'number'));
            $min = $definition['min'] ?? null;
            if ($min !== null && $normalized < $min) {
                $errors["effect_form.{$paramKey}"] = sprintf('%s不能小于%s。', (string) $definition['label'], static::formatNumber($min));
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

    private static function normalizeScalar(mixed $value, string $type): int|float
    {
        if ($type === 'integer') {
            return (int) round((float) $value);
        }

        $number = (float) $value;

        return abs($number - floor($number)) < 0.000001 ? (int) round($number) : $number;
    }

    private static function inferValueType(string $stat): string
    {
        return in_array($stat, ['CRIT_RATE', 'CRIT_DMG', 'CRIT_PERCENT', 'FINAL_DAMAGE', 'FINAL_REDUCTION', 'CDR', 'LOOT_BONUS_PERCENT'], true)
            ? 'percent'
            : 'flat';
    }

    private static function normalizeLegacyTemplateCode(string $legacyCode): ?string
    {
        $legacyCode = trim($legacyCode);

        return match ($legacyCode) {
            'damage_coef' => 'damage_up',
            'range' => 'range_up',
            '' => null,
            default => array_key_exists($legacyCode, static::skillTemplates()) ? $legacyCode : null,
        };
    }

    private static function pureLabel(string $label): string
    {
        $label = preg_replace('/（[^）]*）/u', '', $label);
        $label = preg_replace('/\\([^)]*\\)/', '', (string) $label);

        return trim((string) $label);
    }
}
