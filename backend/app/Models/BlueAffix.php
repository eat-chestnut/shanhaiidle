<?php

namespace App\Models;

use App\Support\BlueAffixModuleSupport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlueAffix extends Model
{
    protected $table = 'blue_affixes';

    protected $fillable = [
        'affix_id',
        'affix_name',
        'display_name',
        'effect_key',
        'value_type',
        'value_min',
        'value_max',
        'weight',
        'level_band',
        'quality',
        'rarity',
        'summary',
        'sort_order',
        'is_enabled',
        'remark',
    ];

    protected $casts = [
        'value_min' => 'float',
        'value_max' => 'float',
        'weight' => 'integer',
        'level_band' => 'integer',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $affix): void {
            BlueAffixModuleSupport::validateAffixModelOrFail($affix);
        });
    }

    public function slotRules(): HasMany
    {
        return $this->hasMany(BlueAffixSlotRule::class, 'affix_id', 'affix_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
