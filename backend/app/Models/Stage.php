<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stage extends Model
{
    protected $table = 'stages';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'unlock_min_level',
        'elite_every_kills',
        'boss_every_kills',
        'spawn_patch',
        'drops_patch',
        'monsters_patch',
        'difficulties',
        'sort_order',
        'is_enabled',
    ];

    protected $casts = [
        'spawn_patch' => 'array',
        'drops_patch' => 'array',
        'monsters_patch' => 'array',
        'difficulties' => 'array',
        'is_enabled' => 'boolean',
    ];
}
