<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StageDifficultyMonster extends Model
{
    protected $fillable = [
        'difficulty_id',
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

    public function difficulty(): BelongsTo
    {
        return $this->belongsTo(MainStageDifficulty::class, 'difficulty_id', 'difficulty_id');
    }

    public function monster(): BelongsTo
    {
        return $this->belongsTo(Monster::class, 'monster_id', 'monster_id');
    }
}
