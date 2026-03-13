<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonsterSkillBinding extends Model
{
    protected $fillable = [
        'monster_id',
        'skill_id',
        'slot_type',
        'trigger_priority',
        'phase_limit',
        'sort_order',
        'is_enabled',
        'remark',
    ];

    protected $casts = [
        'trigger_priority' => 'integer',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    public function monster(): BelongsTo
    {
        return $this->belongsTo(Monster::class, 'monster_id', 'monster_id');
    }
}
