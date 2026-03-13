<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Milestone extends Model
{
    protected $table = 'milestones';

    public const CONDITION_TYPE_OPTIONS = [
        'player_level_reached' => '玩家等级达成',
        'chapter_cleared' => '主线章节通关',
    ];

    protected $fillable = [
        'milestone_id',
        'title',
        'display_name',
        'condition_type',
        'condition_value',
        'pre_milestone_id',
        'reward_item_id',
        'reward_count',
        'icon',
        'summary',
        'sort_order',
        'is_enabled',
        'remark',
    ];

    protected $casts = [
        'reward_count' => 'integer',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    public function preMilestone(): BelongsTo
    {
        return $this->belongsTo(self::class, 'pre_milestone_id', 'milestone_id');
    }

    public function rewardItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'reward_item_id', 'item_id');
    }

    public function playerStates(): HasMany
    {
        return $this->hasMany(PlayerMilestone::class, 'milestone_id', 'milestone_id');
    }
}
