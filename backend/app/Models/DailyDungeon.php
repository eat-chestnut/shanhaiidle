<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyDungeon extends Model
{
    protected $table = 'daily_dungeons';

    protected $fillable = [
        'dungeon_id',
        'title',
        'display_name',
        'dungeon_type',
        'unlock_level',
        'entry_cost_item_id',
        'entry_cost_count',
        'daily_limit',
        'sweep_enabled',
        'icon',
        'summary',
        'sort_order',
        'is_enabled',
        'remark',
    ];

    protected $casts = [
        'unlock_level' => 'integer',
        'entry_cost_count' => 'integer',
        'daily_limit' => 'integer',
        'sweep_enabled' => 'boolean',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    public function levels(): HasMany
    {
        return $this->hasMany(DailyDungeonLevel::class, 'dungeon_id', 'dungeon_id')->orderBy('level_no');
    }
}
