# Gem Module

## 模块定位

`gems` 是正式宝石配置表。

- 宝石成品本体是 `item`
- `gems.item_id` 必须统一引用 `items.item_id`
- `gems` 只维护“宝石是什么、加什么、给多少、给谁用”
- 掉落、礼包、商城等模块后续都应通过 `item_id` 引用宝石成品

本模块明确不处理：

- 孔位开启
- 镶嵌
- 合成
- 分解
- 掉落写回

## gems 字段说明

- `id`
  - 数据库自增主键，仅后台存储使用
- `gem_id`
  - 宝石业务唯一 ID
- `item_id`
  - 对应宝石成品 item，必须命中 `items.item_id`
- `gem_name`
  - 内部名称
- `display_name`
  - 前端展示名称
- `gem_type`
  - 固定枚举：`attr_gem` / `skill_gem`
- `stat_key`
  - 宝石作用 key，必须使用正式程序 key
- `value_type`
  - 固定枚举：`flat` / `percent`
- `value`
  - 宝石实际数值，直接配置，不依赖公式生成
- `quality`
  - 玩法品质
- `rarity`
  - UI 展示稀有度
- `slot_group`
  - 固定枚举：`attr_only` / `skill_only`
- `unlock_level`
  - 可获得或可使用的最低 / 推荐等级
- `icon`
  - 图标路径
- `summary`
  - 宝石效果简述
- `sort_order`
  - 排序
- `is_enabled`
  - 是否启用
- `remark`
  - 备注

## attr_gem / skill_gem 区别

### attr_gem

- 用于属性孔
- `slot_group` 必须为 `attr_only`
- `stat_key` 只能从属性宝石 key 集中选择

当前首版支持的属性 key：

- `MELEE_ATK`
- `HP`
- `DEF`
- `CRIT_RATE`
- `CRIT_DMG`
- `ATK_SPEED`
- `SKILL_DMG`
- `BOSS_DMG`
- `LIFESTEAL`

### skill_gem

- 用于技能孔
- `slot_group` 必须为 `skill_only`
- `stat_key` 只能从技能宝石 key 集中选择

当前首版仅保留少量代表性 key：

- `skill_frost_focus`
- `skill_flame_focus`
- `skill_guard_focus`

## stat_key 规则

- 必须填写正式程序 key
- 不允许写任意中文文本
- `attr_gem` 与 `skill_gem` 使用不同 key 集
- 若 `item_id` 对应的 item 是 `sub_type = attr_gem`，则 `gem_type` 也必须是 `attr_gem`
- 若 `item_id` 对应的 item 是 `sub_type = skill_gem`，则 `gem_type` 也必须是 `skill_gem`

## value_type 规则

- `flat`
  - 固定值
- `percent`
  - 百分比

`value` 直接存最终数值，不在导出或运行时再做公式推导。

## quality / rarity 用途

### quality

- 偏玩法品质
- 用于玩法层级、数值层级、配置分层

### rarity

- 偏 UI 展示层级
- 用于背包、奖励预览、边框和列表 badge

当前首版两者都保留，且都支持五档：

- `white`
- `blue`
- `purple`
- `gold`
- `red`

## slot_group 用途

`slot_group` 只表达使用边界，不表达开孔规则：

- `attr_only`
  - 只能用于属性孔
- `skill_only`
  - 只能用于技能孔

它的职责是把“属性宝石 / 技能宝石”的可用孔位边界固化到配置里，后续镶嵌系统据此做校验。

## 与 items 的关系

- 宝石成品是 `item`
- `gems.item_id -> items.item_id`
- `items.main_type` 必须为 `gem`
- `items.sub_type` 必须与 `gems.gem_type` 对齐
- 掉落、礼包、商城、里程碑等外围模块都应引用 `item_id`
- 不应直接引用 `gem_id` 作为发奖或售卖对象

## 导入导出

- 项目源文件：`data/gem_catalog_v1.json`
- Seeder：`GemsSeeder`
- 导出命令：`php artisan game:export-gem-catalog`
- 导出文件：`gem_catalog_v1.json`
- 配置 bundle 已纳入 `gem_catalog_v1.json`

## 首版范围

首版只做最小可用样例：

- 至少覆盖 `attr_gem`
- 至少覆盖 `skill_gem`
- 品质样例至少覆盖 `white / blue / purple`
- 可保留少量 `gold` 样例验证结构

本轮不扩展：

- 全量宝石库
- 镶嵌逻辑
- 合成逻辑
- 孔位开启逻辑
