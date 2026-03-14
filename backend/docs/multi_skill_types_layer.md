# Multi Skill Types Layer

## 模块边界

- 本轮只扩展战斗内“多技能类型最小扩展层”。
- 本轮只支持 4 类新增技能类型：
  - `multi_hit`
  - `aoe`
  - `self_buff`
  - `shield`
- 仍保留已存在的 `single_damage`。

本轮明确不做：

- DOT / HOT
- 控制效果
- 复杂指向 / 复杂选敌
- 仇恨系统
- controller / API
- 前端表现 / 动画

## 核心组件

### `MultiSkillTypeResolver`

- 作为统一分发入口。
- 根据 `skill_type` 把技能分发到对应处理器：
  - `single_damage` -> `ExpandedDamageResolver`
  - `multi_hit` -> `MultiHitDamageResolver`
  - `aoe` -> `AoEDamageResolver`
  - `self_buff` / `shield` -> `SelfBuffShieldResolver`
- 统一输出：
  - `actor_unit`
  - `target_units`
  - `entries`

### `MultiHitDamageResolver`

- 负责多段单体技能。
- 逐段循环调用 `ExpandedDamageResolver`。
- 每段都会立即把结果回写到当前目标的 `current_hp / shield`。
- 输出的每段结果至少包含：
  - `raw_damage`
  - `hp_damage`
  - `shield_absorbed`
  - `is_critical`
  - `hit_index`

### `AoEDamageResolver`

- 负责小范围直伤技能。
- 对目标波内的每个有效目标分别调用 `ExpandedDamageResolver`。
- 当前最小实现只按传入的 `target_indexes` 或目标数组执行，不扩展复杂范围判定。
- 每个目标结果至少包含：
  - `raw_damage`
  - `hp_damage`
  - `shield_absorbed`
  - `is_critical`

### `SelfBuffShieldResolver`

- 负责非伤害型最小技能：
  - `self_buff`
  - `shield`
- `self_buff` 只写入 `runtime_modifiers`
- `shield` 只写入 `shield`
- 两类技能都返回零伤害占位字段，保持统一输出口径：
  - `raw_damage = 0`
  - `hp_damage = 0`
  - `shield_absorbed = 0`

## 运行时技能状态结构

运行时技能状态继续由 `SkillRuntimeStateBuilder` 生成，基础公共字段为：

- `skill_id`
- `owner_unit_id`
- `skill_type`
- `cooldown_total`
- `cooldown_remaining`
- `auto_cast`
- `enabled`

各技能类型的最小附加字段：

### `single_damage`

- `damage_ratio`

### `multi_hit`

- `multi_hit_count`
- `hit_damage_ratios`

说明：

- 若提供 `hit_damage_ratios`，其长度必须等于 `multi_hit_count`
- 每段会按对应倍率单独结算

### `aoe`

- `damage_ratio`

### `self_buff`

- `modifier_key`
- `modifier_value`

### `shield`

- `shield_value`

## 最小执行规则

### `multi_hit`

1. 锁定一个主目标
2. 读取 `multi_hit_count`
3. 按 `hit_damage_ratios` 逐段调用 `ExpandedDamageResolver`
4. 每段都即时回写目标 `current_hp / shield`
5. 每段都生成一条 `skill_cast` 日志

### `aoe`

1. 由 `SkillCastResolver` 选出当前波有效目标索引
2. 对当前波每个目标分别调用 `ExpandedDamageResolver`
3. 每个目标分别回写 `current_hp / shield`
4. 每个目标分别生成一条 `skill_cast` 日志

### `self_buff`

1. 不依赖敌方目标
2. 按 `modifier_key + modifier_value` 写入施法者 `runtime_modifiers`
3. 生成一条 `effect_apply` 日志

### `shield`

1. 不依赖敌方目标
2. 按 `shield_value` 写入施法者 `shield`
3. 生成一条 `effect_apply` 日志

## SkillCastResolver 接入

- `SkillCooldownResolver::canCast(...)` 现在允许：
  - `single_damage`
  - `multi_hit`
  - `aoe`
  - `self_buff`
  - `shield`
- 玩家施法选择规则：
  - `single_damage / multi_hit`：当前波前排单体目标
  - `aoe`：当前波全部存活目标
  - `self_buff / shield`：施法者自身
- 敌方施法最小规则：
  - `single_damage / multi_hit / aoe`：玩家
  - `self_buff / shield`：敌人自身

## 日志说明

本层不新增日志系统，继续复用：

- `DamageActionLogger`
- `EffectActionLogger`

### `skill_cast`

伤害型技能统一写入 `skill_cast`：

- `single_damage`
- `multi_hit`
- `aoe`

最小字段：

- `tick`
- `actor`
- `target`
- `action = skill_cast`
- `skill_id`
- `raw_damage`
- `is_critical`
- `shield_absorbed`
- `hp_damage`

补充字段：

- `multi_hit` 会额外写 `hit_index`

### `effect_apply`

非伤害型技能统一写入 `effect_apply`：

- `self_buff`
- `shield`

最小字段：

- `tick`
- `actor`
- `action = effect_apply`
- `effect_key`
- `value`
- `source`

## CombatTickRunner 接入

- `CombatTickRunner` 的技能分支现在统一走 `MultiSkillTypeResolver`
- Resolver 返回 `entries` 后，再由 `CombatTickRunner` 复用日志组件写入：
  - 伤害型 -> `DamageActionLogger`
  - 效果型 -> `EffectActionLogger`
- 技能成功执行后仍沿用原有冷却逻辑：
  - 先进入冷却
  - 回合末再 `tick` 一次

## JSON 示例对齐

- 对齐文件：`data/multi_skill_types_examples_v1.json`
- 当前示例覆盖：
  - 双段单体 `multi_hit`
  - 目标波直伤 `aoe`
  - 自增益 `self_buff`
  - 护盾 `shield`

## 统一返回结构

所有公开方法统一返回：

```json
{
  "ok": true,
  "reason": null,
  "data": {}
}
```
