<?php

namespace App\Models;

use App\Support\BlueEquipmentTemplateModuleSupport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlueEquipmentTemplateBaseStat extends Model
{
    protected $table = 'blue_equipment_template_base_stats';

    protected $fillable = [
        'template_id',
        'stat_key',
        'value_type',
        'value',
        'sort_order',
        'is_enabled',
        'remark',
    ];

    protected $casts = [
        'value' => 'float',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $baseStat): void {
            BlueEquipmentTemplateModuleSupport::validateBaseStatModelOrFail($baseStat);
        });
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(BlueEquipmentTemplate::class, 'template_id', 'template_id');
    }
}
