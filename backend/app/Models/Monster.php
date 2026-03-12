<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Monster extends Model
{
    protected $table = 'monsters';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'kind',
        'hp',
        'atk',
        'def',
        'speed',
        'radius',
        'aggro_range',
        'attack_interval',
        'attack_range',
        'exp',
        'drop_bonus_percent',
        'dex_gold',
        'icon',
        'drops',
        'is_enabled',
        'sort_order',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'attack_interval' => 'float',
        'drops' => 'array',
    ];
}
