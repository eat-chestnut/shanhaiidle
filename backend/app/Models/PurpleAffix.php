<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurpleAffix extends Model
{
    protected $table = 'purple_affix_pool';

    protected $fillable = [
        'affix_id',
        'affix_name',
        'stat',
        'slot_tags',
        'flow_tags',
        'rarity_tier',
        'min_value',
        'max_value',
        'value_mode',
        'weight',
        'unlock_level',
        'sort_order',
        'is_enabled',
    ];

    protected $casts = [
        'slot_tags' => 'array',
        'flow_tags' => 'array',
        'min_value' => 'integer',
        'max_value' => 'integer',
        'weight' => 'integer',
        'unlock_level' => 'integer',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];
}
