<?php

namespace App\Models;

use App\Support\EquipmentStarModuleSupport;
use Illuminate\Database\Eloquent\Model;

class EquipmentStarRule extends Model
{
    protected $table = 'equipment_star_rules';

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

    protected $fillable = [
        'set_level',
        'max_star',
        'summary',
        'sort_order',
        'is_enabled',
        'remark',
    ];

    protected $casts = [
        'set_level' => 'integer',
        'max_star' => 'integer',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            $model->fill(EquipmentStarModuleSupport::normalizeStarRuleRowOrFail(
                $model->attributesToArray(),
                'equipment_star_rule'
            ));
        });
    }
}
