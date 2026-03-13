# Player Equipment Instance Module

## 模块定位

本模块只覆盖玩家装备“实例层 + 穿戴统计层”，正式落三张表：

- `player_equipment_instances`
- `player_equipment_loadouts`
- `player_equipment_gem_slots`

本轮不包含：

- 战斗执行逻辑
- 套装效果执行
- 护符效果执行
- 宝石镶嵌接口
- 宝石/护符/套装定义模块改造
- 掉落、商城、礼包逻辑

## 固定穿戴位

固定只允许：

- `main_weapon`
- `sub_weapon`
- `armor`
- `leg`
- `shoe`
- `cloak`
- `helmet`
- `necklace`
- `bracelet_1`
- `bracelet_2`
- `ring_1`
- `ring_2`
- `talisman`

双手镯 / 双戒指必须分位，不允许合并为单一位。

## player_equipment_instances 字段说明

- `id`：自增主键
- `player_id`：玩家 ID
- `instance_id`：装备实例业务唯一 ID
- `item_id`：关联 `items.item_id`
- `equipment_source_type`：首版仅 `set_equipment / blue_equipment / common_equipment`
- `slot_type`：固定穿戴位
- `set_id`：套装装备必填；非套装为空
- `set_level`：套装装备仅允许 `20 / 40 / 50 / 60`；非套装为空
- `star`：当前实际星级，`>= 0`
- `max_star`：当前上限；若是套装装备必须满足 `20->3, 40->6, 50->8, 60->10`
- `quality`：从成品 item 继承
- `rarity`：从成品 item 继承
- `is_locked`：是否锁定
- `is_equipped`：是否已穿戴
- `obtained_at`：获得时间
- `created_at / updated_at`：维护时间

## player_equipment_loadouts 字段说明

- `id`：自增主键
- `player_id`：玩家 ID
- `slot_type`：固定穿戴位
- `instance_id`：关联 `player_equipment_instances.instance_id`
- `created_at / updated_at`：维护时间

关键约束：

- 同一 `player_id + slot_type` 只能有 1 条映射
- 同一 `instance_id` 不能占多个穿戴位
- `instance_id` 必须属于同一 `player_id`

## player_equipment_gem_slots 字段说明

- `id`：自增主键
- `instance_id`：关联 `player_equipment_instances.instance_id`
- `slot_index`：固定 `1 / 2 / 3 / 4`
- `slot_group`：固定 `attr_only / skill_only`
- `required_star`：固定 `3 / 6 / 8 / 10`
- `is_unlocked`：孔位是否解锁
- `gem_item_id`：可空；非空时关联 `items.item_id`
- `created_at / updated_at`：维护时间

固定孔位映射：

- `slot 1 -> attr_only -> required_star 3`
- `slot 2 -> attr_only -> required_star 6`
- `slot 3 -> skill_only -> required_star 8`
- `slot 4 -> skill_only -> required_star 10`

镶嵌边界：

- 未解锁孔位 `gem_item_id` 必须为空
- `attr_only` 只能放属性宝石（`items.sub_type = attr_gem`）
- `skill_only` 只能放技能宝石（`items.sub_type = skill_gem`）

## 统计口径（穿戴统计层）

### 套装件数统计

- 数据来源：仅统计 `player_equipment_loadouts` 当前穿戴实例
- 参与统计位：
- `main_weapon / sub_weapon / armor / leg / shoe / cloak / helmet / necklace / bracelet_1 / bracelet_2 / ring_1 / ring_2`
- `talisman` 不参与套装件数统计

### 护符星级连锁统计

- 数据来源：仅统计当前穿戴实例的实际 `star`
- 参与统计位与套装件数统计相同
- `talisman` 不参与护符星级连锁统计

## 进阶后实例更新规则

当套装装备实例进阶到下一档时：

1. 更新 `item_id`
2. 更新 `set_level`
3. 更新 `max_star`
4. 保留 `star` 不变

示例：20 级 3 星进阶到 40 级后，`star` 仍为 3，`max_star` 从 3 提升为 6。

## 数据源与导入

- 示例与校验源：`data/player_equipment_instance_examples_v1.json`
- 导入服务：`App\Services\PlayerEquipmentInstanceImportService`
- Seeder：`PlayerEquipmentInstanceExamplesSeeder`

导入前会执行结构与业务规则校验，不允许额外穿戴位、额外孔位规则或额外实例类型。
