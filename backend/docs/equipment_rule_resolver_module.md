# Equipment Rule Resolver Module

## 模块范围

本模块仅包含装备执行规则层 / 规则求值层。  
只做判断、求值、输出结果，不写库，不包含 controller / API / 战斗结算 / 背包事务 / 掉落发奖 / 打造事务 / 宝石装卸写库。

## 统一返回结构

5 个 resolver 的公开方法统一返回：

```json
{
  "ok": true,
  "reason": null,
  "data": {}
}
```

失败时：

```json
{
  "ok": false,
  "reason": "failure_reason",
  "data": null
}
```

其中 `EquipmentStageProgressionResolver` 在失败时仍返回：

```json
{
  "ok": false,
  "reason": "failure_reason",
  "data": {
    "can_progress": false,
    "next_state": null
  }
}
```

## Resolver 职责

### 1) EquipmentSetEffectResolver

- 输入：`loadouts / equipmentInstances / setEffects`
- 职责：统计当前穿戴实例套装件数并输出已生效套装效果
- 输出字段：
- `data.set_counts`
- `data.activated_effects`

### 2) TalismanStarLinkResolver

- 输入：`loadouts / equipmentInstances / talismanInstance / talismanStarLinks`
- 职责：判断当前护符 6/8/9/10 星连锁是否成立
- 输出字段：
- `data.qualified_star_links`
- `data.missing_requirements`

### 3) EquipmentGemSlotResolver

- 输入：
- `resolveUnlockedSlots(instance, slotUnlockRules)`
- `canSocketGem(instance, slotIndex, gemItem, gemConfig, slotUnlockRules)`
- 职责：输出已解锁孔位、校验宝石是否可镶嵌指定孔

### 4) EquipmentStageProgressionResolver

- 输入：`instance / progressionRules / recipeMap`
- 职责：判断套装装备是否可进阶，并输出进阶后状态草案
- 输出字段：
- `data.can_progress`
- `data.next_state`

### 5) BlueAffixEligibilityResolver

- 输入：`instance / blueTemplate / blueAffixes / blueAffixSlotRules`
- 职责：输出蓝装实例当前可用蓝词条列表
- 输出字段：
- `data.eligible_affixes`

## 套装件数统计口径

固定参与位：

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

`talisman` 不参与套装件数统计。

## 护符星级连锁统计口径

- 参与统计位与套装件数统计完全一致
- `talisman` 不参与护符星级连锁统计
- 连锁判定为“所有参与统计位都达到 `required_equipment_star`”

## 宝石孔解锁与校验口径

固定孔位规则：

- `3星 -> slot_index=1 -> attr_only`
- `6星 -> slot_index=2 -> attr_only`
- `8星 -> slot_index=3 -> skill_only`
- `10星 -> slot_index=4 -> skill_only`

镶嵌校验：

- 未解锁孔位不可镶嵌
- `attr_only` 只能装 `attr_only` 宝石
- `skill_only` 只能装 `skill_only` 宝石

## 进阶规则口径

- 只处理套装装备实例
- 必须满足当前星级 `>= required_max_star`
- 进阶只输出 `next_state` 草案：
- 更新 `item_id / set_level / max_star`
- 保留 `star`
- 本模块不写库

## 蓝词条规则口径

- 只处理蓝装实例
- 只按模板位、实例位、等级档和词条位规则过滤
- 只输出可用词条列表 `eligible_affixes`
- 不做随机抽取
- 不做权重抽签
- 不做数值 roll
- 不做提取 / 洗练 / 重铸
