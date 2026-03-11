<?php

namespace Database\Seeders;

use App\Models\EquipTemplate;
use Illuminate\Database\Seeder;

class EquipTemplatesSeeder extends Seeder
{
    private const SET_SLOT_CONFIG = [
        'main_weapon' => ['group' => 'weapon', 'main' => 'ATK', 'base' => ['ATK' => [20, 34, 46, 60], 'HP' => [28, 46, 62, 82]]],
        'off_weapon' => ['group' => 'weapon', 'main' => 'ATK', 'base' => ['ATK' => [14, 24, 32, 42], 'DEF' => [4, 7, 9, 12]]],
        'armor' => ['group' => 'armor', 'main' => 'DEF', 'base' => ['DEF' => [12, 22, 30, 40], 'HP' => [64, 112, 150, 196]]],
        'belt' => ['group' => 'armor', 'main' => 'HP', 'base' => ['HP' => [54, 92, 124, 164], 'DEF' => [5, 8, 11, 15]]],
        'shoes' => ['group' => 'armor', 'main' => 'DEF', 'base' => ['DEF' => [9, 16, 22, 30], 'HP' => [32, 56, 74, 98]]],
        'gloves' => ['group' => 'armor', 'main' => 'ATK', 'base' => ['ATK' => [10, 18, 24, 32], 'HP' => [22, 40, 52, 70]]],
        'helm' => ['group' => 'armor', 'main' => 'DEF', 'base' => ['DEF' => [10, 18, 24, 32], 'HP' => [36, 62, 84, 112]]],
        'necklace' => ['group' => 'accessory', 'main' => 'HP', 'base' => ['HP' => [34, 60, 80, 104], 'ATK' => [4, 7, 10, 13]]],
    ];

    private const ACCESSORY_CONFIG = [
        'ring' => ['group' => 'accessory', 'main' => 'ATK', 'base' => ['ATK' => [8, 14, 18, 24], 'CRIT_RATE' => [2, 3, 4, 5]]],
        'bracelet' => ['group' => 'accessory', 'main' => 'DEF', 'base' => ['DEF' => [8, 14, 18, 24], 'HP' => [40, 68, 90, 120]]],
        'talisman' => ['group' => 'accessory', 'main' => 'HP', 'base' => ['HP' => [0, 76, 102, 136], 'QI' => [0, 6, 8, 10]]],
    ];

    private const STAGES = [
        't1' => ['level' => 20, 'star_cap' => 3, 'set_stage' => 20, 'theme_key' => 'nanshan', 'forge_tier' => 'T1'],
        't2' => ['level' => 40, 'star_cap' => 6, 'set_stage' => 40, 'theme_key' => 'nanshan', 'forge_tier' => 'T2'],
        't3' => ['level' => 50, 'star_cap' => 8, 'set_stage' => 40, 'theme_key' => 'nanshan', 'forge_tier' => 'T3'],
        't4' => ['level' => 60, 'star_cap' => 10, 'set_stage' => 60, 'theme_key' => 'qingqiu', 'forge_tier' => 'T4'],
    ];

    private const SET_LINES = [
        'lushu' => ['set_line_id' => 'setline_lushu', 'set_name' => '鹿蜀', 'flow_tag' => 'berserk'],
        'xuangui' => ['set_line_id' => 'setline_xuangui', 'set_name' => '旋龟', 'flow_tag' => 'guard'],
        'guanguan' => ['set_line_id' => 'setline_guanguan', 'set_name' => '灌灌', 'flow_tag' => 'hunt'],
        'migu' => ['set_line_id' => 'setline_migu', 'set_name' => '迷榖', 'flow_tag' => 'evade'],
        'chiyu' => ['set_line_id' => 'setline_chiyu', 'set_name' => '赤鱬', 'flow_tag' => 'flame'],
        'qingqiu' => ['set_line_id' => 'setline_qingqiu', 'set_name' => '青丘', 'flow_tag' => 'frost'],
    ];

