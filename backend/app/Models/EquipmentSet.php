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
        'name',
        'max_pieces',
        'thresholds',
        'is_enabled',
        'sort_order',
    ];

    protected $casts = [
        'max_pieces' => 'integer',
        'thresholds' => 'array',
        'is_enabled' => 'boolean',
        'sort_order' => 'integer',
    ];
}

