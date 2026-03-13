# 主线关卡模块 V1

## 模块定位
- 只负责南山一经的主线章节、主线节点、主线难度、解锁关系和展示挂载
- 不负责怪物详细数值
- 不负责 Boss 技能细节
- 不负责实际怪物掉落条目
- 不负责序章、终章以外的额外世界

## 当前范围
- `prologue_01`
- `stage_01 ~ stage_08`
- `epilogue_01`
- 共 10 章

## 数据文件
- 文件名：`main_stage_module_v1.json`
- 顶层 key：`main_stage_module`
- 导出命令：`php artisan game:export-main-stage-module`

## 一、主线章节表 `main_stage_chapters`
字段：
- `chapter_id`
- `chapter_name`
- `chapter_type`
- `chapter_flow_type`
- `is_functional_chapter`
- `has_combat`
- `has_sect_selection`
- `has_shanshen_ritual`
- `suggested_level_min`
- `suggested_level_max`
- `suggested_power`
- `mountain_name`
- `boss_display_name`
- `unlock_level`
- `unlock_prev_chapter_id`
- `sect_selection_enabled`
- `sect_selection_pool_id`
- `shanshen_ritual_enabled`
- `next_version_teaser_title`
- `next_world_key`
- `teaser_desc`
- `sort_order`
- `is_enabled`
- `remark`

### 仅功能章使用的字段
- `has_sect_selection`
- `has_shanshen_ritual`
- `sect_selection_enabled`
- `sect_selection_pool_id`
- `shanshen_ritual_enabled`
- `next_version_teaser_title`
- `next_world_key`
- `teaser_desc`

### 仅战斗章使用的字段
- `suggested_level_min`
- `suggested_level_max`
- `suggested_power`
- `mountain_name`
- `boss_display_name`

## 二、主线难度表 `main_stage_difficulties`
字段：
- `difficulty_id`
- `chapter_id`
- `difficulty_code`
- `difficulty_name`
- `sort_order`
- `is_enabled`
- `remark`

### 规则
- 难度表只维护显示名和挂载关系
- 不在这张表里存战斗数值细节
- 不在这张表里直接写怪物详细属性
- 主线奖励只保留“首通奖励”
- 不存在 `clear_reward / clear_rewards`
- 重复通关收益由 Boss 掉落承担，不再单独配置通关奖励

## 三、主线难度怪物明细表 `stage_difficulty_monsters`
字段：
- `difficulty_id`
- `monster_id`
- `spawn_type`
- `weight`
- `min_count`
- `max_count`
- `sort_order`
- `is_enabled`
- `remark`

### 规则
- 主线难度直接挂怪物列表，不做 `monster_pool`
- `spawn_type`
  - `normal`
  - `elite`
  - `boss`
- `spawn_type` 必须和怪物主表中的 `monster_type` 一致
- 序章、终章不配置怪物列表

## 四、主线难度首通奖励表 `stage_difficulty_first_clear_rewards`
字段：
- `difficulty_id`
- `item_id`
- `count`
- `sort_order`
- `is_enabled`
- `remark`

### 规则
- 首通奖励直接挂在具体难度上
- 不通过 `first_clear_reward_group_id` 间接引用
- 一个难度可以配置多条首通奖励
- 首通奖励可以直接发礼包 item，礼包本体再由 `gift_packs` / `gift_pack_items` 展开内容
- 序章、终章没有难度，自然也没有首通奖励

## 五、功能章行为字段
当前先并入章节表维护，不再单独拆功能章行为表。

### 序章
- 使用 `sect_selection_enabled`
- 使用 `sect_selection_pool_id`

### 终章
- 使用 `shanshen_ritual_enabled`
- 使用 `next_version_teaser_title`
- 使用 `next_world_key`
- 使用 `teaser_desc`

## 六、后台资源
- `主线章节`
  - 维护 10 章章节主表
  - 可直接区分序章 / 正式主线章 / 终章
- `主线难度`
  - 维护 8 个正式主线章的 24 条难度记录
  - 每条难度可直接维护：
    - 普通怪列表
    - 精英怪列表
    - Boss 列表
    - 首通奖励条目

## 七、客户端建议读取范围
### 章节展示
- `chapter_id`
- `chapter_name`
- `chapter_type`
- `chapter_flow_type`
- `suggested_level_min`
- `suggested_level_max`
- `suggested_power`
- `mountain_name`
- `boss_display_name`

### 主线推进
- `unlock_level`
- `unlock_prev_chapter_id`

### 功能章行为
- `sect_selection_enabled`
- `sect_selection_pool_id`
- `shanshen_ritual_enabled`
- `next_version_teaser_title`
- `next_world_key`
- `teaser_desc`

### 战斗挂载
- 读取正式战斗章节下的 `difficulties`
- 每个难度继续读取：
  - `monster_entries`
  - `first_clear_rewards`

## 八、与怪物模块的关系
- 主线模块不保存怪物详细数值
- 主线难度只通过 `monster_id` 引用怪物库
- Boss 展示名是章节展示字段，不等于怪物库 Boss 全字段
- Boss 技能和 Boss 掉落在怪物模块维护