    public function run(): void
    {
        $rows = [];
        $validIds = [];
        $sort = 10;

        foreach (self::SET_LINES as $key => $line) {
            foreach (self::STAGES as $stageKey => $stage) {
                foreach (self::SET_SLOT_CONFIG as $slot => $config) {
                    $id = $this->setTemplateId($key, $slot, $stageKey);
                    $row = $this->buildSetTemplate($id, $line, $slot, $config, $stageKey, $stage, $sort);
                    $rows[] = $row;
                    $validIds[] = $id;
                    $sort += 10;
                }
            }
        }

        foreach (array_keys(self::SET_LINES) as $key) {
            foreach (['t1', 't2', 't3', 't4'] as $stageKey) {
                foreach (['ring', 'bracelet'] as $slot) {
                    $id = $this->accessoryTemplateId($key, $slot, $stageKey);
                    $row = $this->buildAccessoryTemplate($id, $key, $slot, $stageKey, $sort);
                    $rows[] = $row;
                    $validIds[] = $id;
                    $sort += 10;
                }
            }

            foreach (['t2', 't3', 't4'] as $stageKey) {
                $id = $this->accessoryTemplateId($key, 'talisman', $stageKey);
                $row = $this->buildAccessoryTemplate($id, $key, 'talisman', $stageKey, $sort);
                $rows[] = $row;
                $validIds[] = $id;
                $sort += 10;
            }
        }

        // 保留当前前端调试链路依赖的三条高品质模板。
        foreach ([
            ['normal' => 'main_weapon_nanshan_t1_normal_01', 'high' => 'main_weapon_nanshan_t1_high_01', 'name' => '鹿蜀·主武·20级·精', 'rarity' => 'blue'],
            ['normal' => 'ring_nanshan_t2_normal_01', 'high' => 'ring_nanshan_t2_high_01', 'name' => '鹿蜀·戒指·40级·精', 'rarity' => 'purple'],
            ['normal' => 'bracelet_qingqiu_t2_normal_01', 'high' => 'bracelet_qingqiu_t2_high_01', 'name' => '青丘·手镯·40级·精', 'rarity' => 'purple'],
        ] as $preset) {
            $normal = collect($rows)->firstWhere('id', $preset['normal']);
            if (! is_array($normal)) {
                continue;
            }
            $high = $normal;
            $high['id'] = $preset['high'];
            $high['name'] = $preset['name'];
            $high['rarity'] = $preset['rarity'];
            $high['quality_tier'] = 'high';
            $high['upgrade_from_template_id'] = $preset['normal'];
            $high['upgrade_to_template_id'] = '';
            $high['white_stats'] = $this->scaleStats($normal['white_stats'], 1.22);
            $high['star_growth'] = $this->scaleStats($normal['star_growth'], 1.18);
            $high['sort_order'] = $sort;
            $rows[] = $high;
            $validIds[] = $preset['high'];
            $sort += 10;
        }

        foreach ($rows as $row) {
            EquipTemplate::query()->updateOrCreate(['id' => $row['id']], $row);
        }

        EquipTemplate::query()->whereNotIn('id', $validIds)->delete();
    }

    private function buildSetTemplate(string $id, array $line, string $slot, array $config, string $stageKey, array $stage, int $sortOrder): array
    {
        $whiteStats = $this->buildStats($config['base'], $line['flow_tag'], $stageKey, $slot);

        return [
            'id' => $id,
            'name' => sprintf('%s·%s·%d级', $line['set_name'], $this->slotName($slot), $stage['level']),
            'slot' => $slot,
            'equip_type' => 'set',
            'rarity' => $this->rarityForStage($stageKey),
            'icon' => '',
            'set_id' => sprintf('%s_%d', $line['set_line_id'], $stage['set_stage']),
            'set_line_id' => $line['set_line_id'],
            'set_stage' => $stage['set_stage'],
            'flow_tag' => $line['flow_tag'],
            'required_level' => $stage['level'],
            'white_stats' => $whiteStats,
            'star_growth' => $this->buildStarGrowth($whiteStats, $stageKey, $slot),
            'star_enabled' => true,
            'star_cap' => $stage['star_cap'],
            'can_attach_blue_affix' => true,
            'can_roll_purple_affix' => true,
            'socket_rule_ref' => 'fixed_star_3_6_8_10',
            'slot_group' => $config['group'],
            'theme_key' => $stage['theme_key'],
            'quality_tier' => 'normal',
            'forge_enabled' => true,
            'forge_tier' => $stage['forge_tier'],
            'forge_family_id' => sprintf('family_%s_%s', $line['set_line_id'], $slot),
            'upgrade_from_template_id' => '',
            'upgrade_to_template_id' => $this->upgradeToHighId($id),
            'blueprint_item_id' => $this->blueprintForSetStage($line['set_line_id'], $stage['set_stage']),
            'is_enabled' => true,
            'sort_order' => $sortOrder,
        ];
    }

