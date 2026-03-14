# Buff / Special Effect Execution Minimal Layer

## 模块边界

- 本轮只做 Buff / 特殊效果执行最小闭环层。
- 本轮只支持两类 `trigger_timing`:
  - `passive_always`
  - `on_battle_start`
- 本轮只支持两类落点方向：
  - 常驻标签 / 常驻基础修正
  - 开场护盾 / 开场基础修正

本轮明确不做：

- DOT / HOT
- 控制效果
- 驱散
- 完整 Buff 生命周期
- 复杂叠层
- controller / API
- 前端表现

## 5 个核心组件

### RuntimeEffectStateBuilder

- 负责把 `special_effects` 转成运行时可执行的 `runtime_effects`。
- 当前只接收并保留最小字段：
  - `effect_key`
  - `owner_unit_id`
  - `source`
  - `effect_type`
  - `trigger_timing`
  - `value`
  - `enabled`
- 若效果属于 modifier 类型，会额外保留 `modifier_key`。
- 首版只接受：
  - `passive_tag`
  - `passive_modifier`
  - `battle_start_shield`
  - `battle_start_modifier`

### PassiveEffectApplier

- 负责执行 `passive_always`。
- 首版只处理：
  - `passive_tag`
  - `passive_modifier`
- 落点：
  - `runtime_tags`
  - `runtime_modifiers`

### BattleStartEffectApplier

- 负责执行 `on_battle_start`。
- 首版只处理：
  - `battle_start_shield`
  - `battle_start_modifier`
- 落点：
  - `shield`
  - `runtime_modifiers`
- `on_battle_start` 执行后会把对应 `runtime_effects[*].enabled` 置为 `false`，确保只在战斗开始时执行一次。

### SpecialEffectTriggerResolver

- 负责按 `trigger_timing` 做最小筛选。
- 首版只支持：
  - `passive_always`
  - `on_battle_start`

### EffectActionLogger

- 负责把效果应用写入运行时日志。
- 当前日志结构最小字段：
  - `tick`
  - `actor`
  - `action = effect_apply`
  - `effect_key`
  - `value`
  - `source`

## runtime_effects 结构

运行时单位中的 `runtime_effects` 与 `data/buff_special_effect_execution_minimal_examples_v1.json` 对齐：

```json
[
  {
    "effect_key": "boss_hunt_tag",
    "owner_unit_id": "player_10001",
    "source": "core_qingqiu_frost",
    "effect_type": "passive_tag",
    "trigger_timing": "passive_always",
    "value": null,
    "enabled": true
  },
  {
    "effect_key": "always_bonus_def_flat",
    "owner_unit_id": "player_10001",
    "source": "set_zhaoyao_40_4pc",
    "effect_type": "passive_modifier",
    "trigger_timing": "passive_always",
    "value": 15,
    "modifier_key": "bonus_def_flat",
    "enabled": true
  }
]
```

## runtime 初始化接入

当前最小接入流程为：

1. `BattleRuntimeStateBuilder` 先构建基础 `BattleRuntimeState`
2. `RuntimeEffectStateBuilder` 生成单位级 `runtime_effects`
3. `PassiveEffectApplier` 应用 `passive_always`
4. `BattleStartEffectApplier` 应用 `on_battle_start`
5. `EffectActionLogger` 写入 `effect_apply` 日志

## 运行时单位最小新增字段

本轮为运行时单位补充以下最小字段：

- `runtime_effects`
- `runtime_modifiers`
- `runtime_tags`
- `shield`

各字段当前职责：

- `runtime_effects`: 运行时效果状态登记
- `runtime_modifiers`: 被动与开场基础修正落点
- `runtime_tags`: 常驻标签落点
- `shield`: 开场护盾落点

## 统一返回结构

所有公开方法统一返回：

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
  "reason": "error_code",
  "data": null
}
```
