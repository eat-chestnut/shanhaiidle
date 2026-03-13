<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GiftPack extends Model
{
    protected $table = 'gift_packs';

    public const PACK_TYPE_OPTIONS = [
        'stage_reward_pack' => '主线首通礼包',
        'growth_pack' => '成长礼包',
        'shop_pack' => '商城礼包',
        'milestone_pack' => '里程碑礼包',
    ];

    public const PACK_MODE_OPTIONS = [
        'fixed' => '固定礼包',
        'select_one' => '单选礼包',
        'select_multi' => '多选礼包',
    ];

    public const OPEN_MODE_OPTIONS = [
        'manual' => '手动开启',
        'auto_grant' => '自动发放',
    ];

    protected $fillable = [
        'pack_id',
        'item_id',
        'pack_name',
        'display_name',
        'pack_type',
        'pack_mode',
        'open_mode',
        'select_count_min',
        'select_count_max',
        'desc',
        'icon',
        'is_enabled',
        'sort_order',
        'remark',
    ];

    protected $casts = [
        'select_count_min' => 'integer',
        'select_count_max' => 'integer',
        'is_enabled' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function carrierItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id', 'item_id');
    }

    public function contentItems(): HasMany
    {
        return $this->hasMany(GiftPackItem::class, 'pack_id', 'pack_id')->orderBy('sort_order')->orderBy('id');
    }
}
