# 主线关卡模块 V1

## 模块定位
- 只负责南山一经的主线章节、主线节点、主线难度、解锁关系和展示挂载
- 不负责怪物详细数值
- 不负责 Boss 技能细节
- 不负责实际掉落条目
- 不负责序章、终章以外的额外世界

## 当前范围
- 仅收口 `prologue_01`
- `stage_01 ~ stage_08` 为 8 个正式战斗章节
- `epilogue_01` 为终章
- 共 10 章

## 数据文件
- 文件名：`main_stage_module_v1.json`
- 顶层 key：`main_stage_module`
- 导出命令：`php artisan game:export-main-stage-module`

## 一、主线章节表 `main_stage_chapters`
字段：
- `chapter_id`：业务主键
- `chapter_name`：章节名称
- `chapter_type`：`prologue / main / epilogue`
- `chapter_flow_type`：`story_intro / combat / story_outro`
- `is_functional_chapter`：是否功能章
- `has_combat`：是否为战斗章节
- `has_sect_selection`：是否挂宗门选择
- `has_shanshen_ritual`：是否挂山神祭祀
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

### 规则
- 序章和终章也放在主线章节表
- 序章、终章不创建战斗难度
- 正式主线章必须有 3 个难度

## 二、主线难度表 `main_stage_difficulties`
字段：
- `difficulty_id`
- `chapter_id`
- `difficulty_code`：固定 `difficulty_1 / difficulty_2 / difficulty_3`
- `difficulty_name`：后台可配显示名
- `drop_preview_group_id`
- `first_clear_reward_group_id`
- `sort_order`
- `is_enabled`
- `remark`

### 规则
- 难度表只维护显示名和挂载关系
- 不在这张表里存战斗数值细节
- 不在这张表里直接写怪物详细属性

## 三、主线难度怪物明细表 `stage_difficulty_monsters`
字段：
- `difficulty_id`
- `monster_id`
- `spawn_type`：`normal / elite / boss`
- `weight`
- `min_count`
- `max_count`
- `sort_order`
- `is_enabled`
- `remark`

### 规则
- 主线难度直接挂怪物列表，不做 `monster_pool`
- 每个战斗难度至少应有：
  - 1 组普通怪
  - 1 组精英怪
  - 1 条 Boss
- `monster_id` 关联怪物模块的 `monsters.monster_id`
- `spawn_type` 必须和怪物主表中的 `monster_type` 一致

## 四、功能章行为字段
当前先并入章节表维护，不再单独拆功能章行为表。

### 序章
- 使用 `sect_selection_enabled`
- 使用 `sect_selection_pool_id`

### 终章
- 使用 `shanshen_ritual_enabled`
- 使用 `next_version_teaser_title`
- 使用 `next_world_key`
- 使用 `teaser_desc`

## 五、后台资源
- `主线章节`
  - 维护 10 章章节主表
  - 可直接区分序章 / 正式主线章 / 终章
- `主线难度`
  - 维护 8 个正式主线章的 24 条难度记录
  - 每条难度可直接维护普通怪 / 精英怪 / Boss 列表

## 六、客户端建议读取范围
客户端读取主线章节时建议按用途分层：

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
- 每个难度继续读取 `stage_difficulty_monsters`

## 七、与怪物模块的关系
- 主线模块不保存怪物详细数值
- 主线难度只通过 `monster_id` 引用怪物库
- Boss 展示名是章节展示字段，不等于怪物库 Boss 全字段
- Boss 技能、多阶段、掉落挂载均在怪物模块维护
