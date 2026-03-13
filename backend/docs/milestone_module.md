# Milestone Module

## 模块定位

`milestones` 是成长里程碑定义表，负责配置节点条件、前置依赖和奖励物品。

`player_milestones` 是玩家状态表，负责记录玩家是否达成、是否已领取，以及领取时间。

本模块首版不是成就系统，只支持固定一次性领取，不支持复杂统计条件、活动条件和重复刷新领取。

## milestones 字段说明

- `milestone_id`
  - 业务唯一 ID
- `title`
  - 内部名称
- `display_name`
  - 前端展示名称
- `condition_type`
  - 触发条件类型
  - 固定支持：
    - `player_level_reached`
    - `chapter_cleared`
- `condition_value`
  - 条件值
  - `player_level_reached` 时填写等级值，例如 `5` / `10` / `20`
  - `chapter_cleared` 时填写主线章节 ID，例如 `stage_01` / `stage_03` / `stage_08`
- `pre_milestone_id`
  - 前置节点 ID，可为空
- `reward_item_id`
  - 奖励物品 ID，统一引用 `items.item_id`
- `reward_count`
  - 奖励数量
- `icon`
  - 图标，可为空
- `summary`
  - 节点说明
- `sort_order`
  - 排序
- `is_enabled`
  - 是否启用
- `remark`
  - 备注

## player_milestones 字段说明

- `player_id`
  - 玩家业务 ID
- `milestone_id`
  - 对应 `milestones.milestone_id`
- `is_unlocked`
  - 是否达成解锁
- `is_claimed`
  - 是否已领取
- `claimed_at`
  - 领取时间，可为空

## 条件规则

### 等级达成

- `condition_type = player_level_reached`
- `condition_value` 必须是大于等于 1 的等级值

### 主线章节通关

- `condition_type = chapter_cleared`
- `condition_value` 必须是已启用的主线战斗章节 ID
- 章节条件按章节 ID 判断，不按难度层判断

## 奖励规则

- 一个里程碑只能发一个 `reward_item_id`
- `reward_item_id` 必须统一引用 `items.item_id`
- 若需要多奖励，必须先在礼包模块定义礼包 item，再由 `milestones.reward_item_id` 指向该礼包 item
- 里程碑表本身不直接挂多条奖励明细

## 前置节点规则

- `pre_milestone_id` 可为空
- 不为空时，表示当前节点需要先满足前置节点
- 不允许前置节点指向自己
- 不允许形成循环依赖

## 一次性领取规则

- 每个里程碑固定一次性领取
- 玩家状态只使用两列布尔值表达三态，不额外引入复杂状态枚举

三态说明：

- 未达成：`is_unlocked = false`，`is_claimed = false`
- 已达成未领取：`is_unlocked = true`，`is_claimed = false`
- 已领取：`is_unlocked = true`，`is_claimed = true`

## 导出规则

- 静态配置导出只包含 `milestones`
- `player_milestones` 属于玩家运行时状态，不进入静态配置 bundle
- 当前配置文件为 `progression_milestones_v1.json`，顶层业务字段为 `milestones`
