# DOT / HOT / Status Control Minimal Layer

## 模块边界

- 本轮只实现 DOT / HOT / 控制效果的最小闭环。
- 严格按 `data/dot_hot_status_control_minimal_examples_v1.json` 的字段口径落地。
- 本轮支持：
  - `dot`
  - `hot`
  - `status_control`
- `status_control` 当前最小支持：
  - `stunned`
  - `slowed`
  - `silenced`

本轮明确不做：

- 复杂叠层
- 驱散
- 抗性 / 免疫
- DOT/HOT 复杂乘区
- 前端表现
- controller / API

## 5 个核心组件

### `DotHotStateBuilder`

- 负责把 DOT/HOT 效果对象转成运行时状态。
- 只接收：
  - `effect_type = dot`
  - `effect_type = hot`
- 只接收：
  - `trigger_timing = per_tick`
- 运行时状态保留 JSON 原字段，并补充：
  - `remaining_ticks`

### `DotHotTickResolver`

- 负责每 tick 推进 DOT/HOT。
- `dot` 每 tick 直接扣 `current_hp`
- `hot` 每 tick 直接回 `current_hp`
- 当前最小规则：
  - `dot` 不扩展护盾交互
  - `hot` 不扩展溢出治疗转换
  - `hot` 只回到 `max_hp`

### `StatusControlResolver`

- 负责控制状态的运行时状态构建与每 tick 推进。
- 只接收：
  - `effect_type = status_control`
  - `trigger_timing = on_apply`
- 运行时状态保留 JSON 原字段，并补充：
  - `remaining_ticks`
  - `applied`
  - `resolved`

当前最小行为：

- `stunned`
  - 禁止本 tick 行动
- `silenced`
  - 禁止本 tick 施放技能
  - 仍允许普攻
- `slowed`
  - 当前只作为运行时状态记录与到期失效
  - 不接速度系统

### `DotHotEffectLogger`

- 负责写 DOT/HOT 每 tick 日志。
- 日志动作：
  - `action = dot_hot_tick`
- 日志字段：
  - `tick`
  - `actor`
  - `target`
  - `effect_key`
  - `effect_type`
  - `hp_damage`
  - `hp_healed`

### `StatusEffectLogger`

- 负责写状态控制日志。
- 日志动作：
  - `action = status_effect`
- 日志字段：
  - `tick`
  - `actor`
  - `target`
  - `effect_key`
  - `status`
  - `status_applied`
  - `status_active`

## Runtime 结构

本轮在 `BattleRuntimeState` 顶层新增：

- `dot_hot_states`
- `status_control_states`

并在单位运行时新增：

- `status`

### `dot_hot_states`

最小结构示例：

```json
[
  {
    "effect_key": "burning_dot",
    "owner_unit_id": "enemy_mon_fire_drake_1_1",
    "target_unit_id": "player_10001",
    "effect_type": "dot",
    "trigger_timing": "per_tick",
    "duration_ticks": 5,
    "damage_per_tick": 30,
    "remaining_ticks": 5
  }
]
```

### `status_control_states`

最小结构示例：

```json
[
  {
    "effect_key": "stun_status",
    "owner_unit_id": "enemy_mon_ice_witch_2_1",
    "target_unit_id": "player_10001",
    "effect_type": "status_control",
    "trigger_timing": "on_apply",
    "duration_ticks": 2,
    "status": "stunned",
    "remaining_ticks": 2,
    "applied": false,
    "resolved": false
  }
]
```

## Tick 接入顺序

`CombatTickRunner` 当前每 tick 最小顺序为：

1. `tick + 1`
2. `StatusControlResolver::tick(...)`
3. `StatusEffectLogger` 写状态日志
4. `DotHotTickResolver::resolve(...)`
5. `DotHotEffectLogger` 写 DOT/HOT 日志
6. 死亡结算
7. 玩家行动
8. 敌人行动
9. 再次死亡结算
10. 技能冷却推进
11. 波次切换
12. 胜负判定

## JSON 对齐规则

### DOT

按 `dot_example`：

- 每 tick 产出 `hp_damage`
- 连续持续 `duration_ticks`
- 示例中 5 tick 均为 30

### HOT

按 `hot_example`：

- 每 tick 产出 `hp_healed`
- 连续持续 `duration_ticks`
- 示例中 3 tick 均为 50

### Status Control

按 `status_control_example`：

- 第 1 tick：
  - `status_applied = true`
- 持续期间后续 tick：
  - `status_active = true`
- 到期后的下一 tick：
  - `status_active = false`

### Combined

按 `combined_example`：

- 同一 tick 允许同时出现：
  - DOT 扣血
  - HOT 治疗
  - Status 生效或失效
- 运行时读取时可以按 tick 汇总得到：
  - `hp_damage`
  - `hp_healed`
  - `status`

## Battle Runtime 初始化

`BattleRuntimeStateBuilder` 当前会从单位的 `special_effects` 中抽取：

- `dot`
- `hot`
- `status_control`

并分别初始化到：

- `dot_hot_states`
- `status_control_states`

原有：

- `passive_always`
- `on_battle_start`

仍继续走既有的 `RuntimeEffectStateBuilder / PassiveEffectApplier / BattleStartEffectApplier`。

## 统一返回结构

所有公开方法统一返回：

```json
{
  "ok": true,
  "reason": null,
  "data": {}
}
```
