# 蓝词条模块

## 模块定位

- `blue_affixes` 是蓝词条主表，蓝词条本体不是 `item`。
- 蓝词条按“单条记录 + level_band”拆分定义。
- 蓝词条适用部位通过独立表 `blue_affix_slot_rules` 维护，不使用 JSON 字段混塞。
- 首版只做定义层，不处理提取、生成、洗练、重铸、词条池嵌套和掉落逻辑。
- 护符不参与蓝词条体系。

## 首版 9 类词条

- 攻击：`bonus_melee_atk`
- 生命：`bonus_hp`
- 防御：`bonus_def`
- 暴击：`bonus_crit_rate`
- 暴伤：`bonus_crit_dmg`
- 攻速：`bonus_atk_speed`
- 技伤：`bonus_skill_dmg`
- Boss增伤：`bonus_boss_dmg`
- 吸血：`bonus_lifesteal`

## blue_affixes 字段说明

| 字段 | 说明 |
| --- | --- |
| `id` | 自增主键 |
| `affix_id` | 业务唯一 ID |
| `affix_name` | 内部名称 |
| `display_name` | 前端展示名称 |
| `effect_key` | 效果 key，仅允许本轮 JSON 中定义的 9 类 key |
| `value_type` | 数值类型，只允许 `flat` / `percent` |
| `value_min` | 最小值 |
| `value_max` | 最大值 |
| `weight` | 出现权重，必须大于 0 |
| `level_band` | 等级档，本轮只允许 `20` / `35` / `45` |
| `quality` | 本轮固定为 `blue` |
| `rarity` | 本轮固定为 `blue` |
| `summary` | 摘要说明 |
| `sort_order` | 排序值 |
| `is_enabled` | 是否启用 |
| `remark` | 备注 |
| `created_at` / `updated_at` | 时间戳 |

## blue_affix_slot_rules 字段说明

| 字段 | 说明 |
| --- | --- |
| `id` | 自增主键 |
| `affix_id` | 关联 `blue_affixes.affix_id` |
| `slot_type` | 可用部位，只允许 `main_weapon` / `sub_weapon` / `armor` / `leg` / `shoe` / `cloak` / `helmet` / `necklace` / `bracelet` / `ring` |
| `sort_order` | 排序值 |
| `is_enabled` | 是否启用 |
| `remark` | 备注 |
| `created_at` / `updated_at` | 时间戳 |

## 规则说明

- 同一个蓝词条可配置多个适用部位，但必须显式逐条写入 `blue_affix_slot_rules`。
- 同一 `affix_id + slot_type` 组合不允许重复。
- `slot_type = talisman` 一律禁止。
- `value_min` 必须小于等于 `value_max`。
- `weight` 必须大于 `0`。
- 不做流派限制，不做职业硬限制。

## 数值范围与权重

- `value_min / value_max` 描述该蓝词条在当前等级档下的可取值范围。
- `weight` 仅表示定义层中的出现权重，本轮不实现实际抽取执行。

## 导入导出

- 项目源文件：`data/blue_affixes_v1.json`
- 导入严格使用该 JSON 的原始字段结构，不改字段名。
- 导出文件：`blue_affixes_v1.json`
- 导出内容包含：
  - 蓝词条主表字段
  - 部位规则明细

## 与蓝装模板模块的边界

- 蓝词条模块负责定义“蓝词条是什么、数值范围是多少、哪些部位能用”。
- 蓝装模板模块负责定义“某个蓝装模板允许几条蓝词条”。
- 本轮蓝装模板模块不会内嵌蓝词条定义，本模块也不会处理蓝装生成执行。
