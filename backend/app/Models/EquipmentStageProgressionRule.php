<?php

namespace App\Models;

use App\Support\EquipmentStarModuleSupport;
use Illuminate\Database\Eloquent\Model;

class EquipmentStageProgressionRule extends Model
{
    protected $table = 'equipment_stage_progression_rules';

    public const STAR_KEEP_MODE_OPTIONS = [
        'keep_current_star' => '保留当前星级',
    ];

    public const PROGRESSION_MAP = [
        20 => ['to_set_level' => 40, 'required_max_star' => 3],
        40 => ['to_set_level' => 50, 'required_max_star' => 6],
        50 => ['to_set_level' => 60, 'required_max_star' => 8],
    ];

    protected $fillable = [
        'from_set_level',
        'to_set_level',
        'required_max_star',
        'star_keep_mode',
        'summary',
        'sort_order',
        'is_enabled',
        'remark',
    ];

    protected $casts = [
        'from_set_level' => 'integer',
        'to_set_level' => 'integer',
        'required_max_star' => 'integer',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            $model->fill(EquipmentStarModuleSupport::normalizeProgressionRuleRowOrFail(
                $model->attributesToArray(),
                'equipment_stage_progression_rule'
            ));
        });
    }
}
