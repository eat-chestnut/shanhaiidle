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
        'difficulties',
        'sort_order',
        'is_enabled',
    ];

    protected $casts = [
        'difficulties' => 'array',
        'is_enabled' => 'boolean',
    ];
}
