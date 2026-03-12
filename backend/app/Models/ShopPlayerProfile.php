<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopPlayerProfile extends Model
{
    protected $table = 'shop_player_profiles';

    protected $fillable = [
        'player_id',
        'nickname',
        'level',
        'exp',
        'gold',
        'crystal',
        'contribution',
        'current_stage_id',
        'current_difficulty',
        'highest_cleared_stage_id',
        'highest_cleared_difficulty',
        'free_attr_points',
        'skill_points',
        'current_sect_id',
        'attrs_json',
        'inventory',
        'equipment',
        'claimed_milestones',
        'patrol_summary',
        'task_summary',
    ];

    protected $casts = [
        'level' => 'integer',
        'exp' => 'integer',
        'gold' => 'integer',
        'crystal' => 'integer',
        'contribution' => 'integer',
        'current_difficulty' => 'integer',
        'highest_cleared_difficulty' => 'integer',
        'free_attr_points' => 'integer',
        'skill_points' => 'integer',
        'attrs_json' => 'array',
        'inventory' => 'array',
        'equipment' => 'array',
        'claimed_milestones' => 'array',
        'patrol_summary' => 'array',
        'task_summary' => 'array',
    ];
}
