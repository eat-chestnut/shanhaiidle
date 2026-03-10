<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CraftingRecipe extends Model
{
    protected $table = 'crafting_recipes';

    protected $fillable = [
        'recipe_id',
        'recipe_type',
        'output_type',
        'output_id',
        'output_count',
        'unlock_level',
        'cost_items',
        'cost_gold',
        'cost_currency',
        'notes',
        'sort_order',
        'is_enabled',
    ];

    protected $casts = [
        'output_count' => 'integer',
        'unlock_level' => 'integer',
        'cost_items' => 'array',
        'cost_gold' => 'integer',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];
}
