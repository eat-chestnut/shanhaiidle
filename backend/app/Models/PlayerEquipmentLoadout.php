<?php

namespace App\Models;

use App\Support\PlayerEquipmentInstanceModuleSupport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerEquipmentLoadout extends Model
{
    protected $table = 'player_equipment_loadouts';

    public const SLOT_TYPE_OPTIONS = PlayerEquipmentInstance::SLOT_TYPE_OPTIONS;

    protected $fillable = [
        'player_id',
        'slot_type',
        'instance_id',
    ];

    protected $casts = [
        'player_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            $model->fill(PlayerEquipmentInstanceModuleSupport::normalizeLoadoutRowOrFail(
                $model->attributesToArray(),
                'player_equipment_loadout',
                null,
                $model->exists ? (int) $model->getKey() : null,
            ));
        });
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(PlayerEquipmentInstance::class, 'instance_id', 'instance_id');
    }
}