    private function buildAccessoryTemplate(string $id, string $lineKey, string $slot, string $stageKey, int $sortOrder): array
    {
        $line = self::SET_LINES[$lineKey];
        $stage = self::STAGES[$stageKey];
        $config = self::ACCESSORY_CONFIG[$slot];
        $whiteStats = $this->buildStats($config['base'], $line['flow_tag'], $stageKey, $slot);
        $forgeEnabled = $slot !== 'talisman';

        return [
            'id' => $id,
            'name' => sprintf('%s·%s·%d级', $line['set_name'], $this->slotName($slot), $stage['level']),
            'slot' => $slot,
            'equip_type' => $slot,
            'rarity' => $this->rarityForStage($stageKey),
            'icon' => '',
            'set_id' => null,
            'set_line_id' => null,
            'set_stage' => null,
            'flow_tag' => $line['flow_tag'],
            'required_level' => $stage['level'],
            'white_stats' => $whiteStats,
            'star_growth' => $this->buildStarGrowth($whiteStats, $stageKey, $slot),
            'star_enabled' => true,
            'star_cap' => $stage['star_cap'],
            'can_attach_blue_affix' => true,
            'can_roll_purple_affix' => true,
            'socket_rule_ref' => 'fixed_star_3_6_8_10',
            'slot_group' => $config['group'],
            'theme_key' => $stage['theme_key'],
            'quality_tier' => 'normal',
            'forge_enabled' => $forgeEnabled,
            'forge_tier' => $stage['forge_tier'],
            'forge_family_id' => sprintf('family_%s_%s', $slot, $line['flow_tag']),
            'upgrade_from_template_id' => '',
            'upgrade_to_template_id' => $this->upgradeToHighId($id),
            'blueprint_item_id' => $slot === 'talisman' ? '' : $this->blueprintForAccessory($slot, $line['flow_tag'], $stage['set_stage']),
            'is_enabled' => true,
            'sort_order' => $sortOrder,
        ];
    }

    private function buildStats(array $base, string $flowTag, string $stageKey, string $slot): array
    {
        $stageIndex = array_search($stageKey, array_keys(self::STAGES), true);
        $stageIndex = $stageIndex === false ? 0 : $stageIndex;
        $stats = [];
        foreach ($base as $stat => $values) {
            $stats[$stat] = (int) ($values[$stageIndex] ?? end($values) ?: 0);
        }

        $bonus = match ($flowTag) {
            'berserk' => ['ATK' => [3, 6, 9, 12], 'CRIT_RATE' => [1, 2, 3, 4]],
            'guard' => ['DEF' => [3, 6, 9, 12], 'HP' => [18, 36, 54, 72]],
            'hunt' => ['CRIT_RATE' => [2, 3, 4, 5], 'ATK_SPEED' => [2, 3, 4, 5]],
            'evade' => ['DODGE' => [2, 3, 4, 5], 'HP' => [12, 24, 36, 48]],
            'flame' => ['SPELL_ATK' => [4, 8, 12, 16], 'CRIT_DMG' => [2, 4, 5, 7]],
            default => ['MDEF' => [4, 8, 12, 16], 'CDR' => [2, 3, 4, 5]],
        };

        foreach ($bonus as $stat => $values) {
            $value = (int) ($values[$stageIndex] ?? end($values) ?: 0);
            if ($value <= 0) {
                continue;
            }
            $stats[$stat] = (int) (($stats[$stat] ?? 0) + $value);
        }

        if ($slot === 'talisman') {
            $stats['QI'] = max(4, (int) ($stats['QI'] ?? 0));
        }

        return $this->mapToStatEntries($stats);
    }

    private function buildStarGrowth(array $whiteStats, string $stageKey, string $slot): array
    {
        $multiplier = match ($stageKey) {
            't1' => 0.18,
            't2' => 0.16,
            't3' => 0.15,
            default => 0.14,
        };

        $stats = [];
        foreach ($this->statEntriesToMap($whiteStats) as $stat => $value) {
            $grown = max(1, (int) round($value * $multiplier));
            if (in_array($stat, ['CRIT_RATE', 'CRIT_DMG', 'DODGE', 'ATK_SPEED', 'CDR', 'FINAL_DAMAGE', 'FINAL_REDUCTION'], true)) {
                $grown = max(1, (int) ceil($value * 0.5));
            }
            $stats[$stat] = $grown;
        }

        if ($slot === 'talisman' && isset($stats['QI'])) {
            $stats['QI'] = max(1, (int) $stats['QI']);
        }

        return $this->mapToStatEntries($stats);
    }

