<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerMainStageFirstClearClaim extends Model
{
    protected $table = 'player_main_stage_first_clear_claims';

    protected $fillable = [
        'player_id',
        'stage_id',
        'difficulty_id',
        'battle_result_id',
        'claimed_at',
    ];

    protected $casts = [
        'claimed_at' => 'datetime',
    ];

    public function battleResult(): BelongsTo
    {
        return $this->belongsTo(BattleResult::class, 'battle_result_id');
    }
}
