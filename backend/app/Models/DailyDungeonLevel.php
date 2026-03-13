<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyDungeonLevel extends Model
{
    protected $table = 'daily_dungeon_levels';

    protected $fillable = [
        'dungeon_level_id',
        'dungeon_id',
        'level_no',
        'level_name',
        'recommended_level',
        'recommended_power',
        'is_max_level',
        'summary',
        'sort_order',
        'is_enabled',
        'remark',
    ];

    protected $casts = [
        'level_no' => 'integer',
        'recommended_level' => 'integer',
        'recommended_power' => 'integer',
        'is_max_level' => 'boolean',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    public function dungeon(): BelongsTo
    {
        return $this->belongsTo(DailyDungeon::class, 'dungeon_id', 'dungeon_id');
    }

    public function monsterEntries(): HasMany
    {
        return $this->hasMany(DailyDungeonLevelMonster::class, 'dungeon_level_id', 'dungeon_level_id')->orderBy('sort_order');
    }

    public function normalMonsters(): HasMany
    {
        return $this->monsterEntries()->where('spawn_type', 'normal');
    }

    public function eliteMonsters(): HasMany
    {
        return $this->monsterEntries()->where('spawn_type', 'elite');
    }

    public function bossMonsters(): HasMany
    {
        return $this->monsterEntries()->where('spawn_type', 'boss');
    }

    public function upgradeCosts(): HasMany
    {
        return $this->hasMany(DailyDungeonUpgradeCost::class, 'dungeon_level_id', 'dungeon_level_id')->orderBy('sort_order');
    }

    public function firstClearRewards(): HasMany
    {
        return $this->hasMany(DailyDungeonFirstClearReward::class, 'dungeon_level_id', 'dungeon_level_id')->orderBy('sort_order');
    }
}
