<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TalismanTier extends Model
{
    protected $table = 'talisman_tiers';

    public const TRIGGER_RULE_OPTIONS = [
        'passive_always' => '常驻生效',
        'on_skill_cast' => '施放技能时',
        'on_hit' => '命中时',
        'low_hp' => '低血量时',
    ];

    protected $fillable = [
        'talisman_id',
        'tier_no',
        'tier_name',
        'effect_key',
        'value_type',
        'value',
        'trigger_rule',
        'cooldown_sec',
        'summary',
        'sort_order',
        'is_enabled',
        'remark',
    ];

    protected $casts = [
        'tier_no' => 'integer',
        'value' => 'float',
        'cooldown_sec' => 'integer',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    public function talisman(): BelongsTo
    {
        return $this->belongsTo(Talisman::class, 'talisman_id', 'talisman_id');
    }
}
