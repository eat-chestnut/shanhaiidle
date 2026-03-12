<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ShopPlayerProfile;
use App\Models\Stage;
use Illuminate\Support\Collection;

class PlayerProfileSyncService
{
    public function upsertSnapshot(string $playerId, array $payload): ShopPlayerProfile
    {
        $safePlayerId = trim($playerId);
        $profile = ShopPlayerProfile::query()->firstOrNew(['player_id' => $safePlayerId]);

        if (array_key_exists('nickname', $payload)) {
            $profile->nickname = $this->nullableString($payload['nickname'], 64);
        }

        $profile->level = max(1, (int) ($payload['level'] ?? $profile->level ?? 1));
        $profile->exp = max(0, (int) ($payload['exp'] ?? $profile->exp ?? 0));
        $profile->gold = max(0, (int) ($payload['gold'] ?? $profile->gold ?? 0));
        $profile->crystal = max(0, (int) ($payload['crystal'] ?? $profile->crystal ?? 0));
        $profile->contribution = max(0, (int) ($payload['contribution'] ?? $profile->contribution ?? 0));
        $profile->free_attr_points = max(0, (int) ($payload['free_attr_points'] ?? $profile->free_attr_points ?? 0));
        $profile->skill_points = max(0, (int) ($payload['skill_points'] ?? $profile->skill_points ?? 0));

        if (array_key_exists('current_stage_id', $payload)) {
            $profile->current_stage_id = $this->nullableString($payload['current_stage_id'], 64);
        }
        if (array_key_exists('current_difficulty', $payload)) {
            $profile->current_difficulty = max(0, (int) $payload['current_difficulty']);
        }
        if (array_key_exists('highest_cleared_stage_id', $payload)) {
            $profile->highest_cleared_stage_id = $this->nullableString($payload['highest_cleared_stage_id'], 64);
        }
        if (array_key_exists('highest_cleared_difficulty', $payload)) {
            $profile->highest_cleared_difficulty = max(0, (int) $payload['highest_cleared_difficulty']);
        }
        if (array_key_exists('current_sect_id', $payload)) {
            $profile->current_sect_id = $this->nullableString($payload['current_sect_id'], 64);
        }

        if (array_key_exists('attrs_json', $payload) && is_array($payload['attrs_json'])) {
            $profile->attrs_json = $this->normalizeAttrs($payload['attrs_json']);
        } elseif (! is_array($profile->attrs_json)) {
            $profile->attrs_json = $this->normalizeAttrs([]);
        }

        if (array_key_exists('inventory', $payload) && is_array($payload['inventory'])) {
            $profile->inventory = $this->normalizeInventory($payload['inventory']);
        } elseif (! is_array($profile->inventory)) {
            $profile->inventory = [];
        }

        if (array_key_exists('equipment', $payload) && is_array($payload['equipment'])) {
            $profile->equipment = $this->normalizeEquipment($payload['equipment']);
        } elseif (! is_array($profile->equipment)) {
            $profile->equipment = $this->normalizeEquipment([]);
        }

        if (array_key_exists('claimed_milestones', $payload) && is_array($payload['claimed_milestones'])) {
            $profile->claimed_milestones = $this->normalizeClaimedMilestones($payload['claimed_milestones']);
        } elseif (! is_array($profile->claimed_milestones)) {
            $profile->claimed_milestones = [];
        }

        if (array_key_exists('patrol_summary', $payload) && is_array($payload['patrol_summary'])) {
            $profile->patrol_summary = $this->normalizePatrolSummary($payload['patrol_summary']);
        } elseif (! is_array($profile->patrol_summary)) {
            $profile->patrol_summary = [];
        }

        if (array_key_exists('task_summary', $payload) && is_array($payload['task_summary'])) {
            $profile->task_summary = $this->normalizeTaskSummary($payload['task_summary']);
        } elseif (! is_array($profile->task_summary)) {
            $profile->task_summary = [];
        }

        $profile->save();

        return $profile;
    }

