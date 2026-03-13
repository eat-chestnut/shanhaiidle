<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentSetRecipeCostItem extends Model
{
    protected $fillable = [
        'recipe_id',
        'item_id',
        'count',
        'sort_order',
        'is_enabled',
        'remark',
    ];

    protected $casts = [
        'count' => 'integer',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(EquipmentSetCraftRecipe::class, 'recipe_id', 'recipe_id');
    }
}
