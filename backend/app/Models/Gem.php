<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Gem extends Model
{
    protected $table = 'gems';

    public const GEM_TYPE_OPTIONS = [
        'attr_gem' => '属性宝石',
        'skill_gem' => '技能宝石',
    ];

    public const VALUE_TYPE_OPTIONS = [
        'flat' => '固定值',
        'percent' => '百分比',
    ];

    public const SLOT_GROUP_OPTIONS = [
        'attr_only' => '属性孔',
        'skill_only' => '技能孔',
    ];

    public const ATTR_STAT_KEY_OPTIONS = [
        'MELEE_ATK' => '近战攻击（MELEE_ATK）',
        'HP' => '生命（HP）',
        'DEF' => '防御（DEF）',
        'CRIT_RATE' => '暴击率（CRIT_RATE）',
        'CRIT_DMG' => '暴击伤害（CRIT_DMG）',
        'ATK_SPEED' => '攻击速度（ATK_SPEED）',
        'SKILL_DMG' => '技能伤害（SKILL_DMG）',
        'BOSS_DMG' => 'Boss 伤害（BOSS_DMG）',
        'LIFESTEAL' => '吸血（LIFESTEAL）',
    ];

    public const SKILL_STAT_KEY_OPTIONS = [
        'skill_frost_focus' => '寒霜专精（skill_frost_focus）',
        'skill_flame_focus' => '炎火专精（skill_flame_focus）',
        'skill_guard_focus' => '守御专精（skill_guard_focus）',
    ];

    protected $fillable = [
        'gem_id',
        'item_id',
        'gem_name',
        'display_name',
        'gem_type',
        'stat_key',
        'value_type',
        'value',
        'quality',
        'rarity',
        'slot_group',
        'unlock_level',
        'icon',
        'summary',
        'sort_order',
        'is_enabled',
        'remark',
    ];

    protected $casts = [
        'value' => 'float',
        'unlock_level' => 'integer',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id', 'item_id');
    }
}
