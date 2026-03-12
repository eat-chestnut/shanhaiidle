<?php

namespace Database\Seeders;

use App\Models\BlueAffix;
use App\Models\BlueGearTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class BlueGearTemplatesSeeder extends Seeder
{
    private const STAGES = [
        1 => ['pool' => 'pool_blue_1', 'min_affix_count' => 1, 'max_affix_count' => 1],
        5 => ['pool' => 'pool_blue_5', 'min_affix_count' => 1, 'max_affix_count' => 1],
        10 => ['pool' => 'pool_blue_10', 'min_affix_count' => 1, 'max_affix_count' => 1],
        15 => ['pool' => 'pool_blue_15', 'min_affix_count' => 2, 'max_affix_count' => 2],
        20 => ['pool' => 'pool_blue_20', 'min_affix_count' => 2, 'max_affix_count' => 2],
    ];

    private const SLOT_CONFIG = [
        'main_weapon' => ['name' => '主武器', 'white_stats' => [1 => ['ATK' => 6, 'HP' => 6], 5 => ['ATK' => 10, 'HP' => 10], 10 => ['ATK' => 16, 'HP' => 14], 15 => ['ATK' => 22, 'HP' => 18], 20 => ['ATK' => 28, 'HP' => 24]]],
        'off_weapon' => ['name' => '副武器', 'white_stats' => [1 => ['ATK' => 4, 'DEF' => 1], 5 => ['ATK' => 7, 'DEF' => 2], 10 => ['ATK' => 11, 'DEF' => 3], 15 => ['ATK' => 15, 'DEF' => 5], 20 => ['ATK' => 20, 'DEF' => 6]]],
        'armor' => ['name' => '盔甲', 'white_stats' => [1 => ['DEF' => 4, 'HP' => 24], 5 => ['DEF' => 7, 'HP' => 40], 10 => ['DEF' => 10, 'HP' => 58], 15 => ['DEF' => 14, 'HP' => 74], 20 => ['DEF' => 18, 'HP' => 90]]],
        'belt' => ['name' => '腰带', 'white_stats' => [1 => ['HP' => 20, 'DEF' => 2], 5 => ['HP' => 32, 'DEF' => 3], 10 => ['HP' => 46, 'DEF' => 5], 15 => ['HP' => 60, 'DEF' => 6], 20 => ['HP' => 72, 'DEF' => 8]]],
        'shoes' => ['name' => '鞋子', 'white_stats' => [1 => ['DEF' => 3, 'DODGE' => 1], 5 => ['DEF' => 6, 'DODGE' => 1], 10 => ['DEF' => 9, 'DODGE' => 1], 15 => ['DEF' => 12, 'DODGE' => 2], 20 => ['DEF' => 14, 'DODGE' => 2]]],
        'gloves' => ['name' => '护手', 'white_stats' => [1 => ['ATK' => 3, 'CRIT_PERCENT' => 1], 5 => ['ATK' => 5, 'CRIT_PERCENT' => 1], 10 => ['ATK' => 8, 'CRIT_PERCENT' => 1], 15 => ['ATK' => 11, 'CRIT_PERCENT' => 2], 20 => ['ATK' => 14, 'CRIT_PERCENT' => 2]]],
        'helm' => ['name' => '头盔', 'white_stats' => [1 => ['DEF' => 4, 'HP' => 18], 5 => ['DEF' => 7, 'HP' => 30], 10 => ['DEF' => 10, 'HP' => 42], 15 => ['DEF' => 13, 'HP' => 50], 20 => ['DEF' => 16, 'HP' => 56]]],
        'necklace' => ['name' => '项链', 'white_stats' => [1 => ['HP' => 12, 'ATK' => 2], 5 => ['HP' => 22, 'ATK' => 3], 10 => ['HP' => 34, 'ATK' => 4], 15 => ['HP' => 44, 'ATK' => 5], 20 => ['HP' => 54, 'ATK' => 6]]],
    ];

    private const SLOT_STAT_BIAS = [
        'main_weapon' => ['ATK' => 20, 'MELEE_ATK' => 18, 'SPELL_ATK' => 16, 'CRIT_PERCENT' => 12, 'CRIT_DMG' => 10, 'ATK_SPEED' => 8],
        'off_weapon' => ['ATK' => 14, 'DEF' => 10, 'SPELL_ATK' => 12, 'MELEE_ATK' => 10, 'CDR' => 8],
        'armor' => ['HP' => 18, 'DEF' => 18, 'PDEF' => 14, 'MDEF' => 10],
        'belt' => ['HP' => 14, 'DEF' => 12, 'DODGE' => 8, 'PDEF' => 8],
        'shoes' => ['DODGE' => 16, 'DEF' => 10, 'ATK_SPEED' => 8],
        'gloves' => ['ATK' => 14, 'CRIT_PERCENT' => 12, 'CRIT_DMG' => 10, 'ATK_SPEED' => 12, 'MELEE_ATK' => 10],
        'helm' => ['HP' => 12, 'DEF' => 14, 'PDEF' => 10, 'MDEF' => 10],
        'necklace' => ['ATK' => 8, 'HP' => 10, 'CRIT_PERCENT' => 10, 'CRIT_DMG' => 10, 'SPELL_ATK' => 8, 'CDR' => 10, 'DODGE' => 6],
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
            foreach (self::SLOT_CONFIG as $slotId => $config) {
                $rows[] = [
                    'template_id' => sprintf('blue_%s_%d', $slotId, $level),
                    'name' => sprintf('%d级蓝装%s', $level, $config['name']),
                    'blue_pool_id' => $stage['pool'],
                    'slot_id' => $slotId,
                    'required_level' => $level,
                    'white_stats' => $config['white_stats'][$level],
                    'affix_count' => $stage['max_affix_count'],
                    'min_affix_count' => $stage['min_affix_count'],
                    'max_affix_count' => $stage['max_affix_count'],
                    'affix_pool_tags' => [sprintf('stage_%d', $level), sprintf('slot_%s', $slotId)],
                    'affix_entries' => $this->buildAffixEntries($affixes, $slotId, $level),
                    'icon' => '',
                    'sort_order' => $sortOrder,
                    'is_enabled' => true,
                ];
                $sortOrder += 10;
            }
        }

        foreach ($rows as $row) {
            $validIds[] = $row['template_id'];
            $template = BlueGearTemplate::query()->firstOrNew([
                'template_id' => $row['template_id'],
            ]);

            $payload = $row;
            unset($payload['white_stats'], $payload['affix_entries']);

            $template->fill($payload);
            $template->white_stats = $row['white_stats'];
            $template->affix_entries = $row['affix_entries'];
            $template->save();
        }

        BlueGearTemplate::query()->whereNotIn('template_id', $validIds)->delete();
    }

    private function buildAffixEntries(Collection $affixes, string $slotId, int $level): array
    {
        return $affixes
            ->filter(function (BlueAffix $affix) use ($slotId, $level): bool {
                if ((int) ($affix->unlock_level ?? 0) !== $level) {
                    return false;
                }

                return in_array($slotId, is_array($affix->slot_tags) ? $affix->slot_tags : [], true);
            })
            ->map(function (BlueAffix $affix) use ($slotId): array {
                $stat = (string) $affix->stat;
                $weight = 10;
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
