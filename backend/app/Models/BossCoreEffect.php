<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BossCoreEffect extends Model
{
    protected $fillable = [
        'core_id',
        'effect_key',
        'value_type',
        'value',
        'summary',
        'sort_order',
        'is_enabled',
        'remark',
    ];

    protected $casts = [
        'value' => 'float',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    public function bossCore(): BelongsTo
    {
        return $this->belongsTo(BossCore::class, 'core_id', 'core_id');
    }
}
