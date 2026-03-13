# 主线奖励规则 V1

## 当前口径
- 主线奖励只保留“首通奖励”
- 首通奖励按“难度”分别配置
- 不存在章节统一通关奖励
- 不存在 `clear_reward / clear_rewards`
- 反复通关收益由 Boss 掉落承担

## 结构
正式主线奖励使用表：
- `stage_difficulty_first_clear_rewards`

字段：
- `difficulty_id`
- `item_id`
- `count`
- `sort_order`
- `is_enabled`
- `remark`

## 不再使用的旧口径
- `first_clear_reward_group_id`
- `drop_preview_group_id`
- chapter 级通关奖励
- 任何 group_id 间接奖励挂载

## 后台录入原则
1. 打开具体主线难度
2. 在“首通奖励”区块直接新增奖励条目
3. 每条奖励只录 `item_id + count`
4. 多条奖励按 `sort_order` 排序

## 客户端读取原则
- 首通奖励直接读取难度下的 `first_clear_rewards`
- 不再按章节兜底
- 不再按 group_id 二次解析
