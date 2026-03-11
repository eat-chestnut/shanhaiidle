<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoryMap extends Model
{
    protected $fillable = [
        'map_id',
        'map_name',
        'map_order',
        'map_type',
        'volume_name',
        'source_text',
        'level_min',
        'level_max',
        'theme_tags',
        'atmosphere_desc',
        'recommend_power',
        'unlock_condition',
        'chapter_id',
        'boss_id',
        'normal_drop_pool',
        'elite_drop_pool',
        'boss_drop_pool',
        'icon_path',
        'banner_path',
        'bg_path',
        'sort_order',
        'is_enabled',
    ];

    protected $casts = [
        'map_order' => 'integer',
        'level_min' => 'integer',
        'level_max' => 'integer',
        'theme_tags' => 'array',
        'recommend_power' => 'integer',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];
}
