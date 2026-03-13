<?php

namespace Database\Seeders;

use App\Models\EquipmentSet;
use Illuminate\Database\Seeder;

class EquipmentSetsSeeder extends Seeder
{
    private const BASE_SLOTS = [
        'main_weapon',
        'off_weapon',
        'armor',
        'belt',
        'shoes',
        'gloves',
        'helm',
        'necklace',
    ];

    public function run(): void
    {
        $lines = [
            ['id' => 'setline_lushu', 'name' => '鹿蜀套', 'flow_tag' => 'berserk', 'sect' => '兵宗'],
            ['id' => 'setline_xuangui', 'name' => '旋龟套', 'flow_tag' => 'guard', 'sect' => '兵宗'],
            ['id' => 'setline_guanguan', 'name' => '灌灌套', 'flow_tag' => 'hunt', 'sect' => '巧宗'],
            ['id' => 'setline_migu', 'name' => '迷榖套', 'flow_tag' => 'evade', 'sect' => '游宗'],
            ['id' => 'setline_chiyu', 'name' => '赤鱬套', 'flow_tag' => 'flame', 'sect' => '术宗'],
            ['id' => 'setline_qingqiu', 'name' => '青丘套', 'flow_tag' => 'frost', 'sect' => '术宗'],
        ];

        $validIds = [];
        $sort = 10;
        foreach ($lines as $line) {
            foreach ([20 => 4, 40 => 6, 60 => 8] as $stage => $pieceCount) {
                $id = sprintf('%s_%d', $line['id'], $stage);
                $validIds[] = $id;

                EquipmentSet::query()->updateOrCreate(
                    ['id' => $id],
                    [
                        'set_line_id' => $line['id'],
                        'name' => sprintf('%s·%d级', $line['name'], $stage),
                        'sect' => $line['sect'],
                        'flow_tag' => $line['flow_tag'],
                        'stage' => $stage,
                        'piece_count' => $pieceCount,
                        'slot_ids' => self::BASE_SLOTS,
                        'thresholds' => $this->thresholdsFor($line['id'], $stage),
                        'description' => sprintf('%s在%d级阶段的套装效果配置。', $line['name'], $stage),
                        'is_enabled' => true,
                        'sort_order' => $sort,
                    ]
                );

                $sort += 10;
            }
        }

        EquipmentSet::query()->whereNotIn('id', $validIds)->delete();
    }

    private function thresholdsFor(string $setLineId, int $stage): array
    {
        $base = match ($setLineId) {
            'setline_lushu' => [
                ['count' => 2, 'bonuses' => [['type' => 'stat', 'stat' => 'ATK', 'val' => $stage === 20 ? 6 : ($stage === 40 ? 12 : 18)]]],
                ['count' => 4, 'bonuses' => [['type' => 'stat', 'stat' => 'CRIT_RATE', 'val' => $stage === 20 ? 3 : ($stage === 40 ? 5 : 7)]]],
                ['count' => 6, 'bonuses' => [['type' => 'stat', 'stat' => 'FINAL_DAMAGE', 'val' => 4]]],
                ['count' => 8, 'bonuses' => [['type' => 'stat', 'stat' => 'CRIT_DMG', 'val' => 8]]],
            ],
            'setline_xuangui' => [
                ['count' => 2, 'bonuses' => [['type' => 'stat', 'stat' => 'DEF', 'val' => $stage === 20 ? 8 : ($stage === 40 ? 16 : 24)]]],
                ['count' => 4, 'bonuses' => [['type' => 'stat', 'stat' => 'HP', 'val' => $stage === 20 ? 40 : ($stage === 40 ? 80 : 120)]]],
                ['count' => 6, 'bonuses' => [['type' => 'stat', 'stat' => 'FINAL_REDUCTION', 'val' => 4]]],
                ['count' => 8, 'bonuses' => [['type' => 'stat', 'stat' => 'PDEF', 'val' => 18]]],
            ],
            'setline_guanguan' => [
                ['count' => 2, 'bonuses' => [['type' => 'stat', 'stat' => 'CRIT_RATE', 'val' => $stage === 20 ? 3 : ($stage === 40 ? 5 : 7)]]],
                ['count' => 4, 'bonuses' => [['type' => 'stat', 'stat' => 'ATK_SPEED', 'val' => $stage === 20 ? 3 : ($stage === 40 ? 5 : 7)]]],
                ['count' => 6, 'bonuses' => [['type' => 'stat', 'stat' => 'RANGED_ATK', 'val' => 14]]],
                ['count' => 8, 'bonuses' => [['type' => 'stat', 'stat' => 'DODGE', 'val' => 8]]],
            ],
            'setline_migu' => [
                ['count' => 2, 'bonuses' => [['type' => 'stat', 'stat' => 'DODGE', 'val' => $stage === 20 ? 3 : ($stage === 40 ? 5 : 7)]]],
                ['count' => 4, 'bonuses' => [['type' => 'stat', 'stat' => 'HP', 'val' => $stage === 20 ? 32 : ($stage === 40 ? 68 : 108)]]],
                ['count' => 6, 'bonuses' => [['type' => 'stat', 'stat' => 'CDR', 'val' => 4]]],
                ['count' => 8, 'bonuses' => [['type' => 'stat', 'stat' => 'LOOT_BONUS_PERCENT', 'val' => 8]]],
            ],
            'setline_chiyu' => [
                ['count' => 2, 'bonuses' => [['type' => 'stat', 'stat' => 'SPELL_ATK', 'val' => $stage === 20 ? 8 : ($stage === 40 ? 16 : 24)]]],
                ['count' => 4, 'bonuses' => [['type' => 'stat', 'stat' => 'CRIT_DMG', 'val' => $stage === 20 ? 4 : ($stage === 40 ? 7 : 10)]]],
                ['count' => 6, 'bonuses' => [['type' => 'stat', 'stat' => 'FINAL_DAMAGE', 'val' => 4]]],
                ['count' => 8, 'bonuses' => [['type' => 'stat', 'stat' => 'SP', 'val' => 18]]],
            ],
            default => [
                ['count' => 2, 'bonuses' => [['type' => 'stat', 'stat' => 'MDEF', 'val' => $stage === 20 ? 8 : ($stage === 40 ? 16 : 24)]]],
                ['count' => 4, 'bonuses' => [['type' => 'stat', 'stat' => 'CDR', 'val' => $stage === 20 ? 3 : ($stage === 40 ? 5 : 7)]]],
                ['count' => 6, 'bonuses' => [['type' => 'stat', 'stat' => 'SPELL_ATK', 'val' => 12]]],
                ['count' => 8, 'bonuses' => [['type' => 'stat', 'stat' => 'FINAL_REDUCTION', 'val' => 4]]],
            ],
        };

        return array_values(array_filter($base, fn (array $row): bool => $row['count'] <= ($stage === 20 ? 4 : ($stage === 40 ? 6 : 8))));
    }
}
