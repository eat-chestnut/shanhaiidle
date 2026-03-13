<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopGood extends Model
{
    protected $table = 'shop_goods';

    public const SHOP_TAB_OPTIONS = [
        'daily' => '日常补给',
        'growth' => '成长商品',
        'sect' => '宗门兑换',
        'special' => '特殊推荐',
    ];

    public const GOODS_TYPE_OPTIONS = [
        'direct_item' => '直接道具',
        'gift_pack' => '礼包道具',
    ];

    public const BUY_LIMIT_TYPE_OPTIONS = [
        'none' => '不限购',
        'daily' => '每日限购',
        'weekly' => '每周限购',
        'lifetime' => '终身限购',
    ];

    protected $fillable = [
        'goods_id',
        'title',
        'display_name',
        'shop_tab',
        'goods_type',
        'reward_item_id',
        'reward_count',
        'price_item_id',
        'price_amount',
        'original_price_amount',
        'unlock_level',
        'buy_limit_type',
        'buy_limit_value',
        'is_recommended',
        'desc',
        'remark',
        'sort_order',
        'icon',
        'is_enabled',
    ];

    protected $casts = [
        'reward_count' => 'integer',
        'price_amount' => 'integer',
        'original_price_amount' => 'integer',
        'unlock_level' => 'integer',
        'buy_limit_value' => 'integer',
        'is_recommended' => 'boolean',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    public function rewardItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'reward_item_id', 'item_id');
    }

    public function priceItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'price_item_id', 'item_id');
    }
}
