<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $table = 'items';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'type',
        'rarity',
        'icon',
        'trait',
        'gem_effect',
        'is_enabled',
        'sort_order',
    ];

    protected $casts = [
        'gem_effect' => 'array',
        'is_enabled' => 'boolean',
    ];
}
