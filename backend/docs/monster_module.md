# 怪物模块 V1

## 模块定位
- 普通怪、精英怪、Boss 共用一张怪物主表
- Boss 特有机制通过独立扩展表维护
- 技能挂载、掉落挂载、主线难度怪物挂载全部结构化
- 不在主线模块里写怪物详细属性
- 不在怪物主表里直接写具体掉落条目

## 一、怪物主表 `monsters`
字段：
- `monster_id`：业务唯一 ID
- `monster_name`：后台名称
- `display_name`：客户端展示名
- `monster_type`：`normal / elite / boss`
- `chapter_id`：主要归属章节
- `display_stage_id`：展示归属章节，可空
- `family`：族系
- `title`：称号
- `desc`：说明
- `icon`
- `sprite`
- `prefab_key`
- `level`
- `hp`
- `atk`
- `def`
- `speed`
- `move_speed`
- `attack_range`
- `attack_interval`
- `aggro_range`
- `ai_type`
- `rarity_tag`
- `is_enabled`
- `sort_order`
- `remark`

### 必须手填的核心战斗字段
- `level`
- `hp`
- `atk`
- `def`
- `speed`
- `move_speed`
- `attack_range`
- `attack_interval`
- `aggro_range`
- `ai_type`

### 枚举
- `monster_type`
  - `normal`
  - `elite`
  - `boss`
- `ai_type`
  - `melee_chase`
  - `ranged_keep`
  - `elite_melee_skill`
  - `boss_pattern`
- `rarity_tag`
  - `common`
  - `elite`
  - `boss`
  - `story_boss`

## 二、怪物技能挂载表 `monster_skill_bindings`
字段：
- `monster_id`
- `skill_id`
- `slot_type`
- `trigger_priority`
- `phase_limit`
- `sort_order`
- `is_enabled`
- `remark`

### 规则
- 不允许在 `monsters` 主表里使用逗号字符串保存 `skill_ids`
- `slot_type`：
  - `basic_attack`
  - `active`
  - `passive`
  - `ultimate`
  - `phase_skill`
- `phase_limit` 可空，仅 Boss 多阶段时使用

## 三、怪物掉落挂载表 `monster_drop_bindings`
字段：
- `monster_id`
- `drop_group_id`
- `is_primary`
- `sort_order`
- `remark`

### 规则
- 怪物只挂掉落组，不直接在怪物主表中录掉落条目
- 一个怪物可挂多个掉落组
- `is_primary` 标识主掉落组

## 四、Boss 扩展表 `monster_boss_profiles`
仅 `monster_type = boss` 使用。

字段：
- `monster_id`
- `phase_count`
- `phase_rules`
- `summon_rules`
- `rage_rules`
- `weak_point_rules`
- `intro_text`
- `battle_bgm_id`
- `camera_rule`
- `entry_fx_key`
- `death_fx_key`
- `first_clear_reward_group_id`
- `story_flag_on_clear`
- `remark`

### 仅 Boss 使用的字段
- `phase_count`
- `phase_rules`
- `summon_rules`
- `rage_rules`
- `weak_point_rules`
- `intro_text`
- `battle_bgm_id`
- `camera_rule`
- `entry_fx_key`
- `death_fx_key`
- `first_clear_reward_group_id`
- `story_flag_on_clear`

### JSON 字段
以下字段使用结构化 JSON，并在模型中做 array cast：
- `phase_rules`
- `summon_rules`
- `rage_rules`
- `weak_point_rules`

## 五、主线难度怪物明细表 `stage_difficulty_monsters`
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
- 主线难度直接配置怪物列表，不做 monster_pool
- `spawn_type`：
  - `normal`
  - `elite`
  - `boss`
- `spawn_type` 必须和怪物主表的 `monster_type` 一致
- 序章、终章不配置怪物列表

## 六、与主线模块的关系
- `main_stage_chapters`：只负责章节展示和推进
- `main_stage_difficulties`：只负责难度级别挂载
- `stage_difficulty_monsters`：负责把怪物主表挂到具体难度
- `monster_boss_profiles`：负责 Boss 特有机制扩展

## 七、当前 V1 范围
- 南山一经 8 个战斗章节
- 每章 2 普通 + 1 精英 + 1 Boss
- Boss 多阶段机制先保留扩展结构，不强制启用多阶段
- 技能挂载、掉落挂载已结构化，但不在本模块内扩展技能总库与掉落条目系统
