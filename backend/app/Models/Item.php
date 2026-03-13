<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $table = 'items';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const MAIN_TYPE_OPTIONS = [
        'currency' => '货币',
        'material' => '材料',
        'equipment' => '装备',
        'gem' => '宝石',
        'talisman' => '护符',
        'boss_core' => 'Boss核心',
        'gift_pack' => '礼包',
        'consumable' => '消耗品',
        'blueprint' => '图纸',
        'blueprint_fragment' => '图纸碎片',
    ];

    public const SUB_TYPE_OPTIONS = [
        'currency' => [
            'gold' => '铜贝',
            'premium' => '灵石',
            'contribution' => '宗门贡献',
            'other_token' => '其他代币',
        ],
        'material' => [
            'base_material' => '基础材料',
            'boss_material' => 'Boss 材料',
            'upgrade_material' => '升阶材料',
            'gem_material' => '宝石材料',
            'talisman_material' => '护符材料',
            'refine_material' => '洗练材料',
            'star_material' => '升星材料',
            'story_material' => '剧情材料',
            'function_material' => '功能材料',
        ],
        'equipment' => [
            'set_equipment' => '套装成品',
            'blue_equipment' => '蓝装成品',
            'common_equipment' => '普通装备',
        ],
        'gem' => [
            'attr_gem' => '属性宝石',
            'skill_gem' => '技能宝石',
        ],
        'talisman' => [
            'common_talisman' => '通用护符',
            'sect_talisman' => '宗门护符',
        ],
        'boss_core' => [
            'boss_core' => 'Boss核心',
        ],
        'gift_pack' => [
            'stage_reward_pack' => '主线首通礼包',
            'growth_pack' => '成长礼包',
            'shop_pack' => '商城礼包',
            'milestone_pack' => '里程碑礼包',
        ],
        'consumable' => [
            'exp_item' => '经验丹',
            'ticket' => '门票',
            'special_item' => '特殊道具',
        ],
        'blueprint' => [
            'equipment_blueprint' => '装备图纸',
        ],
        'blueprint_fragment' => [
            'theme_blueprint_fragment' => '图纸碎片',
        ],
    ];

    public const QUALITY_OPTIONS = [
        'white' => '白色',
        'blue' => '蓝色',
        'purple' => '紫色',
        'gold' => '金色',
        'red' => '红色',
    ];

    public const RARITY_OPTIONS = self::QUALITY_OPTIONS;

    public const BIND_TYPE_OPTIONS = [
        'none' => '不绑定',
        'bind_on_get' => '获得绑定',
        'bind_on_use' => '使用绑定',
    ];

    public const USE_TYPE_OPTIONS = [
        'none' => '无主动用途',
        'consume_reward' => '消耗即奖励',
        'open_pack' => '开启礼包',
        'equip' => '装备',
        'embed' => '镶嵌',
        'craft_material' => '打造材料',
    ];

    protected $fillable = [
        'id',
        'item_id',
        'item_name',
        'display_name',
        'main_type',
        'sub_type',
        'quality',
        'rarity',
        'icon',
        'desc',
        'is_stackable',
        'max_stack',
        'is_enabled',
        'sort_order',
        'remark',
        'source_library',
        'required_level',
        'bind_type',
        'sell_price',
        'use_type',
        'rarity_frame_key',
        'name',
        'type',
        'material_type',
        'trait',
        'effect_type',
        'target_scope',
        'effect_payload',
        'drop_unlock_level',
        'socket_limit',
        'source_tags',
        'use_tags',
        'can_compose',
        'can_reforge',
        'stack_limit',
    ];

    protected $casts = [
        'is_stackable' => 'boolean',
        'max_stack' => 'integer',
        'is_enabled' => 'boolean',
        'sort_order' => 'integer',
        'required_level' => 'integer',
        'sell_price' => 'integer',
        'effect_payload' => 'array',
        'socket_limit' => 'array',
        'source_tags' => 'array',
        'use_tags' => 'array',
        'drop_unlock_level' => 'integer',
        'stack_limit' => 'integer',
        'can_compose' => 'boolean',
        'can_reforge' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
