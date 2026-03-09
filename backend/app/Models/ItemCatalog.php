<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemCatalog extends Model
{
    protected $table = 'items_catalog';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'type',
        'rarity',
    ];
}
