# Talisman Module

## 模块定位

`talismans` 是正式护符基础表，配套 `talisman_tiers`、`talisman_tier_upgrade_costs`、`talisman_star_links` 共同定义护符成长链与星级连锁规则。

- 护符成品本体是 `item`
- `talismans.item_id` 必须统一引用 `items.item_id`
- 护符模块只维护配置与成长定义
- 商城、礼包、奖励等外围模块后续都应通过 `item_id` 引用护符成品

本模块明确不处理：

- 穿戴实现
- 战斗执行逻辑
- 掉落逻辑
- 商城投放逻辑
- 完整强化 / 洗练 / 突破系统

## talismans 字段说明

- `id`
  - 数据库自增主键
- `talisman_id`
  - 护符业务唯一 ID
- `item_id`
  - 对应护符成品 item，必须命中 `items.item_id`
- `talisman_name`
  - 内部名称
- `display_name`
  - 前端展示名称
- `talisman_type`
  - 固定枚举：`common_talisman` / `sect_talisman`
- `recommended_sect`
  - 固定枚举：`none` / `bing` / `jingang` / `fulu`
- `quality`
  - 玩法品质
- `rarity`
  - UI 展示稀有度
- `unlock_level`
  - 解锁等级
- `icon`
  - 图标路径
- `summary`
  - 效果简述
- `sort_order`
  - 排序
- `is_enabled`
  - 是否启用
- `remark`
  - 备注

## talisman_tiers 字段说明

- `talisman_id`
  - 关联 `talismans.talisman_id`
- `tier_no`
  - 固定只允许 `1 / 2 / 3`
- `tier_name`
  - 阶级名称
- `effect_key`
  - 护符基础效果 key
- `value_type`
  - 固定枚举：`flat` / `percent`
- `value`
  - 护符基础效果数值
- `trigger_rule`
  - 首版支持：
  - `passive_always`
  - `on_skill_cast`
  - `on_hit`
  - `low_hp`
- `cooldown_sec`
  - 触发型冷却秒数；常驻型通常为 `0`
- `summary`
  - 护符基础效果说明

## talisman_tier_upgrade_costs 字段说明

- `talisman_id`
  - 关联 `talismans.talisman_id`
- `tier_no`
  - 当前阶级
- `target_tier_no`
  - 目标阶级
- `item_id`
  - 消耗物品，关联 `items.item_id`
- `count`
  - 消耗数量
- `sort_order`
  - 排序
- `is_enabled`
  - 是否启用
- `remark`
  - 备注

它支持多条记录，因此一个升阶动作可以同时消耗多种材料。

## talisman_star_links 字段说明

- `talisman_id`
  - 关联 `talismans.talisman_id`
- `tier_no`
  - 关联当前护符阶级
- `required_equipment_star`
  - 固定只允许 `6 / 8 / 9 / 10`
- `effect_key`
  - 连锁增益 key
- `value_type`
  - 固定枚举：`flat` / `percent`
- `value`
  - 连锁增益数值
- `summary`
  - 连锁效果说明
- `sort_order`
  - 排序
- `is_enabled`
  - 是否启用
- `remark`
  - 备注

## 3 阶规则

- 护符固定 3 阶
- 每个护符必须完整配置：
  - `tier_no = 1`
  - `tier_no = 2`
  - `tier_no = 3`
- 本模块不会扩展到更多阶级

## 升阶消耗规则

- 只允许配置：
  - `1 -> 2`
  - `2 -> 3`
- `3 阶` 不再有升级消耗
- 同一升阶动作允许多条消耗记录，表示多种材料
- 首版升阶消耗统一通过 `talisman_tier_upgrade_costs.item_id + count` 描述

## 6 / 8 / 9 / 10 星连锁规则

- 护符支持固定门槛：
  - `6`
  - `8`
  - `9`
  - `10`
- 每个阶级都应配置这四档星级连锁
- 连锁基础效果与基础护符效果共用统一表达：
  - `effect_key`
  - `value_type`
  - `value`

### 正式判定说明

连锁条件不是总星数累计，而是：

- 所有参与统计的装备位都至少达到 `required_equipment_star`
- 护符位不参与全身装备星级统计

当前护符模块导出的静态规则中，参与统计的装备位固定为：

- `main_weapon`
- `off_weapon`
- `armor`
- `belt`
- `shoes`
- `gloves`
- `helm`
- `necklace`
- `ring`
- `bracelet`

排除位固定为：

- `talisman`

装备星级统计逻辑由装备模块实现；护符模块只负责定义这个判定条件与对应奖励。

## recommended_sect 规则

- `recommended_sect` 只用于推荐展示
- 它不是穿戴限制
- 也不是领取限制
- `none` 表示通用护符，不偏向任何宗门

## effect_key 规则

首版基础与连锁效果统一使用同一套 key，当前至少包括：

- `bonus_melee_atk`
- `bonus_hp`
- `bonus_skill_dmg`
- `bonus_damage_reduction`
- `bonus_flame_damage`
- `bonus_frost_damage`
- `bonus_guard_shield`
- `bonus_crit_dmg`
- `bonus_final_damage`

## 与 items 的关系

- 护符成品是 `item`
- `talismans.item_id -> items.item_id`
- `items.main_type` 必须为 `talisman`
- `items.sub_type` 必须与 `talismans.talisman_type` 对齐
- 商城 / 礼包 / 奖励后续都应通过 `item_id` 引用护符
- 不应直接使用 `talisman_id` 做发奖或售卖对象

## 导入导出

- 项目源文件：`data/talisman_module_v1.json`
- Seeder：`TalismansSeeder`
- 导出命令：`php artisan game:export-talisman-module`
- 导出文件：`talisman_module_v1.json`
- 配置 bundle 已纳入 `talisman_module_v1.json`

## 首版边界

本轮只做：

- 护符基础表
- 护符 3 阶成长配置
- 多材料升阶消耗
- 6 / 8 / 9 / 10 星连锁配置

本轮不做：

- 穿戴实现
- 战斗执行实现
- 掉落
- 商城投放
- 其他装备系统扩展
