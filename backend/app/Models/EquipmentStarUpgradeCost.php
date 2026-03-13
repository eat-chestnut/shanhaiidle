<?php

namespace App\Models;

use App\Support\EquipmentStarModuleSupport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentStarUpgradeCost extends Model
{
    protected $table = 'equipment_star_upgrade_costs';

    protected $fillable = [
        'set_level',
        'from_star',
        'to_star',
        'item_id',
        'count',
        'sort_order',
        'is_enabled',
        'remark',
    ];

    protected $casts = [
        'set_level' => 'integer',
        'from_star' => 'integer',
        'to_star' => 'integer',
        'count' => 'integer',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            $model->fill(EquipmentStarModuleSupport::normalizeUpgradeCostRowOrFail(
                $model->attributesToArray(),
                'equipment_star_upgrade_cost'
            ));
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id', 'item_id');
    }
}
