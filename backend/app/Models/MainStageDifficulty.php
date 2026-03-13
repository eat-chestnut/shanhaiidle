<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MainStageDifficulty extends Model
{
    protected $table = 'main_stage_difficulties';

    protected $primaryKey = 'difficulty_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'difficulty_id',
        'chapter_id',
        'difficulty_code',
        'difficulty_name',
        'remark',
        'sort_order',
        'is_enabled',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(MainStageChapter::class, 'chapter_id', 'chapter_id');
    }

    public function monsterEntries(): HasMany
    {
        return $this->hasMany(StageDifficultyMonster::class, 'difficulty_id', 'difficulty_id')->orderBy('sort_order');
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

    public function firstClearRewards(): HasMany
    {
        return $this->hasMany(StageDifficultyFirstClearReward::class, 'difficulty_id', 'difficulty_id')->orderBy('sort_order');
    }
}
