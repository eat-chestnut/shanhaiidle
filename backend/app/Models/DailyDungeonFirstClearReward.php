<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyDungeonFirstClearReward extends Model
{
    protected $fillable = [
        'dungeon_level_id',
        'item_id',
        'count',
        'sort_order',
        'is_enabled',
        'remark',
    ];

    protected $casts = [
        'count' => 'integer',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    public function level(): BelongsTo
    {
        return $this->belongsTo(DailyDungeonLevel::class, 'dungeon_level_id', 'dungeon_level_id');
    }
}