    public function loadArchive(string $playerId): array
    {
        $safePlayerId = trim($playerId);
        if ($safePlayerId === '') {
            return $this->emptyArchive();
        }

        $profile = ShopPlayerProfile::query()
            ->where('player_id', $safePlayerId)
            ->first();

        return $profile instanceof ShopPlayerProfile
            ? $this->archiveFromProfile($profile)
            : array_merge($this->emptyArchive(), ['player_id' => $safePlayerId]);
    }

    public function searchProfileOptions(string $search, int $limit = 20): array
    {
        $keyword = trim($search);
        if ($keyword === '') {
            return [];
        }

        return ShopPlayerProfile::query()
            ->where(function ($query) use ($keyword): void {
                $query->where('player_id', 'like', '%' . $keyword . '%')
                    ->orWhere('nickname', 'like', '%' . $keyword . '%');
            })
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->mapWithKeys(fn (ShopPlayerProfile $profile): array => [
                (string) $profile->player_id => $this->optionLabelFromProfile($profile),
            ])
            ->all();
    }

    public function optionLabel(string $playerId): string
    {
        $profile = ShopPlayerProfile::query()
            ->where('player_id', trim($playerId))
            ->first();

        return $profile instanceof ShopPlayerProfile
            ? $this->optionLabelFromProfile($profile)
            : trim($playerId);
    }

