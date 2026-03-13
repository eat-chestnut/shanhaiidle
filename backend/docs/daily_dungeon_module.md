# 日常副本模块 V1

## 模块定位

- 只负责常驻日常副本
- 首版只做正式 `daily_dungeons` 模块
- 副本采用固定 5 级的升级制
- 不做自动难度解锁
- 不做活动副本、限时副本
- 不做通关奖励、每日结算奖励、三星奖励、评分奖励
- 实际掉落仍然只由怪物模块决定

## 数据文件

- 文件名：`daily_dungeons_v1.json`
- 顶层 key：`daily_dungeons`
- 导出命令：`php artisan game:export-daily-dungeons`

## 一、主表 `daily_dungeons`

字段：

- `dungeon_id`
- `title`
- `display_name`
- `dungeon_type`
- `unlock_level`
- `entry_cost_item_id`
- `entry_cost_count`
- `daily_limit`
- `sweep_enabled`
- `icon`
- `summary`
- `sort_order`
- `is_enabled`
- `remark`

### 规则

- `dungeon_id` 为业务唯一 ID
- 首版固定 4 个副本：
  - `dun_star_sand`
  - `dun_spirit_jade`
  - `dun_spirit_mark`
  - `dun_refine_soul`
- `dungeon_type` 固定使用：
  - `star_sand`
  - `spirit_jade`
  - `spirit_mark`
  - `refine_soul`
- `daily_limit` 首版统一默认 `2`
- `entry_cost_item_id` / `entry_cost_count` 仅保留结构，首版允许为空或 `0`
- `sweep_enabled` 只是布尔开关，首版不扩展复杂扫荡规则

## 二、等级表 `daily_dungeon_levels`

字段：

- `dungeon_level_id`
- `dungeon_id`
- `level_no`
- `level_name`
- `recommended_level`
- `recommended_power`
- `is_max_level`
- `summary`
- `sort_order`
- `is_enabled`
- `remark`

### 规则

- 每个副本固定 5 条等级记录
- `level_no` 只允许 `1 ~ 5`
- `Lv5` 必须是 `is_max_level = true`
- `Lv1 ~ Lv4` 必须是 `is_max_level = false`
- `level_name` 允许后台配置
- 推荐等级 / 推荐战力只做建议值

## 三、等级怪物明细表 `daily_dungeon_level_monsters`

字段：

- `dungeon_level_id`
- `monster_id`
- `spawn_type`
- `weight`
- `min_count`
- `max_count`
- `sort_order`
- `is_enabled`
- `remark`

### 规则

- 每个副本等级直接维护怪物列表
- 不做 `monster_pool`
- `spawn_type` 固定使用：
  - `normal`
  - `elite`
  - `boss`
- `spawn_type` 必须和 `monsters.monster_type` 一致
- 副本掉落不挂在副本上，仍由 `monsters -> monster_drop_items` 决定

## 四、升级消耗表 `daily_dungeon_upgrade_costs`

字段：

- `dungeon_level_id`
- `target_level_no`
- `item_id`
- `count`
- `sort_order`
- `is_enabled`
- `remark`

### 规则

- `dungeon_level_id` 表示当前等级
- `target_level_no` 表示升级目标等级
- 升级消耗严格按“当前等级 -> 下一等级”配置
- `Lv1` 配升到 `Lv2`
- `Lv2` 配升到 `Lv3`
- `Lv3` 配升到 `Lv4`
- `Lv4` 配升到 `Lv5`
- `Lv5` 不再配置升级消耗

## 五、首通奖励表 `daily_dungeon_first_clear_rewards`

字段：

- `dungeon_level_id`
- `item_id`
- `count`
- `sort_order`
- `is_enabled`
- `remark`

### 规则

- 首通奖励挂在副本等级层
- 一个等级允许配置多条 `item` 奖励
- 不做 `clear_reward`
- 不做 `daily_reward`
- 不做评分 / 星级额外奖励

## 六、升级制规则

- 日常副本不是通关后自动解锁下一难度
- 玩家通过消耗 `daily_dungeon_upgrade_costs` 中的材料提升副本等级
- 副本等级提升后，只通过更强的怪物列表和怪物掉落表现体现收益提升
- 正式首版固定每个副本 5 级

## 七、掉落规则

- 副本不直接挂掉落条目
- 副本等级也不挂掉落条目
- 日常副本实际掉落继续读取怪物模块中的 `monster_drop_items`
- 因此日常副本只负责：
  - 挂怪物
  - 挂升级消耗
  - 挂首通奖励

## 八、进入消耗与扫荡字段

### 进入消耗

- `entry_cost_item_id` 统一引用 `items.item_id`
- `entry_cost_count` 为进入消耗数量
- 首版可不填，表示当前版本先不强制扣进入材料

### 扫荡

- `sweep_enabled` 为布尔开关
- 首版只控制能否扫荡
- 不扩展 VIP、三星、首通等附加扫荡条件

## 九、后台资源

- `日常副本`
  - 维护主表基础信息和开放规则
- `日常副本等级`
  - 维护每个副本固定 5 个等级
  - 直接维护普通怪 / 精英怪 / Boss 列表
  - 直接维护升级消耗
  - 直接维护首通奖励

## 十、导出范围

- `daily_dungeons_v1.json` 纳入配置 bundle
- 只导出静态配置
- 不导出任何玩家进度状态
