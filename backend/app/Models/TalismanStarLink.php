<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TalismanStarLink extends Model
{
    protected $table = 'talisman_star_links';

    protected $fillable = [
        'talisman_id',
        'tier_no',
        'required_equipment_star',
        'effect_key',
        'value_type',
        'value',
        'summary',
        'sort_order',
        'is_enabled',
        'remark',
    ];

    protected $casts = [
        'tier_no' => 'integer',
        'required_equipment_star' => 'integer',
        'value' => 'float',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    public function talisman(): BelongsTo
    {
        return $this->belongsTo(Talisman::class, 'talisman_id', 'talisman_id');
    }
}
