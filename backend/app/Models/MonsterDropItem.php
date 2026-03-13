<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonsterDropItem extends Model
{
    protected $fillable = [
        'monster_id',
        'item_id',
        'drop_type',
        'count_min',
        'count_max',
        'drop_rate',
        'sort_order',
        'is_enabled',
        'remark',
    ];

    protected $casts = [
        'count_min' => 'integer',
        'count_max' => 'integer',
        'drop_rate' => 'float',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    public function monster(): BelongsTo
    {
        return $this->belongsTo(Monster::class, 'monster_id', 'monster_id');
    }
}
