# 蓝装模板模块

## 模块定位

- `blue_equipment_templates` 是蓝装模板主表，模板本身不是 `item`。
- 蓝装成品通过 `blue_equipment_templates.result_item_id` 关联 `items.item_id`。
- 当前模块严格按“部位 × 等级档”配置。
- 本轮不处理蓝词条定义抽取、蓝词条权重抽取、提取逻辑、掉落逻辑、打造执行、装备穿戴逻辑、套装模块和护符模块。

## blue_equipment_templates 字段说明

| 字段 | 说明 |
| --- | --- |
| `id` | 自增主键 |
| `template_id` | 业务唯一 ID |
| `template_name` | 模板内部名称 |
| `display_name` | 模板显示名称 |
| `slot_type` | 部位，只支持 `main_weapon` / `sub_weapon` / `armor` / `leg` / `shoe` / `cloak` / `helmet` / `necklace` / `bracelet` / `ring` |
| `level_band` | 等级档，本轮只允许 `20` / `35` / `45` / `50` / `60` |
| `result_item_id` | 蓝装成品 item_id，必须关联 `items.item_id` |
| `blue_affix_count_min` | 最少蓝词条数 |
| `blue_affix_count_max` | 最多蓝词条数 |
| `quality` | 本轮固定为 `blue` |
| `rarity` | 本轮固定为 `blue` |
| `unlock_level` | 解锁等级 |
| `icon` | 图标 key |
| `summary` | 摘要说明 |
| `sort_order` | 排序值 |
| `is_enabled` | 是否启用 |
| `remark` | 备注 |
| `created_at` / `updated_at` | 时间戳 |

## blue_equipment_template_base_stats 字段说明

| 字段 | 说明 |
| --- | --- |
| `id` | 自增主键 |
| `template_id` | 关联 `blue_equipment_templates.template_id` |
| `stat_key` | 白字属性 key，只允许本轮 JSON 中出现的 key |
| `value_type` | 数值类型，只允许 `flat` / `percent` |
| `value` | 最终白字值 |
| `sort_order` | 排序值 |
| `is_enabled` | 是否启用 |
| `remark` | 备注 |
| `created_at` / `updated_at` | 时间戳 |

## 核心规则

- 蓝装模板按“部位 × 等级档”维护，不做流派限制。
- 蓝装模板只定义两类信息：
  - 白字属性
  - 蓝词条数量范围
- 同一个模板允许多条白字属性。
- `blue_affix_count_min` 必须小于等于 `blue_affix_count_max`。
- `slot_type = bracelet` 时，`level_band` 必须大于等于 `35`。
- `slot_type = ring` 时，`level_band` 必须大于等于 `45`。
- `slot_type = talisman` 一律禁止。
- 护符不属于蓝装模板，当前没有蓝色护符。

## 导入导出

- 导入源文件固定为 `data/blue_equipment_templates_v1.json`。
- 导入严格读取 JSON 里的原字段名，不做字段名改造。
- 导出文件固定为 `blue_equipment_templates_v1.json`。
- 导出内容包含：
  - 模板主表字段
  - 模板白字明细
  - 蓝词条数量范围

## items 关联说明

- `result_item_id` 必须映射到 `items.item_id`。
- 如果导入时发现成品 item 不存在，会补齐最小可用蓝装成品 item 记录。
- 补齐的成品 item 仅用于承接蓝装成品，不代表模板本身。

## 与蓝词条模块的边界

- 蓝装模板模块只定义白字属性与蓝词条数量范围。
- 蓝词条具体效果、数值区间和适用部位由蓝词条模块维护。
- 本轮蓝装模板模块不内嵌 `blue_affixes` / `blue_affix_slot_rules` 定义，不负责蓝词条抽取、生成、洗练或掉落执行。
