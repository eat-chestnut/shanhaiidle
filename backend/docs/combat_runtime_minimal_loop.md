# Combat Runtime Minimal Loop

## 模块边界

- 本轮只做最小战斗闭环。
- 入口只消费 `BattleStartPayload`。
- 本轮不做技能系统、Buff 时序、DOT/HOT、控制效果、掉落结算、奖励发放、controller、API、前端 UI。

## 5 个组件职责

### BattleRuntimeStateBuilder

- 将 `BattleStartPayload` 转成 `BattleRuntimeState`。
- 负责构建 `player_unit / enemy_units / battle_context / logs`。
- 初始化 `battle_id / status / tick / current_wave_index`。

### BasicDamageResolver

- 负责最小伤害结算。
- 玩家攻击敌人：
  - `effective_attack = base_attack`
  - 若存在 `bonus_melee_atk`，按百分比或 flat 做简单加成
  - `damage = max(1, effective_attack - enemy_def)`
- 若目标为 boss 且存在 `bonus_boss_dmg`，在上式结果基础上追加 boss 增伤。
- 敌人攻击玩家：
  - `damage = max(1, enemy_attack - player_def)`

### CombatTickRunner

- 推进单次 tick。
- 执行玩家普攻、敌人反击、死亡标记、波次切换、胜负判定与日志写入。

### BattleVictoryResolver

- 负责将当前战斗状态判断为：
  - `ongoing`
  - `victory`
  - `defeat`

### BattleResultBuilder

- 将最终 `BattleRuntimeState` 整理成最小结果对象。
- 输出：
  - `battle_result`
  - `elapsed_ticks`
  - `remaining_player_hp`
  - `remaining_enemy_count`
  - `cleared_wave_count`
  - `logs`

## BattleRuntimeState 结构

```json
{
  "battle_id": "battle_runtime_10001_xxxxxxxx",
  "status": "running",
  "tick": 0,
  "current_wave_index": 1,
  "player_unit": {
    "unit_id": "player_10001",
    "side": "player",
    "current_hp": 850,
    "max_hp": 850,
    "stats": {
      "MELEE_ATK": 120,
      "HP": 850,
      "DEF": 66
    },
    "bonus_stats": {},
    "special_effects": [],
    "alive": true
  },
  "enemy_units": [
    {
      "unit_id": "enemy_mon_qingqiu_guard_1_1",
      "monster_id": "mon_qingqiu_guard",
      "side": "enemy",
      "wave_index": 1,
      "unit_index": 1,
      "is_boss": false,
      "current_hp": 500,
      "max_hp": 500,
      "stats": {
        "HP": 500,
        "ATK": 40,
        "DEF": 12
      },
      "skills": [],
      "tags": [],
      "alive": true
    }
  ],
  "battle_context": {
    "battle_type": "main_stage"
  },
  "logs": []
}
```

## Tick 流程

每个 tick 固定流程如下：

1. 若战斗已结束，直接返回当前状态。
2. 玩家对当前波次 `unit_index` 最小的存活敌人发动一次 `basic_attack`。
3. 当前波次所有存活敌人对玩家各发动一次 `basic_attack`。
4. 更新 `current_hp`。
5. `current_hp <= 0` 的单位标记 `alive = false`。
6. 若当前波次敌人全灭，则切到下一波。
7. 调用 `BattleVictoryResolver` 判定 `ongoing / victory / defeat`。
8. 写入最小日志。

## 日志

当前最小日志覆盖：

- 玩家普攻
- 敌人普攻
- 单位死亡
- 波次切换
- 战斗结束

攻击日志结构与示例对齐：

```json
{
  "tick": 1,
  "actor": "player_10001",
  "target": "enemy_mon_qingqiu_guard_1_1",
  "action": "basic_attack",
  "damage": 108
}
```

状态类日志额外会写入 `action = unit_dead / wave_switch / battle_end`。

## 胜负判定

- 若 `player_unit.alive = false`，结果为 `defeat`
- 若所有 `enemy_units` 都已死亡，结果为 `victory`
- 其他情况为 `ongoing`

## 统一返回结构

所有公开方法统一返回：

```json
{
  "ok": true,
  "reason": null,
  "data": {}
}
```
