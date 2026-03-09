<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkillCatalog extends Model
{
    protected $table = 'skills_catalog';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'desc',
        'sect',
        'class',
        'is_enabled',
        'sort_order',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];
}
