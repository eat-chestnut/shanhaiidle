# 主线关卡模块 V1

## 模块定位
- 只负责南山一经的主线章节、节点挂载、难度挂载、解锁展示关系
- 不负责怪物详细数值
- 不负责掉落条目明细
- 不负责 Boss 技能

## 数据文件
- 文件名：`main_stage_module_v1.json`
- 顶层 key：`main_stage_module`
- 当前只收口 `prologue_01`、`stage_01 ~ stage_08`、`epilogue_01`

## 主线章节表 `main_stage_chapters`
字段：
- `chapter_id`：业务主键
- `chapter_name`：章节名称
- `chapter_type`：`prologue / main / epilogue`
- `chapter_flow_type`：`story_intro / combat / story_outro`
- `is_functional_chapter`：是否功能章
- `has_combat`：是否含战斗
- `has_sect_selection`：是否含宗门选择
- `has_shanshen_ritual`：是否含山神祭祀
- `suggested_level_min` / `suggested_level_max`
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
- `remark`
- `sort_order`
- `is_enabled`

只给功能章使用的字段：
- `has_sect_selection`
- `has_shanshen_ritual`
- `sect_selection_enabled`
- `sect_selection_pool_id`
- `shanshen_ritual_enabled`
- `next_version_teaser_title`
- `next_world_key`
- `teaser_desc`

只给战斗章使用的字段：
- `suggested_level_min`
- `suggested_level_max`
- `suggested_power`
- `mountain_name`
- `boss_display_name`

## 主线难度表 `main_stage_difficulties`
字段：
- `difficulty_id`
- `chapter_id`
- `difficulty_code`：固定 `difficulty_1 / difficulty_2 / difficulty_3`
- `difficulty_name`：后台可配显示名
- `normal_monster_pool_id`
- `elite_monster_pool_id`
- `boss_id`
- `drop_preview_group_id`
- `first_clear_reward_group_id`
- `remark`
- `sort_order`
- `is_enabled`

说明：
- 难度表只做挂载，不存战斗明细
- `normal_monster_pool_id / elite_monster_pool_id / boss_id` 是主线模块对怪物模块的挂载点
- `drop_preview_group_id / first_clear_reward_group_id` 是主线模块对奖励模块的挂载点

## 后台模块
- `主线章节`：维护 10 章章节主表
- `主线难度`：维护 8 个战斗章节的 24 条难度挂载

## 客户端读取建议
客户端后续读取时优先使用：
- 章节展示：`chapter_id / chapter_name / chapter_type / chapter_flow_type / suggested_level_* / suggested_power / mountain_name / boss_display_name`
- 解锁推进：`unlock_level / unlock_prev_chapter_id`
- 功能章行为：`sect_selection_enabled / shanshen_ritual_enabled / next_version_teaser_title / next_world_key`
- 战斗挂载：读取战斗章下的 `difficulties`

## 当前范围
- 只覆盖南山一经
- 不创建额外世界
- 不扩展分支主线
