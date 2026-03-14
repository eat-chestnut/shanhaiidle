<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerItem extends Model
{
    protected $table = 'player_items';

    protected $fillable = [
        'player_id',
        'item_id',
        'count',
    ];

    protected $casts = [
        'count' => 'integer',
    ];
}
