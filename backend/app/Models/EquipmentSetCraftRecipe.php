<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EquipmentSetCraftRecipe extends Model
{
    protected $fillable = [
        'recipe_id',
        'set_id',
        'slot_type',
        'result_item_id',
        'required_base_item_id',
        'required_blueprint_item_id',
        'unlock_level',
        'summary',
        'sort_order',
        'is_enabled',
        'remark',
    ];

    protected $casts = [
        'unlock_level' => 'integer',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    public function equipmentSet(): BelongsTo
    {
        return $this->belongsTo(EquipmentSet::class, 'set_id', 'set_id');
    }

    public function costItems(): HasMany
    {
        return $this->hasMany(EquipmentSetRecipeCostItem::class, 'recipe_id', 'recipe_id');
    }
}
