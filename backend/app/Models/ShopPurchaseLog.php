<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopPurchaseLog extends Model
{
    protected $table = 'shop_purchase_logs';

    public $timestamps = false;

    protected $fillable = [
        'player_id',
        'goods_id',
        'shop_type',
        'reward_item_id',
        'reward_count',
        'cost_currency_type',
        'cost_amount',
        'quantity',
        'purchased_at',
        'reset_bucket_date',
        'reset_bucket_week',
        'operator_type',
        'created_at',
    ];

    protected $casts = [
        'reward_count' => 'integer',
        'cost_amount' => 'integer',
        'quantity' => 'integer',
        'purchased_at' => 'datetime',
        'reset_bucket_date' => 'date',
        'created_at' => 'datetime',
    ];
}