    private function scaleStats(array $stats, float $scale): array
    {
        $out = [];
        foreach ($this->statEntriesToMap($stats) as $stat => $value) {
            $out[$stat] = max(1, (int) ceil($value * $scale));
        }

        return $this->mapToStatEntries($out);
    }

    private function rarityForStage(string $stageKey): string
    {
        return match ($stageKey) {
            't1' => 'white',
            't2' => 'blue',
            't3' => 'purple',
            default => 'gold',
        };
    }

    private function setTemplateId(string $lineKey, string $slot, string $stageKey): string
    {
        if ($lineKey === 'lushu') {
            return sprintf('%s_nanshan_%s_normal_01', $slot, $stageKey);
        }

        return sprintf('%s_%s_%s_normal_01', $slot, $lineKey, $stageKey);
    }

    private function accessoryTemplateId(string $lineKey, string $slot, string $stageKey): string
    {
        if ($slot === 'ring' && $lineKey === 'lushu' && in_array($stageKey, ['t2', 't3', 't4'], true)) {
            return sprintf('ring_nanshan_%s_normal_01', $stageKey);
        }

        if ($slot === 'bracelet' && $lineKey === 'qingqiu' && in_array($stageKey, ['t2', 't3', 't4'], true)) {
            return sprintf('bracelet_qingqiu_%s_normal_01', $stageKey);
        }

        if ($slot === 'talisman' && $lineKey === 'lushu') {
            return match ($stageKey) {
                't2' => 'talisman_shop_40_01',
                't3' => 'talisman_shop_50_01',
                't4' => 'talisman_shop_60_01',
                default => sprintf('talisman_lushu_%s_normal_01', $stageKey),
            };
        }

        return sprintf('%s_%s_%s_normal_01', $slot, $lineKey, $stageKey);
    }

    private function blueprintForSetStage(string $setLineId, int $setStage): string
    {
        return match ($setStage) {
            20 => '',
            40 => 'bp_' . str_replace('setline_', 'set_', $setLineId) . '_40',
            60 => 'bp_' . str_replace('setline_', 'set_', $setLineId) . '_60',
            default => '',
        };
    }

    private function blueprintForAccessory(string $slot, string $flowTag, int $setStage): string
    {
        if ($slot === 'talisman') {
            return '';
        }

        if ($slot === 'ring' && $flowTag === 'berserk' && $setStage >= 40) {
            return 'bp_ring_nanshan_t2_01';
        }

        if ($slot === 'bracelet' && $flowTag === 'frost' && $setStage >= 40) {
            return 'bp_bracelet_qingqiu_t2_01';
        }

        return '';
    }

    private function upgradeToHighId(string $id): string
    {
        return match ($id) {
            'main_weapon_nanshan_t1_normal_01' => 'main_weapon_nanshan_t1_high_01',
            'ring_nanshan_t2_normal_01' => 'ring_nanshan_t2_high_01',
            'bracelet_qingqiu_t2_normal_01' => 'bracelet_qingqiu_t2_high_01',
            default => '',
        };
    }

    private function slotName(string $slot): string
    {
        return match ($slot) {
            'main_weapon' => '主武器',
            'off_weapon' => '副武器',
            'armor' => '盔甲',
            'belt' => '腰带',
            'shoes' => '鞋子',
            'gloves' => '护手',
            'helm' => '头盔',
            'necklace' => '项链',
            'ring' => '戒指',
            'bracelet' => '手镯',
            'talisman' => '护身符',
            default => $slot,
        };
    }

    private function mapToStatEntries(array $stats): array
    {
        $rows = [];

        foreach ($stats as $stat => $value) {
            $key = trim((string) $stat);
            if ($key === '') {
                continue;
            }

            $rows[] = [
                'stat' => $key,
                'value' => max(0, (int) $value),
            ];
        }

        return $rows;
    }

    private function statEntriesToMap(array $rows): array
    {
        $stats = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $stat = trim((string) ($row['stat'] ?? ''));
            if ($stat === '') {
                continue;
            }

            $stats[$stat] = max(0, (int) ($row['value'] ?? 0));
        }

        return $stats;
    }
}
