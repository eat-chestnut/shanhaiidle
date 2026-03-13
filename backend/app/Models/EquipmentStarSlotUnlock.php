<?php

namespace App\Models;

use App\Support\EquipmentStarModuleSupport;
use Illuminate\Database\Eloquent\Model;

class EquipmentStarSlotUnlock extends Model
{
    protected $table = 'equipment_star_slot_unlocks';

    public const SLOT_GROUP_OPTIONS = [
        'attr_only' => '属性孔',
        'skill_only' => '技能孔',
    ];

    public const STAR_SLOT_MAP = [
        3 => ['slot_index' => 1, 'slot_group' => 'attr_only'],
        6 => ['slot_index' => 2, 'slot_group' => 'attr_only'],
        8 => ['slot_index' => 3, 'slot_group' => 'skill_only'],
        10 => ['slot_index' => 4, 'slot_group' => 'skill_only'],
    ];

    protected $fillable = [
        'required_star',
        'slot_index',
        'slot_group',
        'summary',
        'sort_order',
        'is_enabled',
        'remark',
    ];

    protected $casts = [
        'required_star' => 'integer',
        'slot_index' => 'integer',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            $model->fill(EquipmentStarModuleSupport::normalizeSlotUnlockRowOrFail(
                $model->attributesToArray(),
                'equipment_star_slot_unlock'
            ));
        });
    }
}
