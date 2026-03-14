<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerCurrency extends Model
{
    protected $table = 'player_currencies';

    protected $fillable = [
        'player_id',
        'currency_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'integer',
    ];
}
