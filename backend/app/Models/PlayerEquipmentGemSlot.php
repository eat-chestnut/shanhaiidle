<?php

namespace App\Models;

use App\Support\PlayerEquipmentInstanceModuleSupport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerEquipmentGemSlot extends Model
{
    protected $table = 'player_equipment_gem_slots';

    public const SLOT_GROUP_OPTIONS = [
        'attr_only' => '属性孔',
        'skill_only' => '技能孔',
    ];

    public const SLOT_RULE_MAP = [
        1 => ['required_star' => 3, 'slot_group' => 'attr_only'],
        2 => ['required_star' => 6, 'slot_group' => 'attr_only'],
        3 => ['required_star' => 8, 'slot_group' => 'skill_only'],
        4 => ['required_star' => 10, 'slot_group' => 'skill_only'],
    ];

    protected $fillable = [
        'instance_id',
        'slot_index',
        'slot_group',
        'required_star',
        'is_unlocked',
        'gem_item_id',
    ];

    protected $casts = [
        'slot_index' => 'integer',
        'required_star' => 'integer',
        'is_unlocked' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            $model->fill(PlayerEquipmentInstanceModuleSupport::normalizeGemSlotRowOrFail(
                $model->attributesToArray(),
                'player_equipment_gem_slot',
                null,
                $model->exists ? (int) $model->getKey() : null,
            ));
        });
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(PlayerEquipmentInstance::class, 'instance_id', 'instance_id');
    }

    public function gemItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'gem_item_id', 'item_id');
    }
}
