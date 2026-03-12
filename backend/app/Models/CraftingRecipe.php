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

    public function setCostItemsAttribute($value): void
    {
        $rows = [];

        foreach ((array) $value as $row) {
            if (! is_array($row)) {
                continue;
            }

            $itemId = trim((string) ($row['item_id'] ?? ''));
            if ($itemId === '') {
                continue;
            }

            $itemName = trim((string) ($row['item_name'] ?? ''));
            if ($itemName === '') {
                $itemName = (string) Item::query()->where('id', $itemId)->value('name');
            }

            $rows[] = [
                'item_id' => $itemId,
                'item_name' => $itemName,
                'count' => max(1, (int) ($row['count'] ?? 1)),
                'material_type' => trim((string) ($row['material_type'] ?? 'sub')) ?: 'sub',
            ];
        }

        $this->attributes['cost_items'] = json_encode(array_values($rows), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
