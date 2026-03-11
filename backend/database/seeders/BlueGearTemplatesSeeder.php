<?php

namespace Database\Seeders;

use App\Models\BlueAffix;
use App\Models\BlueGearTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class BlueGearTemplatesSeeder extends Seeder
{
    private const FLOWS = [
        'berserk' => '狂战',
        'guard' => '防战',
        'hunt' => '暴击流',
        'evade' => '闪避流',
        'flame' => '火法',
        'frost' => '冰法',
    ];

    private const STAGES = [
        20 => ['pool' => 'pool_blue_20', 'min_affix_count' => 1, 'max_affix_count' => 1],
        30 => ['pool' => 'pool_blue_30', 'min_affix_count' => 1, 'max_affix_count' => 1],
        40 => ['pool' => 'pool_blue_40', 'min_affix_count' => 2, 'max_affix_count' => 2],
        60 => ['pool' => 'pool_blue_60', 'min_affix_count' => 2, 'max_affix_count' => 2],
    ];

    private const SLOT_CONFIG = [
        'main_weapon' => ['name' => '主武器', 'white_stats' => [20 => ['ATK' => 28, 'HP' => 24], 30 => ['ATK' => 38, 'HP' => 30], 40 => ['ATK' => 52, 'HP' => 38], 60 => ['ATK' => 76, 'HP' => 56]]],
        'off_weapon' => ['name' => '副武器', 'white_stats' => [20 => ['ATK' => 20, 'DEF' => 6], 30 => ['ATK' => 28, 'DEF' => 8], 40 => ['ATK' => 38, 'DEF' => 12], 60 => ['ATK' => 54, 'DEF' => 16]]],
        'armor' => ['name' => '盔甲', 'white_stats' => [20 => ['DEF' => 18, 'HP' => 90], 30 => ['DEF' => 24, 'HP' => 120], 40 => ['DEF' => 34, 'HP' => 168], 60 => ['DEF' => 48, 'HP' => 236]]],
        'belt' => ['name' => '腰带', 'white_stats' => [20 => ['HP' => 72, 'DEF' => 8], 30 => ['HP' => 98, 'DEF' => 10], 40 => ['HP' => 138, 'DEF' => 14], 60 => ['HP' => 196, 'DEF' => 20]]],
        'shoes' => ['name' => '鞋子', 'white_stats' => [20 => ['DEF' => 14, 'DODGE' => 2], 30 => ['DEF' => 18, 'DODGE' => 3], 40 => ['DEF' => 24, 'DODGE' => 4], 60 => ['DEF' => 34, 'DODGE' => 5]]],
        'gloves' => ['name' => '护手', 'white_stats' => [20 => ['ATK' => 14, 'CRIT_RATE' => 2], 30 => ['ATK' => 20, 'CRIT_RATE' => 3], 40 => ['ATK' => 28, 'CRIT_RATE' => 4], 60 => ['ATK' => 40, 'CRIT_RATE' => 5]]],
        'helm' => ['name' => '头盔', 'white_stats' => [20 => ['DEF' => 16, 'HP' => 56], 30 => ['DEF' => 22, 'HP' => 78], 40 => ['DEF' => 30, 'HP' => 112], 60 => ['DEF' => 42, 'HP' => 156]]],
        'necklace' => ['name' => '项链', 'white_stats' => [20 => ['HP' => 54, 'ATK' => 6], 30 => ['HP' => 76, 'ATK' => 8], 40 => ['HP' => 108, 'ATK' => 12], 60 => ['HP' => 152, 'ATK' => 18]]],
    ];

    private const FLOW_STAT_BIAS = [
        'berserk' => ['ATK' => 28, 'MELEE_ATK' => 22, 'CRIT_PERCENT' => 14, 'ATK_SPEED' => 10, 'FINAL_DAMAGE' => 8],
        'guard' => ['HP' => 26, 'DEF' => 22, 'PDEF' => 18, 'MDEF' => 12, 'FINAL_REDUCTION' => 8],
        'hunt' => ['CRIT_PERCENT' => 24, 'CRIT_DMG' => 18, 'RANGED_ATK' => 18, 'ATK_SPEED' => 12, 'DODGE' => 8],
        'evade' => ['DODGE' => 24, 'ATK_SPEED' => 12, 'RANGED_ATK' => 10, 'LOOT_BONUS_PERCENT' => 8, 'CDR' => 8],
        'flame' => ['SPELL_ATK' => 26, 'CRIT_DMG' => 10, 'FINAL_DAMAGE' => 8, 'CDR' => 8, 'QI' => 8],
        'frost' => ['SPELL_ATK' => 20, 'CDR' => 18, 'MDEF' => 12, 'QI' => 10, 'FINAL_REDUCTION' => 8],
    ];

    private const SLOT_STAT_BIAS = [
        'main_weapon' => ['ATK' => 20, 'MELEE_ATK' => 18, 'RANGED_ATK' => 18, 'SPELL_ATK' => 18, 'CRIT_PERCENT' => 10, 'CRIT_DMG' => 8, 'FINAL_DAMAGE' => 6],
        'off_weapon' => ['ATK' => 14, 'MELEE_ATK' => 12, 'RANGED_ATK' => 12, 'SPELL_ATK' => 12, 'DEF' => 8],
        'armor' => ['HP' => 18, 'DEF' => 18, 'PDEF' => 12, 'MDEF' => 10, 'FINAL_REDUCTION' => 6],
        'belt' => ['HP' => 16, 'DEF' => 12, 'DODGE' => 8, 'FINAL_REDUCTION' => 4],
        'shoes' => ['DODGE' => 16, 'DEF' => 10, 'ATK_SPEED' => 8],
        'gloves' => ['ATK' => 14, 'CRIT_PERCENT' => 12, 'ATK_SPEED' => 12, 'MELEE_ATK' => 10, 'RANGED_ATK' => 10],
        'helm' => ['HP' => 12, 'DEF' => 14, 'PDEF' => 10, 'MDEF' => 10],
        'necklace' => ['ATK' => 8, 'HP' => 8, 'CRIT_PERCENT' => 10, 'CRIT_DMG' => 10, 'SPELL_ATK' => 8, 'CDR' => 10, 'QI' => 8, 'LOOT_BONUS_PERCENT' => 6],
    ];

    public function run(): void
    {
        $affixes = BlueAffix::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('affix_id')
            ->get();

        $rows = [];
        $validIds = [];
        $sortOrder = 10;

        foreach (self::STAGES as $level => $stage) {
            foreach (self::FLOWS as $flowTag => $flowName) {
                foreach (self::SLOT_CONFIG as $slotId => $config) {
                    $rows[] = [
                        'template_id' => sprintf('blue_%s_%s_%d_01', $slotId, $flowTag, $level),
                        'name' => sprintf('%s试炼%s·%d级蓝装', $flowName, $config['name'], $level),
                        'blue_pool_id' => $stage['pool'],
                        'slot_id' => $slotId,
                        'flow_tag' => $flowTag,
                        'required_level' => $level,
                        'white_stats' => $config['white_stats'][$level],
                        'affix_count' => $stage['max_affix_count'],
                        'min_affix_count' => $stage['min_affix_count'],
                        'max_affix_count' => $stage['max_affix_count'],
                        'affix_pool_tags' => [sprintf('stage_%d', $level), sprintf('slot_%s', $slotId), sprintf('flow_%s', $flowTag)],
                        'affix_entries' => $this->buildAffixEntries($affixes, $slotId, $flowTag),
                        'icon' => '',
                        'sort_order' => $sortOrder,
                        'is_enabled' => true,
                    ];
                    $sortOrder += 10;
                }
            }
        }

        foreach ($rows as $row) {
            $validIds[] = $row['template_id'];
            BlueGearTemplate::query()->updateOrCreate(
                ['template_id' => $row['template_id']],
                $row,
            );
        }

        BlueGearTemplate::query()->whereNotIn('template_id', $validIds)->delete();
    }

    private function buildAffixEntries(Collection $affixes, string $slotId, string $flowTag): array
    {
        return $affixes
            ->filter(fn (BlueAffix $affix): bool => in_array($slotId, is_array($affix->slot_tags) ? $affix->slot_tags : [], true))
            ->map(function (BlueAffix $affix) use ($slotId, $flowTag): array {
                $stat = (string) $affix->stat;
                $weight = 10;
                $weight += self::FLOW_STAT_BIAS[$flowTag][$stat] ?? 0;
                $weight += self::SLOT_STAT_BIAS[$slotId][$stat] ?? 0;

                return [
                    'affix_id' => (string) $affix->affix_id,
                    'weight' => max(1, $weight),
                ];
            })
            ->values()
            ->all();
    }
}
