<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipSlot extends Model
{
    protected $table = 'equip_slots';

    protected $fillable = [
        'slot_id',
        'slot_name',
        'slot_type',
        'unlock_level',
        'equip_limit',
        'is_set_slot',
        'can_drop_blue_gear',
        'can_craft',
        'can_exchange',
        'can_star_up',
        'can_rank_up',
        'sort_order',
        'is_enabled',
    ];

    protected $casts = [
        'unlock_level' => 'integer',
        'equip_limit' => 'integer',
        'is_set_slot' => 'boolean',
        'can_drop_blue_gear' => 'boolean',
        'can_craft' => 'boolean',
        'can_exchange' => 'boolean',
        'can_star_up' => 'boolean',
        'can_rank_up' => 'boolean',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];
}
