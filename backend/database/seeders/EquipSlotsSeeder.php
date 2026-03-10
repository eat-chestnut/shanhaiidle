<?php

namespace Database\Seeders;

use App\Models\EquipSlot;
use Illuminate\Database\Seeder;

class EquipSlotsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['slot_id' => 'main_weapon', 'slot_name' => '主武器', 'slot_type' => 'equipment', 'unlock_level' => 1, 'equip_limit' => 1, 'is_set_slot' => true, 'can_drop_blue_gear' => true, 'can_craft' => true, 'can_exchange' => false, 'can_star_up' => true, 'can_rank_up' => true, 'sort_order' => 10],
            ['slot_id' => 'off_weapon', 'slot_name' => '副武器', 'slot_type' => 'equipment', 'unlock_level' => 1, 'equip_limit' => 1, 'is_set_slot' => true, 'can_drop_blue_gear' => true, 'can_craft' => true, 'can_exchange' => false, 'can_star_up' => true, 'can_rank_up' => true, 'sort_order' => 20],
            ['slot_id' => 'armor', 'slot_name' => '盔甲', 'slot_type' => 'equipment', 'unlock_level' => 1, 'equip_limit' => 1, 'is_set_slot' => true, 'can_drop_blue_gear' => true, 'can_craft' => true, 'can_exchange' => false, 'can_star_up' => true, 'can_rank_up' => true, 'sort_order' => 30],
            ['slot_id' => 'belt', 'slot_name' => '腰带', 'slot_type' => 'equipment', 'unlock_level' => 1, 'equip_limit' => 1, 'is_set_slot' => true, 'can_drop_blue_gear' => true, 'can_craft' => true, 'can_exchange' => false, 'can_star_up' => true, 'can_rank_up' => true, 'sort_order' => 40],
            ['slot_id' => 'shoes', 'slot_name' => '鞋子', 'slot_type' => 'equipment', 'unlock_level' => 1, 'equip_limit' => 1, 'is_set_slot' => true, 'can_drop_blue_gear' => true, 'can_craft' => true, 'can_exchange' => false, 'can_star_up' => true, 'can_rank_up' => true, 'sort_order' => 50],
            ['slot_id' => 'gloves', 'slot_name' => '护手', 'slot_type' => 'equipment', 'unlock_level' => 1, 'equip_limit' => 1, 'is_set_slot' => true, 'can_drop_blue_gear' => true, 'can_craft' => true, 'can_exchange' => false, 'can_star_up' => true, 'can_rank_up' => true, 'sort_order' => 60],
            ['slot_id' => 'helm', 'slot_name' => '头盔', 'slot_type' => 'equipment', 'unlock_level' => 1, 'equip_limit' => 1, 'is_set_slot' => true, 'can_drop_blue_gear' => true, 'can_craft' => true, 'can_exchange' => false, 'can_star_up' => true, 'can_rank_up' => true, 'sort_order' => 70],
            ['slot_id' => 'necklace', 'slot_name' => '项链', 'slot_type' => 'equipment', 'unlock_level' => 1, 'equip_limit' => 1, 'is_set_slot' => true, 'can_drop_blue_gear' => true, 'can_craft' => true, 'can_exchange' => false, 'can_star_up' => true, 'can_rank_up' => true, 'sort_order' => 80],
            ['slot_id' => 'ring', 'slot_name' => '戒指', 'slot_type' => 'accessory', 'unlock_level' => 1, 'equip_limit' => 2, 'is_set_slot' => false, 'can_drop_blue_gear' => false, 'can_craft' => true, 'can_exchange' => false, 'can_star_up' => true, 'can_rank_up' => true, 'sort_order' => 90],
            ['slot_id' => 'bracelet', 'slot_name' => '手镯', 'slot_type' => 'accessory', 'unlock_level' => 1, 'equip_limit' => 2, 'is_set_slot' => false, 'can_drop_blue_gear' => false, 'can_craft' => true, 'can_exchange' => false, 'can_star_up' => true, 'can_rank_up' => true, 'sort_order' => 100],
            ['slot_id' => 'talisman', 'slot_name' => '护身符', 'slot_type' => 'special', 'unlock_level' => 40, 'equip_limit' => 1, 'is_set_slot' => false, 'can_drop_blue_gear' => false, 'can_craft' => false, 'can_exchange' => true, 'can_star_up' => true, 'can_rank_up' => true, 'sort_order' => 110],
        ];

        foreach ($rows as $row) {
            $row['is_enabled'] = true;
            EquipSlot::query()->updateOrCreate(
                ['slot_id' => $row['slot_id']],
                $row,
            );
        }
    }
}
