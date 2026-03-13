<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MainStageDifficulty extends Model
{
    protected $table = 'main_stage_difficulties';

    protected $primaryKey = 'difficulty_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'difficulty_id',
        'chapter_id',
        'difficulty_code',
        'difficulty_name',
        'normal_monster_pool_id',
        'elite_monster_pool_id',
        'boss_id',
        'drop_preview_group_id',
        'first_clear_reward_group_id',
        'remark',
        'sort_order',
        'is_enabled',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(MainStageChapter::class, 'chapter_id', 'chapter_id');
    }
}
