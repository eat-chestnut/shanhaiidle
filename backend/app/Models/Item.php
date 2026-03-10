<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $table = 'items';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'type',
        'sub_type',
        'material_type',
        'rarity',
        'icon',
        'trait',
        'desc',
        'gem_effect',
        'effect_type',
        'target_scope',
        'effect_payload',
        'drop_unlock_level',
        'socket_limit',
        'source_tags',
        'use_tags',
        'stack_limit',
        'can_compose',
        'can_reforge',
        'is_enabled',
        'sort_order',
    ];

    protected $casts = [
        'gem_effect' => 'array',
        'effect_payload' => 'array',
        'socket_limit' => 'array',
        'source_tags' => 'array',
        'use_tags' => 'array',
        'drop_unlock_level' => 'integer',
        'stack_limit' => 'integer',
        'can_compose' => 'boolean',
        'can_reforge' => 'boolean',
        'is_enabled' => 'boolean',
        'sort_order' => 'integer',
    ];
}
