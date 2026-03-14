<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BattleResult extends Model
{
    protected $table = 'battle_results';

    protected $fillable = [
        'battle_id',
        'player_id',
        'battle_type',
        'stage_id',
        'difficulty_id',
        'battle_result',
        'elapsed_ticks',
        'remaining_player_hp',
        'remaining_enemy_count',
        'cleared_wave_count',
        'is_settled',
        'settled_at',
    ];

    protected $casts = [
        'elapsed_ticks' => 'integer',
        'remaining_player_hp' => 'integer',
        'remaining_enemy_count' => 'integer',
        'cleared_wave_count' => 'integer',
        'is_settled' => 'boolean',
        'settled_at' => 'datetime',
    ];
}
