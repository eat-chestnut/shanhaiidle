<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EquipmentSet extends Model
{
    protected $table = 'equipment_sets';

    public const SET_LEVEL_OPTIONS = [
        20 => '20级',
        40 => '40级',
        50 => '50级',
        60 => '60级',
    ];

    public const PIECE_TOTAL_BY_LEVEL = [
        20 => 4,
        40 => 6,
        50 => 8,
        60 => 8,
    ];

    public const THRESHOLDS_BY_LEVEL = [
        20 => [2, 4],
        40 => [2, 4, 6],
        50 => [2, 4, 6, 8],
        60 => [2, 4, 6, 8],
    ];

    public const SLOT_TYPE_OPTIONS = [
        'main_weapon' => '主武器',
        'sub_weapon' => '副武器',
        'armor' => '护甲',
        'shoe' => '鞋子',
        'helmet' => '头盔',
        'leg' => '护腿',
        'cloak' => '披风',
        'necklace' => '项链',
    ];

    public const SLOT_TYPES_BY_LEVEL = [
        20 => ['main_weapon', 'sub_weapon', 'armor', 'shoe'],
        40 => ['main_weapon', 'sub_weapon', 'armor', 'shoe', 'helmet', 'leg'],
        50 => ['main_weapon', 'sub_weapon', 'armor', 'shoe', 'helmet', 'leg', 'cloak', 'necklace'],
        60 => ['main_weapon', 'sub_weapon', 'armor', 'shoe', 'helmet', 'leg', 'cloak', 'necklace'],
    ];

    public const SET_TYPE_OPTIONS = [
        'combat_set' => '战斗套装',
    ];

    public const EFFECT_KEY_OPTIONS = [
        'bonus_melee_atk' => '近战攻击加成',
        'bonus_skill_dmg' => '技能伤害加成',
        'bonus_boss_dmg' => 'Boss伤害加成',
        'bonus_damage_reduction' => '减伤加成',
        'bonus_hp' => '生命加成',
        'bonus_crit_rate' => '暴击率加成',
        'bonus_crit_dmg' => '暴击伤害加成',
        'bonus_final_damage' => '最终伤害加成',
        'bonus_frost_damage' => '寒霜伤害加成',
        'bonus_flame_damage' => '火焰伤害加成',
        'bonus_skill_cooldown_reduction' => '技能冷却缩减',
        'bonus_guard_shield' => '护盾强度加成',
    ];

    public const VALUE_TYPE_OPTIONS = [
        'flat' => '固定值',
        'percent' => '百分比',
    ];

    protected $fillable = [
        'set_id',
        'set_name',
        'display_name',
        'set_level',
        'piece_total',
        'set_type',
        'quality',
        'rarity',
        'unlock_level',
        'icon',
        'summary',
        'source_desc',
        'remark',
        'is_enabled',
        'sort_order',
    ];

    protected $casts = [
        'set_level' => 'integer',
        'piece_total' => 'integer',
        'unlock_level' => 'integer',
        'is_enabled' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(EquipmentSetItem::class, 'set_id', 'set_id');
    }

    public function effects(): HasMany
    {
        return $this->hasMany(EquipmentSetEffect::class, 'set_id', 'set_id');
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(EquipmentSetCraftRecipe::class, 'set_id', 'set_id');
    }

    public static function deriveLineId(string $setId): string
    {
        $derived = preg_replace('/_(20|40|50|60)$/', '', trim($setId));

        return is_string($derived) && $derived !== '' ? $derived : trim($setId);
    }

    public function getSetLineIdAttribute(): string
    {
        return self::deriveLineId((string) $this->set_id);
    }

    public function getStageAttribute(): int
    {
        return (int) $this->set_level;
    }

    public function getNameAttribute(): string
    {
        return (string) $this->display_name;
    }

    public function getPieceCountAttribute(): int
    {
        return (int) $this->piece_total;
    }
}
