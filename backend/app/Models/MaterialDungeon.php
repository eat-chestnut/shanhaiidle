<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialDungeon extends Model
{
    protected $table = 'material_dungeons';

    protected $fillable = [
        'dungeon_id',
        'name',
        'dungeon_type',
        'unlock_level',
        'unlock_stage_id',
        'display_rewards',
        'layer_rules',
        'level_configs',
        'stamina_cost',
        'daily_limit',
        'description',
        'sort_order',
        'is_enabled',
    ];

    protected $casts = [
        'unlock_level' => 'integer',
        'display_rewards' => 'array',
        'layer_rules' => 'array',
        'level_configs' => 'array',
        'stamina_cost' => 'integer',
        'daily_limit' => 'integer',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];
}
