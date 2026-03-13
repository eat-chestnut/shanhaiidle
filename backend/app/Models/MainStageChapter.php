<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MainStageChapter extends Model
{
    protected $table = 'main_stage_chapters';

    protected $primaryKey = 'chapter_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'chapter_id',
        'chapter_name',
        'chapter_type',
        'chapter_flow_type',
        'is_functional_chapter',
        'has_combat',
        'has_sect_selection',
        'has_shanshen_ritual',
        'suggested_level_min',
        'suggested_level_max',
        'suggested_power',
        'mountain_name',
        'boss_display_name',
        'unlock_level',
        'unlock_prev_chapter_id',
        'sect_selection_enabled',
        'sect_selection_pool_id',
        'shanshen_ritual_enabled',
        'next_version_teaser_title',
        'next_world_key',
        'teaser_desc',
        'remark',
        'sort_order',
        'is_enabled',
    ];

    protected $casts = [
        'is_functional_chapter' => 'boolean',
        'has_combat' => 'boolean',
        'has_sect_selection' => 'boolean',
        'has_shanshen_ritual' => 'boolean',
        'sect_selection_enabled' => 'boolean',
        'shanshen_ritual_enabled' => 'boolean',
        'suggested_level_min' => 'integer',
        'suggested_level_max' => 'integer',
        'suggested_power' => 'integer',
        'unlock_level' => 'integer',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    public function difficulties(): HasMany
    {
        return $this->hasMany(MainStageDifficulty::class, 'chapter_id', 'chapter_id')->orderBy('sort_order');
    }
}
