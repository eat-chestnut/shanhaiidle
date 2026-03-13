<?php

namespace App\Models;

use App\Support\PlayerEquipmentInstanceModuleSupport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PlayerEquipmentInstance extends Model
{
    protected $table = 'player_equipment_instances';

    public const SOURCE_TYPE_OPTIONS = [
        'set_equipment' => '套装装备',
        'blue_equipment' => '蓝装',
        'common_equipment' => '普通装备',
    ];

    public const SLOT_TYPE_OPTIONS = [
        'main_weapon' => '主武器',
        'sub_weapon' => '副武器',
        'armor' => '衣甲',
        'leg' => '下装',
        'shoe' => '鞋子',
        'cloak' => '披风',
        'helmet' => '头盔',
        'necklace' => '项链',
        'bracelet_1' => '手镯1',
        'bracelet_2' => '手镯2',
        'ring_1' => '戒指1',
        'ring_2' => '戒指2',
        'talisman' => '护符',
    ];

    public const SET_LEVEL_OPTIONS = [
        20 => '20级',
        40 => '40级',
        50 => '50级',
        60 => '60级',
    ];

    public const MAX_STAR_BY_SET_LEVEL = [
        20 => 3,
        40 => 6,
        50 => 8,
        60 => 10,
    ];

    public const SET_COUNT_EXCLUDED_SLOT_TYPES = [
        'talisman',
    ];

    public const TALISMAN_STAR_LINK_EXCLUDED_SLOT_TYPES = [
        'talisman',
    ];

    public const SET_COUNT_TRACKED_SLOT_TYPES = [
        'main_weapon',
        'sub_weapon',
        'armor',
        'leg',
        'shoe',
        'cloak',
        'helmet',
        'necklace',
        'bracelet_1',
        'bracelet_2',
        'ring_1',
        'ring_2',
    ];

    public const TALISMAN_STAR_LINK_TRACKED_SLOT_TYPES = self::SET_COUNT_TRACKED_SLOT_TYPES;

    protected $fillable = [
        'player_id',
        'instance_id',
        'item_id',
        'equipment_source_type',
        'slot_type',
        'set_id',
        'set_level',
        'star',
        'max_star',
        'quality',
        'rarity',
        'is_locked',
        'is_equipped',
        'obtained_at',
    ];

    protected $casts = [
        'player_id' => 'integer',
        'set_level' => 'integer',
        'star' => 'integer',
        'max_star' => 'integer',
        'is_locked' => 'boolean',
        'is_equipped' => 'boolean',
        'obtained_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            $model->fill(PlayerEquipmentInstanceModuleSupport::normalizeInstanceRowOrFail(
                $model->attributesToArray(),
                'player_equipment_instance'
            ));
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id', 'item_id');
    }

    public function loadout(): HasOne
    {
        return $this->hasOne(PlayerEquipmentLoadout::class, 'instance_id', 'instance_id');
    }

    public function gemSlots(): HasMany
    {
        return $this->hasMany(PlayerEquipmentGemSlot::class, 'instance_id', 'instance_id');
    }
}
