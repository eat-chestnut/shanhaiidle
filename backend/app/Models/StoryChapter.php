<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoryChapter extends Model
{
    protected $fillable = [
        'chapter_id',
        'volume_name',
        'chapter_no',
        'chapter_name',
        'chapter_role',
        'map_id',
        'level_min',
        'level_max',
        'intro_copy',
        'objective_copy',
        'boss_intro_copy',
        'boss_id',
        'clear_copy',
        'next_hook_copy',
        'icon_path',
        'banner_path',
        'sort_order',
        'is_enabled',
    ];

    protected $casts = [
        'chapter_no' => 'integer',
        'level_min' => 'integer',
        'level_max' => 'integer',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];
}
