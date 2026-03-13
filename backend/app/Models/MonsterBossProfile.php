<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonsterBossProfile extends Model
{
    protected $fillable = [
        'monster_id',
        'phase_count',
        'phase_rules',
        'summon_rules',
        'rage_rules',
        'weak_point_rules',
        'intro_text',
        'battle_bgm_id',
        'camera_rule',
        'entry_fx_key',
        'death_fx_key',
        'first_clear_reward_group_id',
        'story_flag_on_clear',
        'remark',
    ];

    protected $casts = [
        'phase_count' => 'integer',
        'phase_rules' => 'array',
        'summon_rules' => 'array',
        'rage_rules' => 'array',
        'weak_point_rules' => 'array',
    ];

    public function monster(): BelongsTo
    {
        return $this->belongsTo(Monster::class, 'monster_id', 'monster_id');
    }
}
