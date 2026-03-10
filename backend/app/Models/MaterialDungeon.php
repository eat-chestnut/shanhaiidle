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
        'layer_config',
        'drop_pools',
        'stamina_cost',
        'daily_limit',
        'description',
        'sort_order',
        'is_enabled',
    ];

    protected $casts = [
        'unlock_level' => 'integer',
        'layer_config' => 'array',
        'drop_pools' => 'array',
        'stamina_cost' => 'integer',
        'daily_limit' => 'integer',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];
}
