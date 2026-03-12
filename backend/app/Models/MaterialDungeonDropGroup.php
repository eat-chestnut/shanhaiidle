<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MaterialDungeonDropGroup extends Model
{
    protected $table = 'material_dungeon_drop_groups';

    protected $fillable = [
        'group_id',
        'name',
        'rewards',
        'description',
        'sort_order',
        'is_enabled',
    ];

    protected $casts = [
        'rewards' => 'array',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $group): void {
            if (blank($group->group_id)) {
                $group->group_id = 'mdg_' . Str::lower((string) Str::uuid());
            }
        });
    }
}
