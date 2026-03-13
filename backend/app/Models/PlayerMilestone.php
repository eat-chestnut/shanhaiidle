<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerMilestone extends Model
{
    protected $table = 'player_milestones';

    protected $fillable = [
        'player_id',
        'milestone_id',
        'is_unlocked',
        'is_claimed',
        'claimed_at',
    ];

    protected $casts = [
        'is_unlocked' => 'boolean',
        'is_claimed' => 'boolean',
        'claimed_at' => 'datetime',
    ];

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class, 'milestone_id', 'milestone_id');
    }
}
