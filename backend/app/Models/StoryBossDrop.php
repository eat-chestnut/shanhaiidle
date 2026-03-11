<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoryBossDrop extends Model
{
    protected $fillable = [
        'boss_drop_id',
        'boss_id',
        'drop_type',
        'item_id',
        'item_name',
        'item_type',
        'count_min',
        'count_max',
        'probability',
        'first_clear_only',
        'use_desc',
        'icon_path',
        'sort_order',
        'is_enabled',
    ];

    protected $casts = [
        'count_min' => 'integer',
        'count_max' => 'integer',
        'probability' => 'float',
        'first_clear_only' => 'boolean',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];
}
