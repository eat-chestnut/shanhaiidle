# Item Module

## 模块定位

`items` 是全局统一物品锚点表。任何“可发放、可掉落、可展示、可计数”的对象，优先通过 `items.item_id` 建立业务引用。

当前明确应 item 化的内容：

- 货币
- 材料
- 装备成品
- 宝石成品
- 护符成品
- 礼包本体
- 消耗品

当前明确不是 item 的内容：

- 怪物
- 技能本体
- 主线章节 / 难度 / 副本
- 蓝色词条本体
- 套装线 / 套装效果定义
- 宝石配置定义（写入 `gems`）
- 护符类型定义
- 掉落条目 / 奖励条目
- 蓝装模板

## 表职责

`items` 负责：

- 提供稳定 `item_id`
- 维护展示名称、品质、稀有度、图标、描述
- 维护堆叠、等级、绑定、出售、使用类型等基础规则
- 为掉落、首通奖励、礼包、商城、里程碑等模块提供统一引用目标

## 核心字段

### Canonical 字段

- `item_id`
  - 业务唯一 ID
  - 全系统最终引用锚点
- `item_name`
  - 内部名称
- `display_name`
  - 前端展示名称
- `main_type`
  - 一级分类
- `sub_type`
  - 二级分类
- `quality`
  - 玩法品质
- `rarity`
  - UI 展示稀有度
- `is_stackable`
  - 是否可堆叠
- `max_stack`
  - 最大堆叠
- `required_level`
  - 使用 / 获取建议等级
- `bind_type`
  - 绑定规则
- `sell_price`
  - 出售价格
- `use_type`
  - 使用类型
- `source_library`
  - 来源目录标识
- `rarity_frame_key`
  - 稀有度边框覆盖

### 兼容扩展字段

当前仍保留旧扩展字段，以免立即打断 gem / material / 旧页面的细分逻辑：

- `type`
- `material_type`
- `effect_type`
- `target_scope`
- `effect_payload`
- `drop_unlock_level`
- `socket_limit`
- `source_tags`
- `use_tags`
- `stack_limit`
- `can_compose`
- `can_reforge`

这些字段不是新的统一锚点口径，但在当前版本中仍可承接旧专用模块的扩展属性。

其中宝石正式效果配置已迁移到 `gems`：

- `items` 只承接宝石成品 item
- `gems.item_id` 统一引用 `items.item_id`
- 后续掉落、礼包、商城都应引用宝石 `item_id`
- `gems` 本身不负责孔位、镶嵌、合成、分解

护符正式成长配置已迁移到 `talismans` + `talisman_tiers` + `talisman_tier_upgrade_costs` + `talisman_star_links`：

- `items` 只承接护符成品 item
- `talismans.item_id` 统一引用 `items.item_id`
- 后续商城、礼包、奖励都应引用护符 `item_id`
- 护符位不参与全身装备星级统计
- 护符模块本身不负责穿戴与执行逻辑

## quality 与 rarity

### quality

偏玩法品质。用于：

- 装备 / 宝石 / 礼包 / 材料的玩法分层
- 掉落和成长配置中的品质约束

### rarity

偏全局 UI 展示层级。用于：

- 背包格子背景
- 奖励预览边框
- 列表 badge
- 稀有度边框与色带

当前 V1 中两者可使用同一套值：

- `white`
- `blue`
- `purple`
- `gold`
- `red`

## main_type / sub_type

### main_type

- `currency`
- `material`
- `equipment`
- `gem`
- `talisman`
- `gift_pack`
- `consumable`
- `blueprint`
- `blueprint_fragment`

### sub_type

`currency`
- `gold`
- `premium`
- `contribution`
- `other_token`

`material`
- `base_material`
- `boss_material`
- `upgrade_material`
- `gem_material`
- `talisman_material`
- `refine_material`
- `star_material`
- `story_material`
- `function_material`

`equipment`
- `set_equipment`
- `blue_equipment`
- `common_equipment`

`gem`
- `attr_gem`
- `skill_gem`

