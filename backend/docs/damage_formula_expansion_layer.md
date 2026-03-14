# Damage Formula Expansion Layer

## Scope

- 本轮只扩展战斗内伤害公式层。
- 本轮已接入：
  - 普攻伤害
  - 技能伤害
  - 暴击 / 暴伤
  - Boss 增伤
  - 技能增伤
  - 最终伤害修正
  - 护盾优先吸收
- 本轮不做：
  - 抗性系统
  - 元素克制
  - DOT/HOT
  - 命中/闪避
  - 治疗系统
  - controller / api

## 5 个核心组件

### `CriticalStrikeResolver`

- 负责暴击判定与暴伤倍率计算。
- 读取：
  - `bonus_crit_rate`
  - `bonus_crit_dmg`
- 输出：
  - `is_critical`
  - `critical_multiplier`

规则：

- `bonus_crit_rate` 参与暴击率计算。
- `bonus_crit_dmg` 参与暴伤倍率计算。
- 首版暴伤倍率：
  - `crit_multiplier = 1 + crit_damage_percent`

### `DamageModifierResolver`

- 负责解析伤害乘区。
- 普攻读取 `bonus_melee_atk`。
- 技能读取 `bonus_skill_dmg`。
- 目标为 boss 时才读取 `bonus_boss_dmg`。
- 最终乘区读取 `final_damage_bonus`。
- 为兼容当前运行时聚合数据，内部也会桥接 `bonus_final_damage` 到最终乘区。

输出：

- `attack_multiplier`
- `skill_multiplier`
- `boss_multiplier`
- `final_multiplier`

### `ShieldAbsorptionResolver`

- 负责护盾优先吸收伤害。
- 先扣 `shield`，再扣 `current_hp`。
- 首版不区分护盾类型，不处理护盾持续时间。

输出：

- `absorbed_by_shield`
- `hp_damage`
- `remaining_shield`

### `ExpandedDamageResolver`

- 负责整合基础攻击、技能倍率、Boss 增伤、暴击、防御扣减与护盾吸收。
- Runtime 中的玩家与敌人普攻、技能都统一调用这个组件。

输出至少包含：

- `raw_damage`
- `is_critical`
- `shield_absorbed`
- `hp_damage`

### `DamageActionLogger`

- 负责记录扩展后的伤害日志。
- 普攻与技能统一写入 `runtime_state.logs`。

日志字段：

- `tick`
- `actor`
- `target`
- `action`
- `skill_id`
- `raw_damage`
- `is_critical`
- `shield_absorbed`
- `hp_damage`

## 普攻公式

```text
base_attack = attacker_attack
pre_def_damage = base_attack * attack_multiplier * boss_multiplier * crit_multiplier
post_def_damage = max(1, pre_def_damage - target_def)
final_damage = post_def_damage * final_multiplier
```

结算完成后必须进入 `ShieldAbsorptionResolver`。

## 技能公式

```text
base_skill_damage = attacker_attack * damage_ratio
pre_def_damage = base_skill_damage * skill_multiplier * boss_multiplier * crit_multiplier
post_def_damage = max(1, pre_def_damage - target_def)
final_damage = post_def_damage * final_multiplier
```

结算完成后必须进入 `ShieldAbsorptionResolver`。

## 暴击规则

- 暴击率只在本轮读取 `bonus_crit_rate`。
- 暴伤只在本轮读取 `bonus_crit_dmg`。
- 本轮不扩展额外暴击来源、暴击免疫或命中判定。

## Boss 增伤规则

- 只有当 `target_unit.is_boss = true` 时才应用 `bonus_boss_dmg`。
- 非 boss 目标时 `boss_multiplier = 1.0`。

## 护盾优先吸收规则

- 先消耗 `shield`
- 再结算 `current_hp`
- 不区分护盾类型
- 不处理护盾持续时间

## Runtime 接入

- 玩家普攻：`ExpandedDamageResolver::resolveBasicAttack(...)`
- 玩家技能：`ExpandedDamageResolver::resolveSkillDamage(...)`
- 敌人普攻：`ExpandedDamageResolver::resolveBasicAttack(...)`
- 敌人技能：`ExpandedDamageResolver::resolveSkillDamage(...)`
- 统一日志：`DamageActionLogger::logDamage(...)`

## 统一返回结构

所有公开方法统一返回：

```json
{
  "ok": true,
  "reason": null,
  "data": {}
}
```
