<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Talisman extends Model
{
    protected $table = 'talismans';

    public const TALISMAN_TYPE_OPTIONS = [
        'common_talisman' => '通用护符',
        'sect_talisman' => '宗门护符',
    ];

    public const RECOMMENDED_SECT_OPTIONS = [
        'none' => '无',
        'bing' => '兵宗',
        'jingang' => '金刚宗',
        'fulu' => '符箓宗',
    ];

    public const EFFECT_KEY_OPTIONS = [
        'bonus_melee_atk' => '近战攻击加成（bonus_melee_atk）',
        'bonus_hp' => '生命加成（bonus_hp）',
        'bonus_skill_dmg' => '技能伤害加成（bonus_skill_dmg）',
        'bonus_damage_reduction' => '减伤加成（bonus_damage_reduction）',
        'bonus_flame_damage' => '火焰伤害加成（bonus_flame_damage）',
        'bonus_frost_damage' => '寒霜伤害加成（bonus_frost_damage）',
        'bonus_guard_shield' => '护盾加成（bonus_guard_shield）',
        'bonus_crit_dmg' => '暴击伤害加成（bonus_crit_dmg）',
        'bonus_final_damage' => '最终伤害加成（bonus_final_damage）',
    ];

    public const VALUE_TYPE_OPTIONS = [
        'flat' => '固定值',
        'percent' => '百分比',
    ];

    public const FIXED_TIER_NUMBERS = [1, 2, 3];

    public const STAR_LINK_THRESHOLDS = [6, 8, 9, 10];

    protected $fillable = [
        'talisman_id',
        'item_id',
        'talisman_name',
        'display_name',
        'talisman_type',
        'recommended_sect',
        'quality',
        'rarity',
        'unlock_level',
        'icon',
        'summary',
        'sort_order',
        'is_enabled',
        'remark',
    ];

    protected $casts = [
        'unlock_level' => 'integer',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id', 'item_id');
    }

    public function tiers(): HasMany
    {
        return $this->hasMany(TalismanTier::class, 'talisman_id', 'talisman_id')->orderBy('tier_no')->orderBy('sort_order')->orderBy('id');
    }

    public function upgradeCosts(): HasMany
    {
        return $this->hasMany(TalismanTierUpgradeCost::class, 'talisman_id', 'talisman_id')
            ->orderBy('tier_no')
            ->orderBy('target_tier_no')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function starLinks(): HasMany
    {
        return $this->hasMany(TalismanStarLink::class, 'talisman_id', 'talisman_id')
            ->orderBy('tier_no')
            ->orderBy('required_equipment_star')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
