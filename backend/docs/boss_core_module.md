# Boss Core Module

## 模块边界

- Boss 核心独立于宝石模块，不写入 `gems`。
- Boss 核心独立于套装件数效果，不占套装效果栏。
- Boss 核心成品必须 item 化，通过 `items.item_id` 承接。
- 首版每个 Boss 核心只保留 1 条主效果。

## 表结构

### boss_cores

- `core_id`
  - 业务唯一 ID。
- `item_id`
  - 关联 `items.item_id`，且必须满足：
  - `main_type = boss_core`
  - `sub_type = boss_core`
- `core_name`
  - 内部名称。
- `display_name`
  - 展示名称。
- `source_boss_id`
  - 关联 `monsters.monster_id`，且必须指向 Boss 怪物。
- `quality` / `rarity`
  - 统一使用 `white / blue / purple / gold / red`。
- `recommended_sect`
  - 首版枚举：
  - `none`
  - `bing`
  - `jingang`
  - `fulu`
- `recommended_build`
  - 首版枚举：
  - `burst`
  - `survival`
  - `skill_loop`
  - `boss_hunt`
  - `burn`
  - `frost`
- `icon`
  - 图标资源。
- `summary`
  - 核心说明。
- `drop_rate_note`
  - 掉率文本，例如“小概率掉落”。
- `sort_order`
  - 排序。
- `is_enabled`
  - 是否启用。
- `remark`
  - 备注。

### boss_core_effects

- `core_id`
  - 关联 `boss_cores.core_id`。
- `effect_key`
  - 结构化效果键，例如：
  - `bonus_burn_damage`
  - `bonus_frost_damage`
  - `bonus_skill_dmg`
  - `bonus_boss_dmg`
  - `bonus_low_hp_shield`
  - `bonus_melee_atk`
  - `bonus_final_damage`
- `value_type`
  - `flat` 或 `percent`。
- `value`
  - 效果数值。
- `summary`
  - 展示说明。
- `sort_order`
  - 排序。
- `is_enabled`
  - 是否启用。
- `remark`
  - 备注。

## recommended_sect / recommended_build 用途

- `recommended_sect`
  - 用于后台筛选、前端推荐标签和配置说明，不是佩戴限制。
- `recommended_build`
  - 用于表达该核心更适合的构筑方向，例如爆发、生存、寒霜等。

## 导出结构

- `boss_core_module_v1.json` 导出包含：
  - `boss_core_rules`
  - `boss_cores`
  - `boss_core_effects`
- 配置 bundle 已纳入 `boss_core_module_v1.json`。

## 首版约束

- Boss 核心 item 化后，后续掉落、礼包、商城若引用核心，应统一走 `item_id`。
- Boss 核心不与套装件数效果合并计算。
- 首版每个核心只允许 1 条主效果。
