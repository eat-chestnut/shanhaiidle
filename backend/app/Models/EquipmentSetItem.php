<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentSetItem extends Model
{
    protected $fillable = [
        'set_id',
        'item_id',
        'slot_type',
        'sort_order',
        'is_enabled',
        'remark',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    public function equipmentSet(): BelongsTo
    {
        return $this->belongsTo(EquipmentSet::class, 'set_id', 'set_id');
    }
}
