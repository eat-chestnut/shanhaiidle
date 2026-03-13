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
        'type',
        'min_level',
        'max_level',
        'target_rule',
        'cd_sec',
        'cost_qi',
        'cast_range',
        'tags',
        'runtime_blocks',
        'is_enabled',
        'sort_order',
    ];

    protected $casts = [
        'min_level' => 'integer',
        'max_level' => 'integer',
        'cd_sec' => 'float',
        'cost_qi' => 'integer',
        'cast_range' => 'float',
        'tags' => 'array',
        'runtime_blocks' => 'array',
        'is_enabled' => 'boolean',
    ];
}
