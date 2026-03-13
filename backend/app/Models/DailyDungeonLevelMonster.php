<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyDungeonLevelMonster extends Model
{
    protected $fillable = [
        'dungeon_level_id',
        'monster_id',
        'spawn_type',
        'weight',
        'min_count',
        'max_count',
        'sort_order',
        'is_enabled',
        'remark',
    ];

    protected $casts = [
        'weight' => 'integer',
        'min_count' => 'integer',
        'max_count' => 'integer',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    public function level(): BelongsTo
    {
        return $this->belongsTo(DailyDungeonLevel::class, 'dungeon_level_id', 'dungeon_level_id');
    }

    public function monster(): BelongsTo
    {
        return $this->belongsTo(Monster::class, 'monster_id', 'monster_id');
    }
}
