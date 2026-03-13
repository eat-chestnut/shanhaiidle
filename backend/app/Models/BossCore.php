<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BossCore extends Model
{
    public const RECOMMENDED_SECT_OPTIONS = [
        'none' => '无',
        'bing' => '兵宗',
        'jingang' => '金刚宗',
        'fulu' => '符箓宗',
    ];

    public const RECOMMENDED_BUILD_OPTIONS = [
        'burst' => '爆发',
        'survival' => '生存',
        'skill_loop' => '技能循环',
        'boss_hunt' => 'Boss向',
        'burn' => '燃烧',
        'frost' => '寒霜',
    ];

    public const EFFECT_KEY_OPTIONS = [
        'bonus_burn_damage' => '燃烧伤害加成',
        'bonus_frost_damage' => '寒霜伤害加成',
        'bonus_skill_dmg' => '技能伤害加成',
        'bonus_boss_dmg' => 'Boss伤害加成',
        'bonus_low_hp_shield' => '低血量护盾',
        'bonus_melee_atk' => '近战攻击加成',
        'bonus_final_damage' => '最终伤害加成',
    ];

    public const VALUE_TYPE_OPTIONS = [
        'flat' => '固定值',
        'percent' => '百分比',
    ];

    protected $fillable = [
        'core_id',
        'item_id',
        'core_name',
        'display_name',
        'source_boss_id',
        'quality',
        'rarity',
        'recommended_sect',
        'recommended_build',
        'icon',
        'summary',
        'drop_rate_note',
        'sort_order',
        'is_enabled',
        'remark',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id', 'item_id');
    }

    public function sourceBoss(): BelongsTo
    {
        return $this->belongsTo(Monster::class, 'source_boss_id', 'monster_id');
    }

    public function effects(): HasMany
    {
        return $this->hasMany(BossCoreEffect::class, 'core_id', 'core_id')->orderBy('sort_order');
    }
}