    public function recentProfiles(int $limit = 12): array
    {
        return ShopPlayerProfile::query()
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (ShopPlayerProfile $profile): array => [
                'player_id' => (string) $profile->player_id,
                'nickname' => (string) ($profile->nickname ?? ''),
                'level' => (int) $profile->level,
                'stage_name' => $this->stageName((string) ($profile->highest_cleared_stage_id ?: $profile->current_stage_id)),
                'updated_at' => $profile->updated_at?->format('Y-m-d H:i:s') ?? '—',
            ])
            ->all();
    }

    private function archiveFromProfile(ShopPlayerProfile $profile): array
    {
        $inventory = is_array($profile->inventory) ? $profile->inventory : [];
        $equipment = is_array($profile->equipment) ? $profile->equipment : [];
        $claimedMilestones = is_array($profile->claimed_milestones) ? $profile->claimed_milestones : [];
        $attrs = is_array($profile->attrs_json) ? $this->normalizeAttrs($profile->attrs_json) : $this->normalizeAttrs([]);
        $patrolSummary = is_array($profile->patrol_summary) ? $profile->patrol_summary : [];
        $taskSummary = is_array($profile->task_summary) ? $profile->task_summary : [];

        $inventoryPreview = $this->buildInventoryPreview($inventory);
        $equipmentPreview = $this->buildEquipmentPreview($equipment);

        return [
            'exists' => true,
            'player_id' => (string) $profile->player_id,
            'nickname' => (string) ($profile->nickname ?? ''),
            'level' => (int) $profile->level,
            'exp' => (int) $profile->exp,
            'gold' => (int) $profile->gold,
            'crystal' => (int) $profile->crystal,
            'contribution' => (int) $profile->contribution,
            'free_attr_points' => (int) $profile->free_attr_points,
            'skill_points' => (int) $profile->skill_points,
            'current_sect_id' => (string) ($profile->current_sect_id ?? ''),
            'attrs_json' => $attrs,
            'attrs_summary' => $this->attrsSummary($attrs),
            'current_stage_id' => (string) ($profile->current_stage_id ?? ''),
            'current_stage_name' => $this->stageName((string) ($profile->current_stage_id ?? '')),
            'current_difficulty' => (int) $profile->current_difficulty,
            'current_difficulty_name' => $this->difficultyName((string) ($profile->current_stage_id ?? ''), (int) $profile->current_difficulty),
            'highest_cleared_stage_id' => (string) ($profile->highest_cleared_stage_id ?? ''),
            'highest_cleared_stage_name' => $this->stageName((string) ($profile->highest_cleared_stage_id ?? '')),
            'highest_cleared_difficulty' => (int) $profile->highest_cleared_difficulty,
            'highest_cleared_difficulty_name' => $this->difficultyName((string) ($profile->highest_cleared_stage_id ?? ''), (int) $profile->highest_cleared_difficulty),
            'inventory_kind_count' => (int) $inventoryPreview['kind_count'],
            'inventory_total_count' => (int) $inventoryPreview['total_count'],
            'inventory_preview_lines' => $inventoryPreview['lines'],
            'equipped_count' => (int) $equipmentPreview['equipped_count'],
            'bag_equipment_count' => (int) $equipmentPreview['bag_count'],
            'equipment_count' => (int) $equipmentPreview['equipment_count'],
            'equipment_preview_lines' => $equipmentPreview['lines'],
            'claimed_milestone_count' => count($claimedMilestones),
            'claimed_milestones' => $claimedMilestones,
            'patrol_summary' => $patrolSummary,
            'task_summary' => $taskSummary,
            'updated_at' => $profile->updated_at?->format('Y-m-d H:i:s') ?? '—',
        ];
    }

    private function optionLabelFromProfile(ShopPlayerProfile $profile): string
    {
        $nickname = trim((string) ($profile->nickname ?? ''));
        $prefix = $nickname !== '' ? sprintf('%s｜', $nickname) : '';

        return sprintf(
            '%s%s｜Lv%d｜%s',
            $prefix,
            (string) $profile->player_id,
            (int) $profile->level,
            $this->stageName((string) ($profile->highest_cleared_stage_id ?: $profile->current_stage_id)),
        );
    }

    private function emptyArchive(): array
    {
        return [
            'exists' => false,
            'player_id' => '',
            'nickname' => '',
            'level' => 0,
            'exp' => 0,
            'gold' => 0,
            'crystal' => 0,
            'contribution' => 0,
            'free_attr_points' => 0,
            'skill_points' => 0,
            'current_sect_id' => '',
            'attrs_json' => $this->normalizeAttrs([]),
            'attrs_summary' => '—',
            'current_stage_id' => '',
            'current_stage_name' => '—',
            'current_difficulty' => 0,
            'current_difficulty_name' => '—',
            'highest_cleared_stage_id' => '',
            'highest_cleared_stage_name' => '—',
            'highest_cleared_difficulty' => 0,
            'highest_cleared_difficulty_name' => '—',
            'inventory_kind_count' => 0,
            'inventory_total_count' => 0,
            'inventory_preview_lines' => [],
            'equipped_count' => 0,
            'bag_equipment_count' => 0,
            'equipment_count' => 0,
            'equipment_preview_lines' => [],
            'claimed_milestone_count' => 0,
            'claimed_milestones' => [],
            'patrol_summary' => [],
            'task_summary' => [],
            'updated_at' => '—',
        ];
    }

    private function normalizeInventory(array $inventory): array
    {
        $out = [];

        foreach ($inventory as $key => $value) {
            if (is_array($value)) {
                $itemId = trim((string) ($value['item_id'] ?? $value['id'] ?? ''));
                $count = max(0, (int) ($value['count'] ?? 0));
            } else {
                $itemId = trim((string) $key);
                $count = max(0, (int) $value);
            }

            if ($itemId === '' || $count <= 0) {
                continue;
            }

            $out[$itemId] = $count;
        }

        ksort($out);

        return $out;
    }

    private function normalizeEquipment(array $equipment): array
    {
        if (array_is_list($equipment)) {
            $bagPreview = [];
            foreach ($equipment as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $bagPreview[] = $this->normalizeEquipmentRow($row);
            }

            return [
                'equipped' => [],
                'bag_preview' => array_values(array_filter($bagPreview)),
                'bag_count' => count($bagPreview),
                'equipped_count' => 0,
            ];
        }

        $equippedRows = [];
        foreach (($equipment['equipped'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $equippedRows[] = $this->normalizeEquipmentRow($row);
        }

        $bagPreviewRows = [];
        foreach (($equipment['bag_preview'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $bagPreviewRows[] = $this->normalizeEquipmentRow($row);
        }

        return [
            'equipped' => array_values(array_filter($equippedRows)),
            'bag_preview' => array_values(array_filter($bagPreviewRows)),
            'bag_count' => max(0, (int) ($equipment['bag_count'] ?? count($bagPreviewRows))),
            'equipped_count' => max(0, (int) ($equipment['equipped_count'] ?? count($equippedRows))),
        ];
    }

    private function normalizeEquipmentRow(array $row): array
    {
        $templateId = trim((string) ($row['template_id'] ?? ''));
        $name = trim((string) ($row['name'] ?? $row['template_name'] ?? $templateId));

        if ($templateId === '' && $name === '') {
            return [];
        }

        return [
            'template_id' => $templateId,
            'name' => $name,
            'slot' => trim((string) ($row['slot'] ?? $row['slot_key'] ?? '')),
            'rarity' => trim((string) ($row['rarity'] ?? '')),
            'star_level' => max(0, (int) ($row['star_level'] ?? 0)),
            'refine_lv' => max(0, (int) ($row['refine_lv'] ?? 0)),
            'equipped' => (bool) ($row['equipped'] ?? false),
        ];
    }

    private function normalizeClaimedMilestones(array $claimed): array
    {
        $out = [];

        if (! array_is_list($claimed)) {
            foreach ($claimed as $key => $value) {
                $milestoneKey = trim((string) $key);
                if ($milestoneKey === '' || ! $value) {
                    continue;
                }
                $out[] = $milestoneKey;
            }

            sort($out);

            return $out;
        }

        foreach ($claimed as $value) {
            $milestoneKey = trim((string) $value);
            if ($milestoneKey === '') {
                continue;
            }
            $out[] = $milestoneKey;
        }

        $out = array_values(array_unique($out));
        sort($out);

        return $out;
    }

    private function normalizeAttrs(array $attrs): array
    {
        $defaults = [
            'strength' => 0,
            'physique' => 0,
            'agility' => 0,
            'spirit' => 0,
            'true_energy' => 0,
            'fortune' => 0,
        ];

        foreach ($defaults as $key => $value) {
            $defaults[$key] = max(0, (int) ($attrs[$key] ?? $value));
        }

        return $defaults;
    }

    private function normalizePatrolSummary(array $summary): array
    {
        return [
            'status_text' => trim((string) ($summary['status_text'] ?? '')),
            'route_name' => trim((string) ($summary['route_name'] ?? '')),
            'accumulated_seconds' => max(0, (int) ($summary['accumulated_seconds'] ?? 0)),
            'is_capped' => (bool) ($summary['is_capped'] ?? false),
            'exp' => max(0, (int) ($summary['exp'] ?? 0)),
            'has_materials' => (bool) ($summary['has_materials'] ?? false),
            'has_rare_drop' => (bool) ($summary['has_rare_drop'] ?? false),
        ];
    }

    private function normalizeTaskSummary(array $summary): array
    {
        return [
            'daily_total' => max(0, (int) ($summary['daily_total'] ?? 0)),
            'milestone_total' => max(0, (int) ($summary['milestone_total'] ?? 0)),
            'daily_unlocked' => max(0, (int) ($summary['daily_unlocked'] ?? 0)),
            'milestone_unlocked' => max(0, (int) ($summary['milestone_unlocked'] ?? 0)),
            'claimable' => max(0, (int) ($summary['claimable'] ?? 0)),
        ];
    }

    private function buildInventoryPreview(array $inventory): array
    {
        $kindCount = count($inventory);
        $totalCount = array_sum(array_map(static fn (mixed $count): int => max(0, (int) $count), $inventory));
        if ($inventory === []) {
            return ['kind_count' => 0, 'total_count' => 0, 'lines' => []];
        }

        $itemNames = Item::query()
            ->whereIn('id', array_keys($inventory))
            ->pluck('name', 'id')
            ->all();

        $rows = collect($inventory)
            ->map(fn (mixed $count, string $itemId): array => [
                'item_id' => $itemId,
                'name' => (string) ($itemNames[$itemId] ?? $itemId),
                'count' => max(0, (int) $count),
            ])
            ->filter(fn (array $row): bool => $row['count'] > 0)
            ->sortBy([
                ['count', 'desc'],
                ['name', 'asc'],
            ])
            ->take(12)
            ->map(fn (array $row): string => sprintf('%s x%d', $row['name'], $row['count']))
            ->values()
            ->all();

        return [
            'kind_count' => $kindCount,
            'total_count' => $totalCount,
            'lines' => $rows,
        ];
    }

    private function buildEquipmentPreview(array $equipment): array
    {
        $equippedRows = collect($equipment['equipped'] ?? [])
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(fn (array $row): string => sprintf(
                '已装备｜%s｜%s',
                $this->slotLabel((string) ($row['slot'] ?? '')),
                (string) ($row['name'] ?? $row['template_id'] ?? '装备'),
            ))
            ->take(8)
            ->values()
            ->all();

        $bagRows = collect($equipment['bag_preview'] ?? [])
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(fn (array $row): string => sprintf(
                '背包｜%s｜%s',
                $this->slotLabel((string) ($row['slot'] ?? '')),
                (string) ($row['name'] ?? $row['template_id'] ?? '装备'),
            ))
            ->take(8)
            ->values()
            ->all();

        $equippedCount = max(0, (int) ($equipment['equipped_count'] ?? count($equippedRows)));
        $bagCount = max(0, (int) ($equipment['bag_count'] ?? count($bagRows)));

        return [
            'equipped_count' => $equippedCount,
            'bag_count' => $bagCount,
            'equipment_count' => $equippedCount + $bagCount,
            'lines' => array_values(array_merge($equippedRows, $bagRows)),
        ];
    }

    private function attrsSummary(array $attrs): string
    {
        $labels = [
            'strength' => '力道',
            'physique' => '体魄',
            'agility' => '身法',
            'spirit' => '神识',
            'true_energy' => '真元',
            'fortune' => '运势',
        ];

        $parts = [];
        foreach ($labels as $key => $label) {
            $parts[] = sprintf('%s %d', $label, max(0, (int) ($attrs[$key] ?? 0)));
        }

        return implode(' / ', $parts);
    }

    private function stageName(string $stageId): string
    {
        $safeStageId = trim($stageId);
        if ($safeStageId === '') {
            return '—';
        }

        $stage = Stage::query()->find($safeStageId);

        return $stage instanceof Stage
            ? (string) $stage->name
            : $safeStageId;
    }

    private function difficultyName(string $stageId, int $difficulty): string
    {
        $safeStageId = trim($stageId);
        if ($safeStageId === '') {
            return '—';
        }

        $stage = Stage::query()->find($safeStageId);
        if (! $stage instanceof Stage || ! is_array($stage->difficulties)) {
            return '难度' . max(0, $difficulty);
        }

        $rows = array_values($stage->difficulties);
        $safeIndex = max(0, min($difficulty, max(count($rows) - 1, 0)));

        if (! isset($rows[$safeIndex]) || ! is_array($rows[$safeIndex])) {
            return '难度' . max(0, $difficulty);
        }

        return trim((string) ($rows[$safeIndex]['difficulty_name'] ?? '难度' . max(0, $difficulty)));
    }

    private function slotLabel(string $slot): string
    {
        return match (trim($slot)) {
            'main_weapon' => '主武器',
            'off_weapon' => '副武器',
            'armor' => '盔甲',
            'belt' => '腰带',
            'shoes' => '鞋子',
            'gloves' => '护手',
            'helm' => '头盔',
            'necklace' => '项链',
            'talisman' => '护身符',
            'ring', 'ring1', 'ring2' => '戒指',
            'bracelet', 'bracelet1', 'bracelet2' => '手镯',
            default => trim($slot) !== '' ? trim($slot) : '未标注部位',
        };
    }

    private function nullableString(mixed $value, int $maxLength): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, $maxLength);
    }
}
