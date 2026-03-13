<?php

namespace App\Models;

use App\Support\BlueEquipmentTemplateModuleSupport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlueEquipmentTemplate extends Model
{
    protected $table = 'blue_equipment_templates';

    protected $fillable = [
        'template_id',
        'template_name',
        'display_name',
        'slot_type',
        'level_band',
        'result_item_id',
        'blue_affix_count_min',
        'blue_affix_count_max',
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
        'level_band' => 'integer',
        'blue_affix_count_min' => 'integer',
        'blue_affix_count_max' => 'integer',
        'unlock_level' => 'integer',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $template): void {
            BlueEquipmentTemplateModuleSupport::validateTemplateModelOrFail($template);
        });
    }

    public function baseStats(): HasMany
    {
        return $this->hasMany(BlueEquipmentTemplateBaseStat::class, 'template_id', 'template_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function resultItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'result_item_id', 'item_id');
    }
}
