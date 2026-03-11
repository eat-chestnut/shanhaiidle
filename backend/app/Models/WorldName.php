<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorldName extends Model
{
    protected $fillable = [
        'name_id',
        'category',
        'sub_category',
        'display_name',
        'source_text',
        'from_original',
        'naming_note',
        'visual_tags',
        'system_usage',
        'icon_path',
        'image_path',
        'sort_order',
        'is_enabled',
    ];

    protected $casts = [
        'from_original' => 'boolean',
        'visual_tags' => 'array',
        'system_usage' => 'array',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];
}
