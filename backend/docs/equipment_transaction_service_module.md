# Equipment Transaction Service Module

本模块只覆盖装备执行事务层，不包含 controller、API、战斗结算、掉落发奖、商城购买、礼包发放、完整背包 UI 与蓝词条随机生成。

## 统一返回结构

5 个事务 service 的公开方法统一返回：

```json
{
  "ok": true,
  "reason": null,
  "data": {}
}
```

失败时统一返回：

```json
{
  "ok": false,
  "reason": "具体失败原因",
  "data": null
}
```

字段命名与 `data/equipment_transaction_service_examples_v1.json` 保持一致。

## 事务 Service 职责

### EquipmentEquipService

- 负责穿戴装备到目标穿戴位。
- 若目标位已有旧装备，先取消旧装备 `is_equipped`。
- 同步更新当前实例 `is_equipped` 与 `player_equipment_loadouts`。
- 对 `ring` 与 `bracelet` 只处理双位兼容，不额外扩展别的穿戴流。

### EquipmentStarUpgradeService

- 负责套装装备升星事务。
- 校验实例归属、套装类型、星级上限与升星消耗配置。
- 扣除玩家档案中的升星材料。
- 更新 `star`。
- 升到 3 / 6 / 8 / 10 时，同步 `player_equipment_gem_slots.is_unlocked`。

### EquipmentStageProgressionService

- 负责套装装备进阶事务。
- 强制先走 `EquipmentStageProgressionResolver` 计算 `next_state`。
- 校验图纸与附加材料。
- 扣除图纸与附加材料。
- 更新 `item_id / set_level / max_star`，保留 `star`。
- 已存在的宝石孔记录继续沿用，不做回退。

### EquipmentGemSocketService

- 负责宝石镶嵌事务。
- 强制优先调用 `EquipmentGemSlotResolver` 做孔位与类型校验。
- 校验实例归属、孔位存在、孔位已解锁、孔位为空、宝石归属与宝石类型。
- 成功时只写 `player_equipment_gem_slots.gem_item_id`。
- 不允许直接覆盖已有宝石；已有宝石必须先卸下再镶嵌。

### EquipmentGemUnsocketService

- 负责宝石卸下事务。
- 校验实例归属、孔位存在、孔位已有宝石。
- 成功时把 `player_equipment_gem_slots.gem_item_id` 清空。

## 为什么事务层依赖 Resolver

- `EquipmentGemSlotResolver` 已定义固定的 3 / 6 / 8 / 10 星孔位解锁规则，以及 `attr_only / skill_only` 的类型匹配规则。事务层复用它，避免再复制一套孔位与宝石类型判断。
- `EquipmentStageProgressionResolver` 已定义套装进阶的可进阶条件、下一档 `next_state` 以及 recipe 命中规则。事务层强制先用 resolver 判断，再执行扣料与写库。
- 事务层只负责“锁数据、扣材料、写状态、保证原子性”，不重新实现复杂规则层。

## 写库边界

### 装备穿戴

- 写 `player_equipment_instances.is_equipped`
- 写 `player_equipment_instances.slot_type`
- 写 `player_equipment_loadouts`

### 装备升星

- 锁 `player_equipment_instances`
- 锁 `shop_player_profiles`
- 扣玩家档案材料 / 货币
- 写 `player_equipment_instances.star`
- 写 `player_equipment_gem_slots.is_unlocked`

### 装备进阶

- 锁 `player_equipment_instances`
- 锁 `shop_player_profiles`
- 扣图纸与附加材料
- 写 `player_equipment_instances.item_id / set_level / max_star / set_id`
- 不删除既有 `player_equipment_gem_slots`

### 宝石镶嵌

- 锁 `player_equipment_instances`
- 锁 `player_equipment_gem_slots`
- 锁 `shop_player_profiles` 仅用于校验玩家拥有该宝石
- 写 `player_equipment_gem_slots.gem_item_id`

### 宝石卸下

- 锁 `player_equipment_instances`
- 锁 `player_equipment_gem_slots`
- 写 `player_equipment_gem_slots.gem_item_id = null`

## 非本轮范围

- 不包含 controller / API
- 不包含战斗结算
- 不包含掉落发奖
- 不包含商城事务
- 不包含礼包事务
- 不包含完整背包 UI
- 不包含蓝词条随机生成
