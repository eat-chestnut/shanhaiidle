<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentSetSource extends Model
{
    protected $fillable = [
        'set_source_id',
        'set_line_id',
        'set_name',
        'flow_tag',
        'set_stage',
        'main_mat_1',
        'main_mat_2',
        'sub_materials',
        'source_maps',
        'source_bosses',
        'need_blueprint',
        'blueprint_source',
        'craft_desc',
        'icon_path',
        'image_path',
        'sort_order',
        'is_enabled',
    ];

    protected $casts = [
        'set_stage' => 'integer',
        'sub_materials' => 'array',
        'source_maps' => 'array',
        'source_bosses' => 'array',
        'need_blueprint' => 'boolean',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];
}
