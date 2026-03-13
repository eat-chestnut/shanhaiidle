<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftPackItem extends Model
{
    protected $table = 'gift_pack_items';

    public const CONTENT_MODE_OPTIONS = [
        'fixed' => '固定内容',
        'selectable' => '自选内容',
    ];

    public const RECOMMENDED_SECT_OPTIONS = [
        'none' => '无',
        'bing' => '兵宗',
        'jingang' => '金刚宗',
        'fulu' => '符箓宗',
    ];

    protected $fillable = [
        'pack_id',
        'item_id',
        'content_mode',
        'count_min',
        'count_max',
        'weight',
        'recommended_sect',
        'display_note',
        'sort_order',
        'is_enabled',
        'remark',
    ];

    protected $casts = [
        'count_min' => 'integer',
        'count_max' => 'integer',
        'weight' => 'integer',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    public function pack(): BelongsTo
    {
        return $this->belongsTo(GiftPack::class, 'pack_id', 'pack_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id', 'item_id');
    }
}
