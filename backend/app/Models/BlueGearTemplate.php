<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlueGearTemplate extends Model
{
    protected $table = 'blue_gear_templates';

    protected $fillable = [
        'template_id',
        'name',
        'blue_pool_id',
        'slot_id',
        'flow_tag',
        'required_level',
        'white_stats',
        'affix_count',
        'affix_pool_tags',
        'icon',
        'sort_order',
        'is_enabled',
    ];

    protected $casts = [
        'required_level' => 'integer',
        'white_stats' => 'array',
        'affix_count' => 'integer',
        'affix_pool_tags' => 'array',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];
}
