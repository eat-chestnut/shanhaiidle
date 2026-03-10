<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipTemplate extends Model
{
    protected $table = 'equip_templates';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'slot',
        'equip_type',
        'rarity',
        'main_stat',
        'main_min',
        'main_max',
        'unidentified_chance',
        'icon',
        'set_id',
        'set_line_id',
        'set_stage',
        'flow_tag',
        'required_level',
        'white_stats',
        'star_growth',
        'star_enabled',
        'star_cap',
        'can_attach_blue_affix',
        'can_roll_purple_affix',
        'socket_rule_ref',
        'slot_group',
        'theme_key',
        'quality_tier',
        'forge_enabled',
        'forge_tier',
        'forge_family_id',
        'upgrade_from_template_id',
        'upgrade_to_template_id',
        'blueprint_item_id',
        'effects',
        'is_enabled',
        'sort_order',
    ];

    protected $casts = [
        'unidentified_chance' => 'float',
        'set_stage' => 'integer',
        'required_level' => 'integer',
        'white_stats' => 'array',
        'star_growth' => 'array',
        'star_enabled' => 'boolean',
        'star_cap' => 'integer',
        'can_attach_blue_affix' => 'boolean',
        'can_roll_purple_affix' => 'boolean',
        'forge_enabled' => 'boolean',
        'effects' => 'array',
        'is_enabled' => 'boolean',
        'sort_order' => 'integer',
    ];
}
