# Equipment Star Module

## 模块定位

本轮只定义套装升星模块的规则层，涉及四张正式配置表：

- `equipment_star_rules`
- `equipment_star_upgrade_costs`
- `equipment_star_slot_unlocks`
- `equipment_stage_progression_rules`

本轮明确不处理：

- 宝石镶嵌执行逻辑
- 升星前端表现
- 升星概率系统
- 跨档打造执行逻辑
- 装备实例数据迁移逻辑

## 总体规则

- 每件套装装备支持升星
- 不同套装档位有不同星级上限：
  - `20 -> 3 星`
  - `40 -> 6 星`
  - `50 -> 8 星`
  - `60 -> 10 星`
- 首版升星固定为必成制：
  - `star_mode = always_success`
  - 不做失败
  - 不做掉星
  - 不做保底
- 升星支持多材料消耗
- 升星核心材料来自星砂副本产物
- 满当前档最高星才能进阶
- 进阶后保留当前星级

## equipment_star_rules 字段说明

- `id`
  - 数据库自增主键
- `set_level`
  - 套装档位，固定只允许 `20 / 40 / 50 / 60`
- `max_star`
  - 该档最大星级，固定映射：
  - `20 -> 3`
  - `40 -> 6`
  - `50 -> 8`
  - `60 -> 10`
- `summary`
  - 规则说明
- `sort_order`
  - 排序
- `is_enabled`
  - 是否启用
- `remark`
  - 备注
- `created_at` / `updated_at`
  - 后台维护时间戳

## equipment_star_upgrade_costs 字段说明

- `id`
  - 数据库自增主键
- `set_level`
  - 套装档位，固定只允许 `20 / 40 / 50 / 60`
- `from_star`
  - 当前星级
- `to_star`
  - 目标星级，必须等于 `from_star + 1`
- `item_id`
  - 消耗物品，必须关联 `items.item_id`
- `count`
  - 消耗数量，必须大于 `0`
- `sort_order`
  - 同一升星动作内的材料排序
- `is_enabled`
  - 是否启用
- `remark`
  - 备注
- `created_at` / `updated_at`
  - 后台维护时间戳

同一个 `set_level + from_star + to_star` 允许存在多条记录，用于表达一笔升星动作同时消耗多种材料。

## equipment_star_slot_unlocks 字段说明

- `id`
  - 数据库自增主键
- `required_star`
  - 解锁该孔所需星级，固定只允许 `3 / 6 / 8 / 10`
- `slot_index`
  - 孔位序号，固定只允许 `1 / 2 / 3 / 4`
- `slot_group`
  - 孔位分组，固定只允许 `attr_only / skill_only`
- `summary`
  - 规则说明
- `sort_order`
  - 排序
- `is_enabled`
  - 是否启用
- `remark`
  - 备注
- `created_at` / `updated_at`
  - 后台维护时间戳

固定映射如下：

- `3 星 -> 第 1 孔 -> attr_only`
- `6 星 -> 第 2 孔 -> attr_only`
- `8 星 -> 第 3 孔 -> skill_only`
- `10 星 -> 第 4 孔 -> skill_only`

其中：

- 前两个孔只能放属性宝石
- 后两个孔只能放技能宝石

## equipment_stage_progression_rules 字段说明

- `id`
  - 数据库自增主键
- `from_set_level`
  - 当前档位，固定只允许 `20 / 40 / 50`
- `to_set_level`
  - 目标档位，固定只允许：
  - `20 -> 40`
  - `40 -> 50`
  - `50 -> 60`
- `required_max_star`
  - 当前档进阶所需满星，固定映射：
  - `20 -> 40` 需要 `3`
  - `40 -> 50` 需要 `6`
  - `50 -> 60` 需要 `8`
- `star_keep_mode`
  - 固定为 `keep_current_star`
- `summary`
  - 规则说明
- `sort_order`
  - 排序
- `is_enabled`
  - 是否启用
- `remark`
  - 备注
- `created_at` / `updated_at`
  - 后台维护时间戳

## 进阶规则说明

- 必须达到当前档最高星才能进阶下一档
- 20 级套装必须满 `3 星` 才能进阶到 40 级
- 40 级套装必须满 `6 星` 才能进阶到 50 级
- 50 级套装必须满 `8 星` 才能进阶到 60 级
- 进阶后保留当前星级，不重置为 0 星

## 导入导出

- 项目源文件：`data/equipment_star_module_v1.json`
- Seeder：`EquipmentStarModuleSeeder`
- 导出命令：`php artisan game:export-equipment-star-module`
- 导出文件：`equipment_star_module_v1.json`
- 配置 bundle 已纳入 `equipment_star_module_v1.json`

## 本轮边界

本轮只做：

- 四张规则表与后台维护
- 严格按 `equipment_star_module_v1.json` 落地
- 规则导入导出
- 满星进阶与保留星级的规则定义

本轮不做：

- 升星执行逻辑
- 镶嵌执行逻辑
- 跨档打造执行逻辑
- 概率、失败、掉星、保底系统
