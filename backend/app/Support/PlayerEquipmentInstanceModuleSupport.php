<?php

namespace App\Support;

use App\Models\Item;
use App\Models\PlayerEquipmentGemSlot;
use App\Models\PlayerEquipmentInstance;
use App\Models\PlayerEquipmentLoadout;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class PlayerEquipmentInstanceModuleSupport
{
    public const VERSION = 'v1';

    public const MODULE = 'player_equipment_instance_examples';

    /**
     * @var array<string, Item|null>
     */
    private static array $itemCache = [];

    /**
     * @return array<string, mixed>
     */
    public static function rulesPayload(): array
    {
        return [
            'loadout_slot_types' => array_keys(PlayerEquipmentInstance::SLOT_TYPE_OPTIONS),
            'set_count_excludes' => PlayerEquipmentInstance::SET_COUNT_EXCLUDED_SLOT_TYPES,
            'talisman_star_link_excludes' => PlayerEquipmentInstance::TALISMAN_STAR_LINK_EXCLUDED_SLOT_TYPES,
            'gem_slot_unlocks' => self::exportGemSlotUnlockRules(),
        ];
    }

    /**
     * @return array{rules: array<string,mixed>, instances: array<int, array<string, mixed>>, loadouts: array<int, array<string, mixed>>, gem_slots: array<int, array<string, mixed>>, progression_examples: array<int, array<string, mixed>>}
     */
    public static function normalizeProjectPayloadOrFail(array $decoded): array
    {
        $errors = [];

        $version = trim((string) ($decoded['version'] ?? ''));
        if ($version !== self::VERSION) {
            $errors['version'] = sprintf('version 必须为 %s。', self::VERSION);
        }

        $module = trim((string) ($decoded['module'] ?? ''));
        if ($module !== self::MODULE) {
            $errors['module'] = sprintf('module 必须为 %s。', self::MODULE);
        }

        $rules = $decoded['rules'] ?? null;
        if (! is_array($rules)) {
            $errors['rules'] = 'rules 节点缺失。';
        }

        $exampleInstances = $decoded['example_instances'] ?? null;
        if (! is_array($exampleInstances)) {
            $errors['example_instances'] = 'example_instances 节点缺失。';
        }

        $exampleLoadouts = $decoded['example_loadouts'] ?? null;
        if (! is_array($exampleLoadouts)) {
            $errors['example_loadouts'] = 'example_loadouts 节点缺失。';
        }

        $exampleGemSlots = $decoded['example_gem_slots'] ?? null;
        if (! is_array($exampleGemSlots)) {
            $errors['example_gem_slots'] = 'example_gem_slots 节点缺失。';
        }

        $progressionExamples = $decoded['progression_examples'] ?? [];
        if (! is_array($progressionExamples)) {
            $errors['progression_examples'] = 'progression_examples 必须为数组。';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $normalizedRules = self::normalizeRulesOrFail($rules);
        $normalizedInstances = self::normalizeInstanceRowsOrFail(array_values($exampleInstances), 'example_instances');

        $instancesById = [];
        foreach ($normalizedInstances as $row) {
            $instancesById[(string) $row['instance_id']] = $row;
        }

        return [
            'rules' => $normalizedRules,
            'instances' => $normalizedInstances,
            'loadouts' => self::normalizeLoadoutRowsOrFail(array_values($exampleLoadouts), $instancesById, 'example_loadouts'),
            'gem_slots' => self::normalizeGemSlotRowsOrFail(array_values($exampleGemSlots), $instancesById, 'example_gem_slots'),
            'progression_examples' => self::normalizeProgressionExamplesOrFail(array_values($progressionExamples), 'progression_examples'),
        ];
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    public static function normalizeRulesOrFail(array $rules): array
    {
        $errors = [];
        $expected = self::rulesPayload();

        $loadoutSlots = isset($rules['loadout_slot_types']) && is_array($rules['loadout_slot_types'])
            ? array_values(array_map(static fn (mixed $slot): string => trim((string) $slot), $rules['loadout_slot_types']))
            : null;
        if ($loadoutSlots !== $expected['loadout_slot_types']) {
            $errors['rules.loadout_slot_types'] = 'loadout_slot_types 必须严格等于固定穿戴位列表。';
        }

        $setCountExcludes = isset($rules['set_count_excludes']) && is_array($rules['set_count_excludes'])
            ? array_values(array_map(static fn (mixed $slot): string => trim((string) $slot), $rules['set_count_excludes']))
            : null;
        if ($setCountExcludes !== $expected['set_count_excludes']) {
            $errors['rules.set_count_excludes'] = 'set_count_excludes 必须固定为 ["talisman"]。';
        }

        $starLinkExcludes = isset($rules['talisman_star_link_excludes']) && is_array($rules['talisman_star_link_excludes'])
            ? array_values(array_map(static fn (mixed $slot): string => trim((string) $slot), $rules['talisman_star_link_excludes']))
            : null;
        if ($starLinkExcludes !== $expected['talisman_star_link_excludes']) {
            $errors['rules.talisman_star_link_excludes'] = 'talisman_star_link_excludes 必须固定为 ["talisman"]。';
        }

        $gemSlotUnlocks = $rules['gem_slot_unlocks'] ?? null;
        if (! is_array($gemSlotUnlocks)) {
            $errors['rules.gem_slot_unlocks'] = 'gem_slot_unlocks 节点缺失。';
        } else {
            $normalizedGemSlotUnlocks = self::normalizeGemSlotUnlockRulesOrFail(array_values($gemSlotUnlocks), 'rules.gem_slot_unlocks');
            if ($normalizedGemSlotUnlocks !== $expected['gem_slot_unlocks']) {
                $errors['rules.gem_slot_unlocks'] = 'gem_slot_unlocks 必须严格遵循 3/6/8/10 与 attr_only/skill_only 固定映射。';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $expected;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{required_star:int,slot_index:int,slot_group:string}>
     */
    public static function normalizeGemSlotUnlockRulesOrFail(array $rows, string $pathPrefix = 'rules.gem_slot_unlocks'): array
    {
        $errors = [];
        $normalized = [];
        $seenSlotIndexes = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                $errors["{$pathPrefix}.{$index}"] = 'gem_slot_unlocks 条目格式错误。';
                continue;
            }

            $slotIndex = (int) ($row['slot_index'] ?? 0);
            $requiredStar = (int) ($row['required_star'] ?? 0);
            $slotGroup = trim((string) ($row['slot_group'] ?? ''));

            $expected = PlayerEquipmentGemSlot::SLOT_RULE_MAP[$slotIndex] ?? null;
            if (! is_array($expected)) {
                $errors["{$pathPrefix}.{$index}.slot_index"] = 'slot_index 只能是 1 / 2 / 3 / 4。';
                continue;
            }

            if (isset($seenSlotIndexes[$slotIndex])) {
                $errors["{$pathPrefix}.{$index}.slot_index"] = sprintf('slot_index=%d 重复。', $slotIndex);
                continue;
            }

            $seenSlotIndexes[$slotIndex] = true;

            if ($requiredStar !== (int) $expected['required_star']) {
                $errors["{$pathPrefix}.{$index}.required_star"] = sprintf(
                    'slot_index=%d 时 required_star 必须为 %d。',
                    $slotIndex,
                    $expected['required_star'],
                );
            }

            if ($slotGroup !== (string) $expected['slot_group']) {
                $errors["{$pathPrefix}.{$index}.slot_group"] = sprintf(
                    'slot_index=%d 时 slot_group 必须为 %s。',
                    $slotIndex,
                    $expected['slot_group'],
                );
            }

            $normalized[] = [
                'required_star' => $requiredStar,
                'slot_index' => $slotIndex,
                'slot_group' => $slotGroup,
            ];
        }

        $expectedSlotIndexes = array_keys(PlayerEquipmentGemSlot::SLOT_RULE_MAP);
        sort($expectedSlotIndexes);
        $actualSlotIndexes = array_keys($seenSlotIndexes);
        sort($actualSlotIndexes);

        if ($actualSlotIndexes !== $expectedSlotIndexes) {
            $errors[$pathPrefix] = 'gem_slot_unlocks 必须完整配置 1/2/3/4 四个固定孔位。';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        usort($normalized, static fn (array $left, array $right): int => $left['slot_index'] <=> $right['slot_index']);

        return array_values($normalized);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeInstanceRowsOrFail(array $rows, string $pathPrefix = 'player_equipment_instances'): array
    {
        $errors = [];
        $normalized = [];
        $seenInstanceIds = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                $errors["{$pathPrefix}.{$index}"] = '装备实例条目格式错误。';
                continue;
            }

            try {
                $normalizedRow = self::normalizeInstanceRowOrFail($row, "{$pathPrefix}.{$index}");
            } catch (ValidationException $e) {
                $errors = array_merge($errors, $e->errors());
                continue;
            }

            $instanceId = (string) $normalizedRow['instance_id'];
            if (isset($seenInstanceIds[$instanceId])) {
                $errors["{$pathPrefix}.{$index}.instance_id"] = sprintf('instance_id 重复：%s。', $instanceId);
                continue;
            }

            $seenInstanceIds[$instanceId] = true;
            $normalized[] = $normalizedRow;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return array_values($normalized);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function normalizeInstanceRowOrFail(array $row, string $pathPrefix = 'player_equipment_instance'): array
    {
        $errors = [];

        $playerId = (int) ($row['player_id'] ?? 0);
        $instanceId = trim((string) ($row['instance_id'] ?? ''));
        $itemId = trim((string) ($row['item_id'] ?? ''));
        $equipmentSourceType = trim((string) ($row['equipment_source_type'] ?? ''));
        $slotType = trim((string) ($row['slot_type'] ?? ''));
        $setId = self::nullableString($row['set_id'] ?? null);
        $setLevel = self::nullableInt($row['set_level'] ?? null);
        $star = (int) ($row['star'] ?? 0);
        $maxStar = (int) ($row['max_star'] ?? 0);
        $quality = trim((string) ($row['quality'] ?? ''));
        $rarity = trim((string) ($row['rarity'] ?? ''));
        $isLocked = (bool) ($row['is_locked'] ?? false);
        $isEquipped = (bool) ($row['is_equipped'] ?? false);

        try {
            $obtainedAt = self::nullableDateTimeString($row['obtained_at'] ?? null);
        } catch (ValidationException $e) {
            $errors = array_merge($errors, $e->errors());
            $obtainedAt = null;
        }

        if ($playerId < 1) {
            $errors["{$pathPrefix}.player_id"] = 'player_id 必须为正整数。';
        }

        if ($instanceId === '') {
            $errors["{$pathPrefix}.instance_id"] = 'instance_id 不能为空。';
        }

        if ($itemId === '') {
            $errors["{$pathPrefix}.item_id"] = 'item_id 不能为空。';
        }

        if (! array_key_exists($equipmentSourceType, PlayerEquipmentInstance::SOURCE_TYPE_OPTIONS)) {
            $errors["{$pathPrefix}.equipment_source_type"] = 'equipment_source_type 只允许 set_equipment / blue_equipment / common_equipment。';
        }

        if (! array_key_exists($slotType, PlayerEquipmentInstance::SLOT_TYPE_OPTIONS)) {
            $errors["{$pathPrefix}.slot_type"] = 'slot_type 非法，必须使用固定穿戴位。';
        }

        if ($star < 0) {
            $errors["{$pathPrefix}.star"] = 'star 必须 >= 0。';
        }

        if ($maxStar < 0) {
            $errors["{$pathPrefix}.max_star"] = 'max_star 必须 >= 0。';
        }

        if ($star > $maxStar) {
            $errors["{$pathPrefix}.star"] = 'star 不能大于 max_star。';
        }

        if ($equipmentSourceType === 'set_equipment') {
            if ($setId === null) {
                $errors["{$pathPrefix}.set_id"] = '套装装备必须填写 set_id。';
            }

            if ($setLevel === null || ! array_key_exists($setLevel, PlayerEquipmentInstance::MAX_STAR_BY_SET_LEVEL)) {
                $errors["{$pathPrefix}.set_level"] = '套装装备 set_level 只允许 20 / 40 / 50 / 60。';
            } elseif ($maxStar !== PlayerEquipmentInstance::MAX_STAR_BY_SET_LEVEL[$setLevel]) {
                $errors["{$pathPrefix}.max_star"] = sprintf(
                    'set_level=%d 时 max_star 必须为 %d。',
                    $setLevel,
                    PlayerEquipmentInstance::MAX_STAR_BY_SET_LEVEL[$setLevel],
                );
            }
        } else {
            if ($setId !== null) {
                $errors["{$pathPrefix}.set_id"] = '非套装装备 set_id 必须为空。';
            }

            if ($setLevel !== null) {
                $errors["{$pathPrefix}.set_level"] = '非套装装备 set_level 必须为空。';
            }
        }

        if ($quality === '') {
            $errors["{$pathPrefix}.quality"] = 'quality 不能为空。';
        }

        if ($rarity === '') {
            $errors["{$pathPrefix}.rarity"] = 'rarity 不能为空。';
        }

        $item = null;
        if ($itemId !== '') {
            $item = self::itemByItemId($itemId);
            if (! $item instanceof Item) {
                $errors["{$pathPrefix}.item_id"] = sprintf('item_id 不存在：%s。', $itemId);
            }
        }

        if ($item instanceof Item) {
            if ($quality !== '' && (string) $item->quality !== $quality) {
                $errors["{$pathPrefix}.quality"] = sprintf('quality 必须继承 item.%s。', (string) $item->quality);
            }

            if ($rarity !== '' && (string) $item->rarity !== $rarity) {
                $errors["{$pathPrefix}.rarity"] = sprintf('rarity 必须继承 item.%s。', (string) $item->rarity);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'player_id' => $playerId,
            'instance_id' => $instanceId,
            'item_id' => $itemId,
            'equipment_source_type' => $equipmentSourceType,
            'slot_type' => $slotType,
            'set_id' => $setId,
            'set_level' => $setLevel,
            'star' => $star,
            'max_star' => $maxStar,
            'quality' => $quality,
            'rarity' => $rarity,
            'is_locked' => $isLocked,
            'is_equipped' => $isEquipped,
            'obtained_at' => $obtainedAt,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, array<string, mixed>>|null  $knownInstancesById
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeLoadoutRowsOrFail(array $rows, ?array $knownInstancesById = null, string $pathPrefix = 'player_equipment_loadouts'): array
    {
        $errors = [];
        $normalized = [];
        $seenPlayerSlot = [];
        $seenInstances = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                $errors["{$pathPrefix}.{$index}"] = '穿戴映射条目格式错误。';
                continue;
            }

            try {
                $normalizedRow = self::normalizeLoadoutRowOrFail($row, "{$pathPrefix}.{$index}", $knownInstancesById);
            } catch (ValidationException $e) {
                $errors = array_merge($errors, $e->errors());
                continue;
            }

            $playerSlotKey = sprintf('%d|%s', $normalizedRow['player_id'], $normalizedRow['slot_type']);
            if (isset($seenPlayerSlot[$playerSlotKey])) {
                $errors["{$pathPrefix}.{$index}.slot_type"] = '同一 player_id + slot_type 不允许重复。';
            }

            $instanceId = (string) $normalizedRow['instance_id'];
            if (isset($seenInstances[$instanceId])) {
                $errors["{$pathPrefix}.{$index}.instance_id"] = '同一 instance_id 不允许重复占位。';
            }

            $seenPlayerSlot[$playerSlotKey] = true;
            $seenInstances[$instanceId] = true;
            $normalized[] = $normalizedRow;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return array_values($normalized);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, array<string, mixed>>|null  $knownInstancesById
     * @return array<string, mixed>
     */
    public static function normalizeLoadoutRowOrFail(
        array $row,
        string $pathPrefix = 'player_equipment_loadout',
        ?array $knownInstancesById = null,
        ?int $ignoreRecordId = null,
    ): array {
        $errors = [];

        $playerId = (int) ($row['player_id'] ?? 0);
        $slotType = trim((string) ($row['slot_type'] ?? ''));
        $instanceId = trim((string) ($row['instance_id'] ?? ''));

        if ($playerId < 1) {
            $errors["{$pathPrefix}.player_id"] = 'player_id 必须为正整数。';
        }

        if (! array_key_exists($slotType, PlayerEquipmentLoadout::SLOT_TYPE_OPTIONS)) {
            $errors["{$pathPrefix}.slot_type"] = 'slot_type 非法，必须使用固定穿戴位。';
        }

        if ($instanceId === '') {
            $errors["{$pathPrefix}.instance_id"] = 'instance_id 不能为空。';
        }

        $instance = null;
        if ($instanceId !== '') {
            $instance = self::resolveInstance($instanceId, $knownInstancesById);
            if ($instance === null) {
                $errors["{$pathPrefix}.instance_id"] = sprintf('instance_id 不存在：%s。', $instanceId);
            }
        }

        if (is_array($instance)) {
            $instancePlayerId = (int) ($instance['player_id'] ?? 0);
            $instanceSlotType = (string) ($instance['slot_type'] ?? '');

            if ($instancePlayerId !== $playerId) {
                $errors["{$pathPrefix}.player_id"] = 'instance_id 必须属于同一个 player_id。';
            }

            if ($slotType !== '' && $instanceSlotType !== '' && $instanceSlotType !== $slotType) {
                $errors["{$pathPrefix}.slot_type"] = 'loadout.slot_type 必须与实例 slot_type 一致。';
            }
        }

        if ($knownInstancesById === null && $playerId > 0 && $slotType !== '') {
            $slotOccupied = PlayerEquipmentLoadout::query()
                ->where('player_id', $playerId)
                ->where('slot_type', $slotType)
                ->when($ignoreRecordId !== null, static fn ($query) => $query->where('id', '!=', $ignoreRecordId))
                ->exists();

            if ($slotOccupied) {
                $errors["{$pathPrefix}.slot_type"] = '同一 player_id + slot_type 不允许重复。';
            }
        }

        if ($knownInstancesById === null && $instanceId !== '') {
            $instanceOccupied = PlayerEquipmentLoadout::query()
                ->where('instance_id', $instanceId)
                ->when($ignoreRecordId !== null, static fn ($query) => $query->where('id', '!=', $ignoreRecordId))
                ->exists();

            if ($instanceOccupied) {
                $errors["{$pathPrefix}.instance_id"] = '同一 instance_id 不允许重复占位。';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'player_id' => $playerId,
            'slot_type' => $slotType,
            'instance_id' => $instanceId,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, array<string, mixed>>|null  $knownInstancesById
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeGemSlotRowsOrFail(array $rows, ?array $knownInstancesById = null, string $pathPrefix = 'player_equipment_gem_slots'): array
    {
        $errors = [];
        $normalized = [];
        $seenInstanceSlots = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                $errors["{$pathPrefix}.{$index}"] = '宝石孔实例条目格式错误。';
                continue;
            }

            try {
                $normalizedRow = self::normalizeGemSlotRowOrFail($row, "{$pathPrefix}.{$index}", $knownInstancesById);
            } catch (ValidationException $e) {
                $errors = array_merge($errors, $e->errors());
                continue;
            }

            $key = sprintf('%s|%d', $normalizedRow['instance_id'], $normalizedRow['slot_index']);
            if (isset($seenInstanceSlots[$key])) {
                $errors["{$pathPrefix}.{$index}.slot_index"] = '同一 instance_id + slot_index 不允许重复。';
            }

            $seenInstanceSlots[$key] = true;
            $normalized[] = $normalizedRow;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return array_values($normalized);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, array<string, mixed>>|null  $knownInstancesById
     * @return array<string, mixed>
     */
    public static function normalizeGemSlotRowOrFail(
        array $row,
        string $pathPrefix = 'player_equipment_gem_slot',
        ?array $knownInstancesById = null,
        ?int $ignoreRecordId = null,
    ): array {
        $errors = [];

        $instanceId = trim((string) ($row['instance_id'] ?? ''));
        $slotIndex = (int) ($row['slot_index'] ?? 0);
        $slotGroup = trim((string) ($row['slot_group'] ?? ''));
        $requiredStar = (int) ($row['required_star'] ?? 0);
        $isUnlocked = (bool) ($row['is_unlocked'] ?? false);
        $gemItemId = self::nullableString($row['gem_item_id'] ?? null);

        if ($instanceId === '') {
            $errors["{$pathPrefix}.instance_id"] = 'instance_id 不能为空。';
        }

        $expected = PlayerEquipmentGemSlot::SLOT_RULE_MAP[$slotIndex] ?? null;
        if (! is_array($expected)) {
            $errors["{$pathPrefix}.slot_index"] = 'slot_index 只能是 1 / 2 / 3 / 4。';
        } else {
            if ($requiredStar !== (int) $expected['required_star']) {
                $errors["{$pathPrefix}.required_star"] = sprintf(
                    'slot_index=%d 时 required_star 必须为 %d。',
                    $slotIndex,
                    $expected['required_star'],
                );
            }

            if ($slotGroup !== (string) $expected['slot_group']) {
                $errors["{$pathPrefix}.slot_group"] = sprintf(
                    'slot_index=%d 时 slot_group 必须为 %s。',
                    $slotIndex,
                    $expected['slot_group'],
                );
            }
        }

        if (! array_key_exists($slotGroup, PlayerEquipmentGemSlot::SLOT_GROUP_OPTIONS)) {
            $errors["{$pathPrefix}.slot_group"] = 'slot_group 只能是 attr_only / skill_only。';
        }

        $instance = null;
        if ($instanceId !== '') {
            $instance = self::resolveInstance($instanceId, $knownInstancesById);
            if ($instance === null) {
                $errors["{$pathPrefix}.instance_id"] = sprintf('instance_id 不存在：%s。', $instanceId);
            }
        }

        if (is_array($instance) && $isUnlocked) {
            $instanceStar = (int) ($instance['star'] ?? 0);
            if ($instanceStar < $requiredStar) {
                $errors["{$pathPrefix}.is_unlocked"] = sprintf(
                    '实例当前 star=%d，未达到 required_star=%d，不能标记为已解锁。',
                    $instanceStar,
                    $requiredStar,
                );
            }
        }

        if (! $isUnlocked && $gemItemId !== null) {
            $errors["{$pathPrefix}.gem_item_id"] = '未解锁孔位不能镶嵌宝石，gem_item_id 必须为空。';
        }

        if ($gemItemId !== null) {
            $item = self::itemByItemId($gemItemId);
            if (! $item instanceof Item) {
                $errors["{$pathPrefix}.gem_item_id"] = sprintf('gem_item_id 不存在：%s。', $gemItemId);
            } else {
                if ((string) $item->main_type !== 'gem') {
                    $errors["{$pathPrefix}.gem_item_id"] = 'gem_item_id 必须引用宝石 item。';
                }

                $requiredGemSubType = $slotGroup === 'attr_only' ? 'attr_gem' : 'skill_gem';
                if ((string) $item->sub_type !== $requiredGemSubType) {
                    $errors["{$pathPrefix}.gem_item_id"] = sprintf(
                        '%s 孔位只能镶嵌 %s。',
                        $slotGroup,
                        $requiredGemSubType,
                    );
                }
            }
        }

        if ($knownInstancesById === null && $instanceId !== '' && $slotIndex > 0) {
            $occupied = PlayerEquipmentGemSlot::query()
                ->where('instance_id', $instanceId)
                ->where('slot_index', $slotIndex)
                ->when($ignoreRecordId !== null, static fn ($query) => $query->where('id', '!=', $ignoreRecordId))
                ->exists();

            if ($occupied) {
                $errors["{$pathPrefix}.slot_index"] = '同一 instance_id + slot_index 不允许重复。';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'instance_id' => $instanceId,
            'slot_index' => $slotIndex,
            'slot_group' => $slotGroup,
            'required_star' => $requiredStar,
            'is_unlocked' => $isUnlocked,
            'gem_item_id' => $gemItemId,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeProgressionExamplesOrFail(array $rows, string $pathPrefix = 'progression_examples'): array
    {
        $errors = [];
        $normalized = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                $errors["{$pathPrefix}.{$index}"] = 'progression_examples 条目格式错误。';
                continue;
            }

            $before = is_array($row['before'] ?? null) ? $row['before'] : null;
            $after = is_array($row['after'] ?? null) ? $row['after'] : null;

            if ($before === null || $after === null) {
                $errors["{$pathPrefix}.{$index}"] = 'progression_examples 必须包含 before / after。';
                continue;
            }

            $beforeInstanceId = trim((string) ($before['instance_id'] ?? ''));
            $afterInstanceId = trim((string) ($after['instance_id'] ?? ''));
            $beforeItemId = trim((string) ($before['item_id'] ?? ''));
            $afterItemId = trim((string) ($after['item_id'] ?? ''));
            $beforeSetLevel = (int) ($before['set_level'] ?? 0);
            $afterSetLevel = (int) ($after['set_level'] ?? 0);
            $beforeStar = (int) ($before['star'] ?? -1);
            $afterStar = (int) ($after['star'] ?? -1);
            $beforeMaxStar = (int) ($before['max_star'] ?? -1);
            $afterMaxStar = (int) ($after['max_star'] ?? -1);

            if ($beforeInstanceId === '' || $afterInstanceId === '') {
                $errors["{$pathPrefix}.{$index}.instance_id"] = 'before/after.instance_id 不能为空。';
            } elseif ($beforeInstanceId !== $afterInstanceId) {
                $errors["{$pathPrefix}.{$index}.instance_id"] = '进阶前后必须是同一个 instance_id。';
            }

            if ($beforeItemId === '' || $afterItemId === '') {
                $errors["{$pathPrefix}.{$index}.item_id"] = 'before/after.item_id 不能为空。';
            }

            $nextSetLevel = self::nextSetLevel($beforeSetLevel);
            if ($nextSetLevel === null) {
                $errors["{$pathPrefix}.{$index}.before.set_level"] = 'before.set_level 必须为 20 / 40 / 50 且可进阶。';
            } elseif ($afterSetLevel !== $nextSetLevel) {
                $errors["{$pathPrefix}.{$index}.after.set_level"] = sprintf(
                    'set_level 进阶必须是 %d -> %d。',
                    $beforeSetLevel,
                    $nextSetLevel,
                );
            }

            if ($beforeStar !== $afterStar) {
                $errors["{$pathPrefix}.{$index}.after.star"] = '进阶后 star 必须保持不变。';
            }

            $expectedBeforeMax = PlayerEquipmentInstance::MAX_STAR_BY_SET_LEVEL[$beforeSetLevel] ?? null;
            if ($expectedBeforeMax === null || $beforeMaxStar !== $expectedBeforeMax) {
                $errors["{$pathPrefix}.{$index}.before.max_star"] = 'before.max_star 与 before.set_level 不匹配。';
            }

            $expectedAfterMax = PlayerEquipmentInstance::MAX_STAR_BY_SET_LEVEL[$afterSetLevel] ?? null;
            if ($expectedAfterMax === null || $afterMaxStar !== $expectedAfterMax) {
                $errors["{$pathPrefix}.{$index}.after.max_star"] = 'after.max_star 与 after.set_level 不匹配。';
            }

            $normalized[] = [
                'before' => [
                    'instance_id' => $beforeInstanceId,
                    'item_id' => $beforeItemId,
                    'set_level' => $beforeSetLevel,
                    'star' => $beforeStar,
                    'max_star' => $beforeMaxStar,
                ],
                'after' => [
                    'instance_id' => $afterInstanceId,
                    'item_id' => $afterItemId,
                    'set_level' => $afterSetLevel,
                    'star' => $afterStar,
                    'max_star' => $afterMaxStar,
                ],
                'summary' => self::nullableString($row['summary'] ?? null),
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return array_values($normalized);
    }

    /**
     * 进阶时只更新 item_id / set_level / max_star，并保留当前 star。
     *
     * @param  array<string, mixed>  $instance
     * @return array{item_id:string,set_level:int,max_star:int,star:int}
     */
    public static function progressionUpdatePayloadOrFail(array $instance, string $nextItemId, int $nextSetLevel): array
    {
        $errors = [];

        $instanceSourceType = trim((string) ($instance['equipment_source_type'] ?? ''));
        $currentSetLevel = (int) ($instance['set_level'] ?? 0);
        $currentStar = (int) ($instance['star'] ?? 0);

        if ($instanceSourceType !== 'set_equipment') {
            $errors['instance.equipment_source_type'] = '仅套装装备支持进阶更新规则。';
        }

        $expectedNextSetLevel = self::nextSetLevel($currentSetLevel);
        if ($expectedNextSetLevel === null) {
            $errors['instance.set_level'] = '当前 set_level 不支持继续进阶。';
        } elseif ($nextSetLevel !== $expectedNextSetLevel) {
            $errors['next_set_level'] = sprintf('下一档 set_level 必须为 %d。', $expectedNextSetLevel);
        }

        $nextItemId = trim($nextItemId);
        if ($nextItemId === '') {
            $errors['next_item_id'] = 'next_item_id 不能为空。';
        } elseif (! self::itemByItemId($nextItemId) instanceof Item) {
            $errors['next_item_id'] = sprintf('next_item_id 不存在：%s。', $nextItemId);
        }

        $nextMaxStar = PlayerEquipmentInstance::MAX_STAR_BY_SET_LEVEL[$nextSetLevel] ?? null;
        if ($nextMaxStar === null) {
            $errors['next_set_level'] = 'next_set_level 只允许 40 / 50 / 60。';
        } elseif ($currentStar > $nextMaxStar) {
            $errors['instance.star'] = '当前 star 不能大于下一档 max_star。';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'item_id' => $nextItemId,
            'set_level' => $nextSetLevel,
            'max_star' => (int) $nextMaxStar,
            'star' => $currentStar,
        ];
    }

    /**
     * @return array<int, array{required_star:int,slot_index:int,slot_group:string}>
     */
    public static function exportGemSlotUnlockRules(): array
    {
        $rows = [];

        foreach (PlayerEquipmentGemSlot::SLOT_RULE_MAP as $slotIndex => $mapping) {
            $rows[] = [
                'required_star' => (int) $mapping['required_star'],
                'slot_index' => (int) $slotIndex,
                'slot_group' => (string) $mapping['slot_group'],
            ];
        }

        usort($rows, static fn (array $left, array $right): int => $left['slot_index'] <=> $right['slot_index']);

        return array_values($rows);
    }

    private static function nextSetLevel(int $setLevel): ?int
    {
        return match ($setLevel) {
            20 => 40,
            40 => 50,
            50 => 60,
            default => null,
        };
    }

    /**
     * @param  array<string, array<string, mixed>>|null  $knownInstancesById
     * @return array<string, mixed>|null
     */
    private static function resolveInstance(string $instanceId, ?array $knownInstancesById): ?array
    {
        if ($knownInstancesById !== null) {
            return $knownInstancesById[$instanceId] ?? null;
        }

        $instance = PlayerEquipmentInstance::query()->where('instance_id', $instanceId)->first();
        if (! $instance instanceof PlayerEquipmentInstance) {
            return null;
        }

        return [
            'player_id' => (int) $instance->player_id,
            'slot_type' => (string) $instance->slot_type,
            'star' => (int) $instance->star,
        ];
    }

    private static function itemByItemId(string $itemId): ?Item
    {
        if (array_key_exists($itemId, self::$itemCache)) {
            return self::$itemCache[$itemId];
        }

        $item = Item::query()->where('item_id', $itemId)->first();
        self::$itemCache[$itemId] = $item instanceof Item ? $item : null;

        return self::$itemCache[$itemId];
    }

    /**
     * @throws ValidationException
     */
    private static function nullableDateTimeString(mixed $value): ?string
    {
        $value = self::nullableString($value);
        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'obtained_at' => 'obtained_at 必须是有效时间。',
            ]);
        }
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }
}
