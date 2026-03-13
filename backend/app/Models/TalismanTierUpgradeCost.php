<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TalismanTierUpgradeCost extends Model
{
    protected $table = 'talisman_tier_upgrade_costs';

    protected $fillable = [
        'talisman_id',
        'tier_no',
        'target_tier_no',
        'item_id',
        'count',
        'sort_order',
        'is_enabled',
        'remark',
    ];

    protected $casts = [
        'tier_no' => 'integer',
        'target_tier_no' => 'integer',
        'count' => 'integer',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    public function talisman(): BelongsTo
    {
        return $this->belongsTo(Talisman::class, 'talisman_id', 'talisman_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id', 'item_id');
    }
}
