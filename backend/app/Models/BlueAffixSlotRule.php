<?php

namespace App\Models;

use App\Support\BlueAffixModuleSupport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlueAffixSlotRule extends Model
{
    protected $table = 'blue_affix_slot_rules';

    protected $fillable = [
        'affix_id',
        'slot_type',
        'sort_order',
        'is_enabled',
        'remark',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $slotRule): void {
            BlueAffixModuleSupport::validateSlotRuleModelOrFail($slotRule);
        });
    }

    public function affix(): BelongsTo
    {
        return $this->belongsTo(BlueAffix::class, 'affix_id', 'affix_id');
    }
}
