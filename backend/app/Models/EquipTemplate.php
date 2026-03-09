<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipTemplate extends Model
{
    protected $table = 'equip_templates';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'slot',
        'rarity',
        'main_stat',
        'main_min',
        'main_max',
        'unidentified_chance',
        'icon',
        'set_id',
        'effects',
        'is_enabled',
        'sort_order',
    ];

    protected $casts = [
        'unidentified_chance' => 'float',
        'effects' => 'array',
        'is_enabled' => 'boolean',
    ];
}
