<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StarterGift extends Model
{
    protected $fillable = [
        'gift_id',
        'unlock_level',
        'gift_name',
        'gift_role',
        'open_copy',
        'rewards',
        'must_claim',
        'is_free',
        'icon_path',
        'banner_path',
        'sort_order',
        'is_enabled',
    ];

    protected $casts = [
        'unlock_level' => 'integer',
        'rewards' => 'array',
        'must_claim' => 'boolean',
        'is_free' => 'boolean',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];
}
