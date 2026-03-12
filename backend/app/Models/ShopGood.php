<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopGood extends Model
{
    protected $table = 'shop_goods';

    protected $fillable = [
        'goods_id',
        'shop_type',
        'title',
        'subtitle',
        'desc',
        'reward_item_id',
        'reward_count',
        'cost_currency_type',
        'cost_amount',
        'unlock_level',
        'daily_limit',
        'weekly_limit',
        'lifetime_limit',
        'sort_order',
        'icon',
        'is_enabled',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'reward_count' => 'integer',
        'cost_amount' => 'integer',
        'unlock_level' => 'integer',
        'daily_limit' => 'integer',
        'weekly_limit' => 'integer',
        'lifetime_limit' => 'integer',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];
}
