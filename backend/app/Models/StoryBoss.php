<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoryBoss extends Model
{
    protected $fillable = [
        'boss_id',
        'boss_name',
        'source_text',
        'map_id',
        'chapter_id',
        'boss_type',
        'recommend_level',
        'recommend_power',
        'lore_role',
        'visual_tags',
        'combat_tags',
        'intro_copy',
        'clear_copy',
        'icon_path',
        'portrait_path',
        'banner_path',
        'sort_order',
        'is_enabled',
    ];

    protected $casts = [
        'recommend_level' => 'integer',
        'recommend_power' => 'integer',
        'visual_tags' => 'array',
        'combat_tags' => 'array',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];
}
