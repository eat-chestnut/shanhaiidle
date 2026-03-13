<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentSet extends Model
{
    protected $table = 'equipment_sets';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'set_line_id',
        'name',
        'sect',
        'flow_tag',
        'stage',
        'piece_count',
        'slot_ids',
        'thresholds',
        'description',
        'is_enabled',
        'sort_order',
    ];

    protected $casts = [
        'stage' => 'integer',
        'piece_count' => 'integer',
        'slot_ids' => 'array',
        'thresholds' => 'array',
        'is_enabled' => 'boolean',
        'sort_order' => 'integer',
    ];
}
