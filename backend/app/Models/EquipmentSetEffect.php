<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentSetEffect extends Model
{
    protected $fillable = [
        'set_id',
        'piece_count',
        'effect_key',
        'value_type',
        'value',
        'summary',
        'sort_order',
        'is_enabled',
        'remark',
    ];

    protected $casts = [
        'piece_count' => 'integer',
        'value' => 'float',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    public function equipmentSet(): BelongsTo
    {
        return $this->belongsTo(EquipmentSet::class, 'set_id', 'set_id');
    }
}
