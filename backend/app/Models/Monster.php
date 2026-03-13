<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Monster extends Model
{
    protected $table = 'monsters';

    protected $fillable = [
        'monster_id',
        'monster_name',
        'display_name',
        'monster_type',
        'chapter_id',
        'display_stage_id',
        'family',
        'title',
        'desc',
        'icon',
        'sprite',
        'prefab_key',
        'level',
        'hp',
        'atk',
        'def',
        'speed',
        'move_speed',
        'attack_range',
        'attack_interval',
        'aggro_range',
        'ai_type',
        'rarity_tag',
        'is_enabled',
        'sort_order',
        'remark',
    ];

    protected $casts = [
        'level' => 'integer',
        'hp' => 'integer',
        'atk' => 'integer',
        'def' => 'integer',
        'speed' => 'integer',
        'move_speed' => 'integer',
        'attack_range' => 'integer',
        'attack_interval' => 'float',
        'aggro_range' => 'integer',
        'is_enabled' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function skillBindings(): HasMany
    {
        return $this->hasMany(MonsterSkillBinding::class, 'monster_id', 'monster_id')->orderBy('sort_order');
    }

    public function dropBindings(): HasMany
    {
        return $this->hasMany(MonsterDropBinding::class, 'monster_id', 'monster_id')->orderBy('sort_order');
    }

    public function bossProfile(): HasOne
    {
        return $this->hasOne(MonsterBossProfile::class, 'monster_id', 'monster_id');
    }
}
