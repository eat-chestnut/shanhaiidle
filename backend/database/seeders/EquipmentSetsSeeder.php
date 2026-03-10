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
            ['id' => 'setline_kuangzhan', 'name' => '狂战套', 'flow_tag' => 'berserk', 'sect' => '兵宗'],
            ['id' => 'setline_fangzhan', 'name' => '防战套', 'flow_tag' => 'guard', 'sect' => '兵宗'],
            ['id' => 'setline_liesha', 'name' => '猎杀号套', 'flow_tag' => 'hunt', 'sect' => '体宗'],
            ['id' => 'setline_zhuying', 'name' => '逐影套', 'flow_tag' => 'shadow', 'sect' => '法宗'],
            ['id' => 'setline_fentian', 'name' => '焚天套', 'flow_tag' => 'flame', 'sect' => '法宗'],
            ['id' => 'setline_xuanshuang', 'name' => '玄霜套', 'flow_tag' => 'frost', 'sect' => '法宗'],
        ];

        $sort = 10;
        foreach ($lines as $line) {
            foreach ([20 => 4, 40 => 6, 60 => 8] as $stage => $pieceCount) {
                $id = sprintf('%s_%d', $line['id'], $stage);
                $thresholds = $this->defaultThresholds($stage);

                EquipmentSet::query()->updateOrCreate(
                    ['id' => $id],
                    [
                        'set_line_id' => $line['id'],
                        'name' => $line['name'] . sprintf('·%d级', $stage),
                        'sect' => $line['sect'],
                        'flow_tag' => $line['flow_tag'],
                        'stage' => $stage,
                        'piece_count' => $pieceCount,
                        'slot_ids' => self::BASE_SLOTS,
                        'max_pieces' => $pieceCount,
                        'thresholds' => $thresholds,
                        'description' => sprintf('%s（%d级）套装效果', $line['name'], $stage),
                        'is_enabled' => true,
                        'sort_order' => $sort,
                    ]
                );

                $sort += 10;
            }
        }

        $validIds = [];
        foreach ($lines as $line) {
            foreach ([20, 40, 60] as $stage) {
                $validIds[] = sprintf('%s_%d', $line['id'], $stage);
            }
        }

        EquipmentSet::query()
            ->whereNotIn('id', $validIds)
            ->delete();
    }

    private function defaultThresholds(int $stage): array
    {
        if ($stage === 20) {
            return [
                ['count' => 2, 'bonuses' => [['type' => 'stat', 'stat' => 'ATK', 'val' => 3]]],
                ['count' => 4, 'bonuses' => [['type' => 'stat', 'stat' => 'DEF', 'val' => 3]]],
            ];
        }

        if ($stage === 40) {
            return [
                ['count' => 2, 'bonuses' => [['type' => 'stat', 'stat' => 'ATK', 'val' => 6]]],
                ['count' => 4, 'bonuses' => [['type' => 'stat', 'stat' => 'DEF', 'val' => 6]]],
                ['count' => 6, 'bonuses' => [['type' => 'stat', 'stat' => 'CRIT_PERCENT', 'val' => 4]]],
            ];
        }

        return [
            ['count' => 2, 'bonuses' => [['type' => 'stat', 'stat' => 'ATK', 'val' => 10]]],
            ['count' => 4, 'bonuses' => [['type' => 'stat', 'stat' => 'DEF', 'val' => 10]]],
            ['count' => 6, 'bonuses' => [['type' => 'stat', 'stat' => 'CRIT_PERCENT', 'val' => 6]]],
            ['count' => 8, 'bonuses' => [['type' => 'stat', 'stat' => 'LOOT_BONUS_PERCENT', 'val' => 6]]],
        ];
    }
}
