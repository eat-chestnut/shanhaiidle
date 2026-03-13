# Equipment Set Module

## 模块边界

- 套装线不是 item。
- 套装成品装备是 item，通过 `items.item_id` 承接。
- 套装全部通过打造获得，不做完整套装直接掉落。
- 40 / 50 / 60 级打造都需要前一档同部位 item 与图纸。
- 本轮不实现套装升星，后续会在当前结构上扩展升星能力。

## 表结构

### equipment_sets

- `set_id`
  - 业务唯一 ID。
- `set_name`
  - 内部名称。
- `display_name`
  - 展示名称。
- `set_level`
  - 仅支持 `20 / 40 / 50 / 60`。
- `piece_total`
  - 由 `set_level` 固定推导：
  - `20 -> 4`
  - `40 -> 6`
  - `50 -> 8`
  - `60 -> 8`
- `set_type`
  - 首版固定 `combat_set`。
- `quality` / `rarity`
  - 统一使用 `white / blue / purple / gold / red`。
- `unlock_level`
  - 打开或打造开放等级。
- `icon`
  - 图标资源。
- `summary`
  - 套装简介。
- `source_desc`
  - 来源说明，例如主线打造、Boss 图纸打造。
- `sort_order`
  - 排序。
- `is_enabled`
  - 是否启用。
- `remark`
  - 备注。

### equipment_set_items

- `set_id`
  - 关联 `equipment_sets.set_id`。
- `item_id`
  - 关联 `items.item_id`，且必须是 `main_type = equipment`、`sub_type = set_equipment`。
- `slot_type`
  - 固定装备位：
  - `main_weapon`
  - `sub_weapon`
  - `armor`
  - `shoe`
  - `helmet`
  - `leg`
  - `cloak`
  - `necklace`
- `sort_order`
  - 部位排序。
- `is_enabled`
  - 是否启用。
- `remark`
  - 备注。

### equipment_set_effects

- `set_id`
  - 关联 `equipment_sets.set_id`。
- `piece_count`
  - 套装件数阈值。
- `effect_key`
  - 结构化效果键，例如：
  - `bonus_melee_atk`
  - `bonus_skill_dmg`
  - `bonus_boss_dmg`
  - `bonus_damage_reduction`
  - `bonus_hp`
  - `bonus_crit_rate`
  - `bonus_crit_dmg`
  - `bonus_final_damage`
  - `bonus_frost_damage`
  - `bonus_flame_damage`
  - `bonus_skill_cooldown_reduction`
  - `bonus_guard_shield`
- `value_type`
  - `flat` 或 `percent`。
- `value`
  - 效果数值。
- `summary`
  - 前端展示说明。
- `sort_order`
  - 排序。
- `is_enabled`
  - 是否启用。
- `remark`
  - 备注。

### equipment_set_craft_recipes

- `recipe_id`
  - 业务唯一 ID。
- `set_id`
  - 关联 `equipment_sets.set_id`。
- `slot_type`
  - 当前打造目标部位。
- `result_item_id`
  - 打造产物，关联 `items.item_id`。
- `required_base_item_id`
  - 主材料。
- `required_blueprint_item_id`
  - 图纸 item。
- `unlock_level`
  - 配方开放等级。
- `summary`
  - 配方说明。
- `sort_order`
  - 排序。
- `is_enabled`
  - 是否启用。
- `remark`
  - 备注。

### equipment_set_recipe_cost_items

- `recipe_id`
  - 关联 `equipment_set_craft_recipes.recipe_id`。
- `item_id`
  - 关联 `items.item_id`。
- `count`
  - 数量。
- `sort_order`
  - 排序。
- `is_enabled`
  - 是否启用。
- `remark`
  - 备注。

## 四档规则

- 20级：4件套，激活 `2 / 4`
- 40级：6件套，激活 `2 / 4 / 6`
- 50级：8件套，激活 `2 / 4 / 6 / 8`
- 60级：8件套，激活 `2 / 4 / 6 / 8`

## 打造链规则

- 20级套装：
  - 不需要图纸。
  - 不需要前一档装备。
  - 仅消耗附加材料。
- 40级套装：
  - 每个部位必须配置 `required_base_item_id`。
  - 每个部位必须配置 `required_blueprint_item_id`。
  - 主材要求前一档同部位 item。
- 50级套装：
  - 每个部位必须配置 `required_base_item_id`。
  - 每个部位必须配置 `required_blueprint_item_id`。
  - 主材要求前一档同部位 item。
- 60级套装：
  - 每个部位必须配置 `required_base_item_id`。
  - 每个部位必须配置 `required_blueprint_item_id`。
  - 主材要求前一档同部位 item。

## 新增部位的主材承接

- 由于 20级固定 4 件、40级固定 6 件、50 / 60 级固定 8 件，高阶阶段会解锁低阶未纳入件数统计的新部位。
- 为了同时满足“低阶件数固定”与“高阶仍要求前一档同部位主材”，样例数据为这些新增部位补了独立 carrier item：
  - 20级头盔胚 / 护腿胚
  - 40级披风胚 / 项链胚
- 这些 carrier item 只作为打造主材锚点，不计入低阶套装件数，不改变 4 / 6 / 8 / 8 的正式套装件数规则。

## 导出结构

- `equipment_sets.json` 导出包含：
  - `equipment_set_rules`
  - `equipment_sets`
  - `equipment_set_items`
  - `equipment_set_effects`
  - `equipment_set_craft_recipes`
  - `equipment_set_recipe_cost_items`
- 配置 bundle 已纳入 `equipment_sets.json`。

## 本轮未实现

- 套装升星系统
- 装备随机词条系统
- 蓝装模板模块
- 商城模块
- 礼包模块
- 掉落模块执行逻辑
