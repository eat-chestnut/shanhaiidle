<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StageDifficultyFirstClearReward extends Model
{
    protected $fillable = [
        'difficulty_id',
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

    public function difficulty(): BelongsTo
    {
        return $this->belongsTo(MainStageDifficulty::class, 'difficulty_id', 'difficulty_id');
    }
}
