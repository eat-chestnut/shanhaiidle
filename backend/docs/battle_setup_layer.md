# Battle Setup Layer

## 模块边界

- 本轮只做战斗初始化数据组装。
- 本轮不做战斗执行、伤害公式、Buff 时序、技能触发、掉落结算。
- 本轮不做 controller / API，不新增写库事务。

## 4 个 Builder 职责

### PlayerBattleSnapshotBuilder

- 负责组装 `player_snapshot`。
- 直接复用现有 `PlayerEquipmentStatAggregator` 输出的 `base_stats / bonus_stats / special_effects`。
- 补齐 `equipment_summary`，当前包含：
  - `set_counts`
  - `talisman_star_links`
  - `equipped_boss_core_ids`
- 额外从特殊效果里提取 `*_tag` 形态的 `combat_tags`。

### EnemyBattleSnapshotBuilder

- 负责组装 `enemy_snapshots`。
- 读取主线难度怪物配置 `stage_difficulty_monsters`，按 `normal / elite / boss` 展开为固定波次：
  - `normal => wave_index 1`
  - `elite => wave_index 2`
  - `boss => wave_index 3`
- 当前只登记怪物基础属性、技能列表、标签，不做 AI 执行和技能时序。

### BattleContextBuilder

- 负责组装 `battle_context`。
- 当前按 `stageContext` 入参和主线难度表补齐：
  - `battle_type`
  - `stage_id`
  - `difficulty_id`
  - `recommended_power`
  - `is_boss_battle`
  - `scene_id`
  - `settlement_mode`
  - `version`

### BattleStartPayloadBuilder

- 负责统一组合：
  - `player_snapshot`
  - `enemy_snapshots`
  - `battle_context`
  - `debug_sources`
- 只返回战斗起始载荷，不进入实时战斗计算。

## BattleStartPayload 结构

```json
{
  "player_snapshot": {
    "player_id": 10001,
    "base_stats": {},
    "bonus_stats": {},
    "special_effects": [],
    "equipment_summary": {
      "set_counts": [],
      "talisman_star_links": [],
      "equipped_boss_core_ids": []
    },
    "combat_tags": []
  },
  "enemy_snapshots": [
    {
      "monster_id": "stage_01_normal_a",
      "wave_index": 1,
      "unit_index": 1,
      "is_boss": false,
      "base_stats": {},
      "skills": [],
      "tags": []
    }
  ],
  "battle_context": {
    "battle_type": "main_stage",
    "stage_id": "stage_01",
    "difficulty_id": "stage_01_difficulty_1",
    "recommended_power": 100,
    "is_boss_battle": false,
    "scene_id": "scene_stage_01",
    "settlement_mode": "normal",
    "version": "v1"
  },
  "debug_sources": [
    {
      "type": "player_equipment",
      "source": "player_equipment_instances"
    },
    {
      "type": "monster_config",
      "source": "stage_difficulty_monsters"
    }
  ]
}
```

## debug_sources 用途

- 标记本次初始化载荷依赖了哪些配置源和数据表。
- 方便排查玩家快照、怪物快照、上下文字段到底来自哪里。
- 方便后续战斗执行层只消费 `BattleStartPayload`，不反向查询装备和关卡原始表。
