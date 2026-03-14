<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RewardGrantLog extends Model
{
    protected $table = 'reward_grant_logs';

    protected $fillable = [
        'player_id',
        'battle_id',
        'item_id',
        'count',
        'reward_source_type',
        'reward_source_id',
        'grant_batch_id',
    ];

    protected $casts = [
        'count' => 'integer',
    ];
}