`talisman`
- `common_talisman`
- `sect_talisman`

`gift_pack`
- `stage_reward_pack`
- `growth_pack`
- `shop_pack`
- `milestone_pack`

`consumable`
- `exp_item`
- `ticket`
- `special_item`

## bind_type / use_type

### bind_type

- `none`
- `bind_on_get`
- `bind_on_use`

### use_type

- `none`
- `consume_reward`
- `open_pack`
- `equip`
- `embed`
- `craft_material`

## 与其他模块关系

- 怪物掉落条目引用 `items.item_id`
- 日常副本的 `entry_cost_item_id` 统一引用 `items.item_id`
- 日常副本升级消耗 `daily_dungeon_upgrade_costs.item_id` 统一引用 `items.item_id`
- 日常副本首通奖励 `daily_dungeon_first_clear_rewards.item_id` 统一引用 `items.item_id`
- 主线难度首通奖励引用 `items.item_id`
- 礼包本体本身也应存在于 `items`
- 礼包内容通过 `gift_pack_items.item_id` 继续引用 `items.item_id`
- V1 禁止 `gift_pack_items.item_id` 再指向 `gift_pack` 类型 item
- 商城商品的 `reward_item_id` 与 `price_item_id` 都统一引用 `items.item_id`
- 商城商品一次只卖单个 `reward_item_id`
- 多奖励商城商品必须先做成礼包 item，再由 `shop_goods.reward_item_id` 指向该礼包 item
- 成长里程碑的 `reward_item_id` 统一引用 `items.item_id`
- 多奖励里程碑必须先做成礼包 item，再由 `milestones.reward_item_id` 指向该礼包 item
- 成品实体与配置定义分离：
  - 蓝装模板不是 item
  - 蓝装成品是 item
  - 宝石配置不是 item，宝石成品是 item
  - 套装线不是 item
  - 套装成品装备是 item

## 宝石承载方式

- 宝石成品是 item，正式 carrier 为 `items.item_id`
- `gems.item_id` 必须命中对应宝石 item
- `items.main_type` 必须为 `gem`
- `items.sub_type` 当前只支持：
  - `attr_gem`
  - `skill_gem`
- `slot_group` 定义写在 `gems`，用于表达属性孔 / 技能孔的使用边界
- 掉落、礼包、商城后续都通过宝石 `item_id` 引用，不直接发 `gem_id`

## 护符承载方式

- 护符成品是 item，正式 carrier 为 `items.item_id`
- `talismans.item_id` 必须命中对应护符 item
- `items.main_type` 必须为 `talisman`
- `items.sub_type` 当前只支持：
  - `common_talisman`
  - `sect_talisman`
- 护符成长、升阶消耗、星级连锁定义写在护符模块表中，不写回 `items`
- 商城、礼包、奖励后续都通过护符 `item_id` 引用

## 礼包承载方式

- 礼包本体是 item，正式 carrier 为 `items.item_id`
- 礼包主表 `gift_packs.item_id` 必须命中对应礼包 item
- 礼包内容表 `gift_pack_items.item_id` 只引用普通 item，不再拆成货币 / 材料 / 装备多套结构
- `recommended_sect` 仅是礼包自选内容的展示标签，不是领取限制

## 商城承载方式

- `shop_goods.reward_item_id` 统一引用 `items.item_id`
- `shop_goods.price_item_id` 统一引用 `items.item_id`
- `goods_type = direct_item` 时直接售卖单个 item
- `goods_type = gift_pack` 时售卖礼包 item
- 商城表不直接挂多条奖励明细，多奖励必须通过礼包 item 承载

## 里程碑承载方式

- `milestones.reward_item_id` 统一引用 `items.item_id`
- 一个里程碑只发单个 `reward_item_id`
- 多奖励里程碑必须先做成礼包 item，再由里程碑节点发放该礼包 item

## 数据源规则

- 正式物品目录源文件：`data/items.json`
- `ItemsSeeder` 只负责从源文件导入
- 不再内置 demo fallback 物品数据
