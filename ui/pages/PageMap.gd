extends Control

const PAGE_BATTLE := "res://ui/pages/PageBattle.tscn"
const PAGE_CHARACTER := "res://ui/pages/PageCharacter.tscn"
const PAGE_SKILLS := "res://ui/pages/PageSkills.tscn"
const PAGE_DEX := "res://ui/pages/PageDexHome.tscn"
const PAGE_DUNGEON := "res://ui/pages/PageDungeon.tscn"
const PAGE_WORKSHOP := "res://ui/pages/PageWorkshop.tscn"
const PAGE_SECT_TASKS := "res://ui/pages/PageSectTasks.tscn"
const PAGE_MOUNTAIN_GOD := "res://ui/pages/PageMountainGod.tscn"
const PAGE_GROWTH_MILESTONES := "res://ui/pages/PageGrowthMilestones.tscn"
const PAGE_SHOP := "res://ui/pages/PageShop.tscn"
const STAGE_ICON_PATH := "res://assets/icons/stage_node_placeholder.png"

@onready var _root_vbox: VBoxContainer = $RootVBox
@onready var _current_info: VBoxContainer = $RootVBox/Header/HeaderRow/CurrentInfo
@onready var _title: Label = $RootVBox/Header/HeaderRow/Title
@onready var _current_stage: Label = $RootVBox/Header/HeaderRow/CurrentInfo/CurrentStage
@onready var _lbl_cfg_ver: Label = $RootVBox/Header/HeaderRow/CurrentInfo/LblCfgVersion
@onready var _lbl_bundle_id: Label = $RootVBox/Header/HeaderRow/CurrentInfo/LblBundleId
@onready var _btn_dex: Button = $RootVBox/Header/HeaderRow/BtnDex
@onready var _btn_update_cfg: Button = $RootVBox/Header/HeaderRow/BtnUpdateConfig
@onready var _badge_dex_entry: Node = $RootVBox/Header/HeaderRow/BtnDex/Badge
@onready var _stage_scroll: ScrollContainer = $RootVBox/StageScroll
@onready var _stage_list: VBoxContainer = $RootVBox/StageScroll/StageList
@onready var _diff_popup: Control = $StageDifficultyPopup
@onready var _btn_nav_character: Button = $RootVBox/BottomNav/BtnNavCharacter
@onready var _btn_nav_skills: Button = $RootVBox/BottomNav/BtnNavSkills
@onready var _btn_nav_battle: Button = $RootVBox/BottomNav/BtnNavBattle
@onready var _btn_nav_bag: Button = $RootVBox/BottomNav/BtnNavBag
@onready var _btn_nav_map: Button = $RootVBox/BottomNav/BtnNavMap
@onready var _badge_char: Node = $RootVBox/BottomNav/BtnNavCharacter/Badge
@onready var _badge_skill: Node = $RootVBox/BottomNav/BtnNavSkills/Badge

var _stages: Array[Dictionary] = []
var _lbl_resources: Label
var _lbl_resources_2: Label
var _lbl_patrol_summary: Label
var _lbl_patrol_preview: Label
var _lbl_progression_summary: Label
var _lbl_progression_next: Label
var _lbl_task_summary: Label
var _lbl_god_summary: Label
var _btn_patrol_claim: Button
var _patrol_refresh_accum := 0.0

func _ready() -> void:
	_build_dynamic_sections()
	_apply_i18n()
	_connect_signals()
	_load_stages()
	_rebuild_stage_list()
	_refresh_all()
	_consume_pending_target()
	TaskService.on_visit_sect()

func _build_dynamic_sections() -> void:
	if _current_info.get_node_or_null("LblResources") == null:
		_lbl_resources = Label.new()
		_lbl_resources.name = "LblResources"
		_lbl_resources.horizontal_alignment = HORIZONTAL_ALIGNMENT_RIGHT
		_lbl_resources.add_theme_font_size_override("font_size", 14)
		_lbl_resources.modulate = Color(0.86, 0.86, 0.9, 1.0)
		_current_info.add_child(_lbl_resources)
	else:
		_lbl_resources = _current_info.get_node("LblResources") as Label

	if _current_info.get_node_or_null("LblResources2") == null:
		_lbl_resources_2 = Label.new()
		_lbl_resources_2.name = "LblResources2"
		_lbl_resources_2.horizontal_alignment = HORIZONTAL_ALIGNMENT_RIGHT
		_lbl_resources_2.add_theme_font_size_override("font_size", 14)
		_lbl_resources_2.modulate = Color(0.82, 0.82, 0.86, 1.0)
		_current_info.add_child(_lbl_resources_2)
	else:
		_lbl_resources_2 = _current_info.get_node("LblResources2") as Label

	if _root_vbox.get_node_or_null("HubPanel") == null:
		var hub_panel := PanelContainer.new()
		hub_panel.name = "HubPanel"
		var hub_vbox := VBoxContainer.new()
		hub_vbox.add_theme_constant_override("separation", 8)
		hub_panel.add_child(hub_vbox)

		var hub_title := Label.new()
		hub_title.text = "宗门入口"
		hub_title.add_theme_font_size_override("font_size", 24)
		hub_vbox.add_child(hub_title)

		var primary_title := Label.new()
		primary_title.text = "核心入口"
		primary_title.add_theme_font_size_override("font_size", 18)
		primary_title.modulate = Color(0.94, 0.9, 0.72, 1.0)
		hub_vbox.add_child(primary_title)

		var primary_grid := GridContainer.new()
		primary_grid.columns = 2
		primary_grid.add_theme_constant_override("h_separation", 8)
		primary_grid.add_theme_constant_override("v_separation", 8)
		hub_vbox.add_child(primary_grid)

		primary_grid.add_child(_make_hub_button("BtnMainline", "山门", _on_hub_mainline_pressed))
		primary_grid.add_child(_make_hub_button("BtnDungeon", "演武场", _on_hub_dungeon_pressed))
		primary_grid.add_child(_make_hub_button("BtnWorkshop", "工坊", _on_hub_workshop_pressed))
		primary_grid.add_child(_make_hub_button("BtnTasks", "宗务堂", _on_hub_tasks_pressed))

		var secondary_title := Label.new()
		secondary_title.text = "宗门内务"
		secondary_title.add_theme_font_size_override("font_size", 18)
		secondary_title.modulate = Color(0.84, 0.88, 0.98, 1.0)
		hub_vbox.add_child(secondary_title)

		var secondary_row := HBoxContainer.new()
		secondary_row.add_theme_constant_override("separation", 8)
		hub_vbox.add_child(secondary_row)

		var btn_god := _make_hub_button("BtnGod", "山神殿", _on_hub_god_pressed)
		var btn_shop := _make_hub_button("BtnShop", "宝库", _on_hub_shop_pressed)
		var btn_milestones := _make_hub_button("BtnMilestones", "成长里程碑", _on_hub_milestones_pressed)
		secondary_row.add_child(btn_god)
		secondary_row.add_child(btn_shop)
		secondary_row.add_child(btn_milestones)

		_root_vbox.add_child(hub_panel)
		_root_vbox.move_child(hub_panel, 1)

	if _root_vbox.get_node_or_null("SummaryPanel") == null:
		var summary_panel := PanelContainer.new()
		summary_panel.name = "SummaryPanel"
		var summary_vbox := VBoxContainer.new()
		summary_vbox.name = "SummaryVBox"
		summary_vbox.add_theme_constant_override("separation", 4)
		summary_panel.add_child(summary_vbox)

		_lbl_patrol_summary = Label.new()
		_lbl_patrol_summary.name = "LblPatrolSummary"
		_lbl_patrol_summary.add_theme_font_size_override("font_size", 18)
		summary_vbox.add_child(_lbl_patrol_summary)

		_lbl_patrol_preview = Label.new()
		_lbl_patrol_preview.name = "LblPatrolPreview"
		_lbl_patrol_preview.add_theme_font_size_override("font_size", 18)
		_lbl_patrol_preview.modulate = Color(0.90, 0.88, 0.74, 1.0)
		summary_vbox.add_child(_lbl_patrol_preview)

		_btn_patrol_claim = Button.new()
		_btn_patrol_claim.name = "BtnPatrolClaim"
		_btn_patrol_claim.custom_minimum_size = Vector2(0, 42)
		_btn_patrol_claim.text = "领取巡查收益"
		summary_vbox.add_child(_btn_patrol_claim)

		_lbl_task_summary = Label.new()
		_lbl_progression_summary = Label.new()
		_lbl_progression_summary.name = "LblProgressionSummary"
		_lbl_progression_summary.add_theme_font_size_override("font_size", 18)
		summary_vbox.add_child(_lbl_progression_summary)

		_lbl_progression_next = Label.new()
		_lbl_progression_next.name = "LblProgressionNext"
		_lbl_progression_next.add_theme_font_size_override("font_size", 18)
		_lbl_progression_next.modulate = Color(0.86, 0.9, 1.0, 1.0)
		summary_vbox.add_child(_lbl_progression_next)

		_lbl_task_summary = Label.new()
		_lbl_task_summary.name = "LblTaskSummary"
		_lbl_task_summary.add_theme_font_size_override("font_size", 18)
		summary_vbox.add_child(_lbl_task_summary)

		_lbl_god_summary = Label.new()
		_lbl_god_summary.name = "LblGodSummary"
		_lbl_god_summary.add_theme_font_size_override("font_size", 18)
		summary_vbox.add_child(_lbl_god_summary)

		_root_vbox.add_child(summary_panel)
		_root_vbox.move_child(summary_panel, 2)
	else:
		_lbl_patrol_summary = _root_vbox.get_node("SummaryPanel/SummaryVBox/LblPatrolSummary") as Label
		_lbl_patrol_preview = _root_vbox.get_node("SummaryPanel/SummaryVBox/LblPatrolPreview") as Label
		_btn_patrol_claim = _root_vbox.get_node("SummaryPanel/SummaryVBox/BtnPatrolClaim") as Button
		_lbl_progression_summary = _root_vbox.get_node("SummaryPanel/SummaryVBox/LblProgressionSummary") as Label
		_lbl_progression_next = _root_vbox.get_node("SummaryPanel/SummaryVBox/LblProgressionNext") as Label
		_lbl_task_summary = _root_vbox.get_node("SummaryPanel/SummaryVBox/LblTaskSummary") as Label
		_lbl_god_summary = _root_vbox.get_node("SummaryPanel/SummaryVBox/LblGodSummary") as Label

	if _root_vbox.get_node_or_null("StageTitle") == null:
		var stage_title := Label.new()
		stage_title.name = "StageTitle"
		stage_title.text = "山门主线"
		stage_title.add_theme_font_size_override("font_size", 24)
		_root_vbox.add_child(stage_title)
		_root_vbox.move_child(stage_title, 3)

func _make_hub_button(node_name: String, text: String, action: Callable) -> Button:
	var btn := Button.new()
	btn.name = node_name
	btn.custom_minimum_size = Vector2(0, 56)
	btn.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	btn.add_theme_font_size_override("font_size", 22)
	btn.text = text
	if not btn.pressed.is_connected(action):
		btn.pressed.connect(action)
	return btn

func _apply_i18n() -> void:
	_title.text = "宗门"
	_btn_dex.text = I18nService.t("ui.nav.dex", "图鉴")
	_btn_update_cfg.text = "更新配置"
	_btn_nav_character.text = I18nService.t("ui.nav.character", "人物")
	_btn_nav_skills.text = I18nService.t("ui.nav.skills", "技能")
	_btn_nav_battle.text = I18nService.t("ui.nav.battle", "战斗")
	_btn_nav_bag.text = "工坊"
	_btn_nav_map.text = "宗门"
	_btn_nav_bag.disabled = false
	_btn_nav_map.disabled = true
	_current_stage.text = "当前山门：—"
	_lbl_cfg_ver.text = "配置：主线v0 人物成长v0 里程碑v0 蓝装v0 副本v0 宗务v0 山神v0"
	_lbl_bundle_id.text = "Bundle：内置"

func _connect_signals() -> void:
	if not _btn_nav_character.pressed.is_connected(_on_nav_character_pressed):
		_btn_nav_character.pressed.connect(_on_nav_character_pressed)
	if not _btn_nav_skills.pressed.is_connected(_on_nav_skills_pressed):
		_btn_nav_skills.pressed.connect(_on_nav_skills_pressed)
	if not _btn_nav_battle.pressed.is_connected(_on_nav_battle_pressed):
		_btn_nav_battle.pressed.connect(_on_nav_battle_pressed)
	if not _btn_nav_bag.pressed.is_connected(_on_nav_bag_pressed):
		_btn_nav_bag.pressed.connect(_on_nav_bag_pressed)
	if not _btn_dex.pressed.is_connected(_on_nav_dex_pressed):
		_btn_dex.pressed.connect(_on_nav_dex_pressed)
	if not _btn_update_cfg.pressed.is_connected(_on_update_cfg_pressed):
		_btn_update_cfg.pressed.connect(_on_update_cfg_pressed)
	if _btn_patrol_claim != null and not _btn_patrol_claim.pressed.is_connected(_on_claim_patrol_pressed):
		_btn_patrol_claim.pressed.connect(_on_claim_patrol_pressed)
	if not EventBus.inventory_updated.is_connected(_on_model_changed):
		EventBus.inventory_updated.connect(_on_model_changed)
	if not EventBus.tasks_updated.is_connected(_on_model_changed):
		EventBus.tasks_updated.connect(_on_model_changed)
	if not EventBus.mountain_god_updated.is_connected(_on_model_changed):
		EventBus.mountain_god_updated.connect(_on_model_changed)
	if not EventBus.patrol_updated.is_connected(_on_model_changed):
		EventBus.patrol_updated.connect(_on_model_changed)

func _load_stages() -> void:
	_stages.clear()
	var stages_any = ConfigService.get_stages_db().get("stages", [])
	if not (stages_any is Array):
		return
	for stage_any in stages_any:
		if stage_any is Dictionary:
			_stages.append((stage_any as Dictionary).duplicate(true))

func _rebuild_stage_list() -> void:
	for child in _stage_list.get_children():
		_stage_list.remove_child(child)
		child.queue_free()

	if _stages.is_empty():
		var empty := Label.new()
		empty.custom_minimum_size = Vector2(0, 72)
		empty.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
		empty.vertical_alignment = VERTICAL_ALIGNMENT_CENTER
		empty.text = "暂无主线关卡配置"
		empty.add_theme_font_size_override("font_size", 24)
		_stage_list.add_child(empty)
		return

	for stage in _stages:
		var stage_id := str(stage.get("id", ""))
		var stage_name := str(stage.get("name", stage_id))
		var unlock_min_level := maxi(1, int(stage.get("unlock_min_level", 1)))
		var unlocked := ProgressModel.level >= unlock_min_level
		var cleared := MapProgressModel.is_stage_cleared(stage_id)
		var is_current := stage_id == GrindModel.stage_id

		var panel := PanelContainer.new()
		panel.add_theme_stylebox_override("panel", _build_card_style(is_current))
		panel.size_flags_horizontal = Control.SIZE_EXPAND_FILL

		var row := HBoxContainer.new()
		row.custom_minimum_size = Vector2(0, 110)
		row.size_flags_horizontal = Control.SIZE_EXPAND_FILL
		row.add_theme_constant_override("separation", 10)
		panel.add_child(row)

		var icon := TextureRect.new()
		icon.custom_minimum_size = Vector2(64, 64)
		var icon_tex: Variant = load(STAGE_ICON_PATH)
		if icon_tex is Texture2D:
			icon.texture = icon_tex
		icon.expand_mode = TextureRect.EXPAND_IGNORE_SIZE
		icon.stretch_mode = TextureRect.STRETCH_KEEP_ASPECT_CENTERED
		row.add_child(icon)

		var center := VBoxContainer.new()
		center.size_flags_horizontal = Control.SIZE_EXPAND_FILL
		center.alignment = BoxContainer.ALIGNMENT_CENTER
		center.add_theme_constant_override("separation", 4)
		row.add_child(center)

		var name_label := Label.new()
		name_label.size_flags_horizontal = Control.SIZE_EXPAND_FILL
		name_label.add_theme_font_size_override("font_size", 24)
		name_label.text = stage_name
		center.add_child(name_label)

		var unlock_label := Label.new()
		unlock_label.size_flags_horizontal = Control.SIZE_EXPAND_FILL
		unlock_label.add_theme_font_size_override("font_size", 18)
		if cleared:
			unlock_label.text = "已通关"
			unlock_label.modulate = Color(0.76, 1.0, 0.78, 1.0)
		else:
			unlock_label.text = "已解锁" if unlocked else "需Lv%d" % unlock_min_level
			unlock_label.modulate = Color(0.72, 0.72, 0.72, 1.0) if unlocked else Color(1.0, 0.78, 0.78, 1.0)
		center.add_child(unlock_label)

		var right := VBoxContainer.new()
		right.custom_minimum_size = Vector2(130, 0)
		right.alignment = BoxContainer.ALIGNMENT_CENTER
		right.add_theme_constant_override("separation", 6)
		row.add_child(right)

		var current_label := Label.new()
		current_label.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
		current_label.add_theme_font_size_override("font_size", 16)
		current_label.text = "当前" if is_current else ""
		current_label.modulate = Color(1.0, 0.9, 0.45, 1.0)
		right.add_child(current_label)

		var btn := Button.new()
		btn.custom_minimum_size = Vector2(130, 50)
		btn.text = "进入"
		btn.add_theme_font_size_override("font_size", 22)
		btn.disabled = not unlocked
		if not btn.disabled:
			btn.pressed.connect(_on_stage_pressed.bind(stage_id))
		right.add_child(btn)

		_stage_list.add_child(panel)

func _refresh_all() -> void:
	_refresh_current_stage_text()
	_refresh_cfg_version()
	_refresh_bundle_id()
	_refresh_resources()
	_refresh_patrol_summary()
	_refresh_progression_summary()
	_refresh_task_summary()
	_refresh_mountain_summary()
	_refresh_badges()

func _process(delta: float) -> void:
	_patrol_refresh_accum += delta
	if _patrol_refresh_accum < 1.0:
		return
	_patrol_refresh_accum = 0.0
	_refresh_patrol_summary()

func _refresh_resources() -> void:
	if _lbl_resources != null:
		_lbl_resources.text = "金币 %d｜灵石 %d｜贡献 %d" % [
			PlayerModel.gold,
			PlayerModel.spirit_stone,
			PlayerModel.sect_contribution,
		]
	if _lbl_resources_2 != null:
		_lbl_resources_2.text = "体力 %d/%d｜属性点 %d｜技能点 %d" % [
			PlayerModel.stamina,
			PlayerModel.stamina_cap,
			ProgressModel.free_attr_points,
			SkillModel.skill_points,
		]

func _refresh_task_summary() -> void:
	if _lbl_task_summary == null:
		return
	var summary := TaskService.get_summary()
	_lbl_task_summary.text = "宗务堂：今日可接 %d/%d，历程 %d/%d，可领取 %d" % [
		int(summary.get("daily_unlocked", 0)),
		int(summary.get("daily_total", 0)),
		int(summary.get("milestone_unlocked", 0)),
		int(summary.get("milestone_total", 0)),
		int(summary.get("claimable", 0)),
	]

func _refresh_patrol_summary() -> void:
	if _lbl_patrol_summary == null or _lbl_patrol_preview == null:
		return
	var summary := OfflineService.get_patrol_status()
	if not bool(summary.get("has_route", false)):
		_lbl_patrol_summary.text = "自动巡查：尚未建立巡查路线"
		_lbl_patrol_preview.text = str(summary.get("summary_text", "通关任一主线难度后，即可建立自动巡查路线。"))
		if _btn_patrol_claim != null:
			_btn_patrol_claim.visible = false
		return

	var route_name := str(summary.get("route_name", "未知巡查区域"))
	var accumulated_seconds := maxi(0, int(summary.get("accumulated_seconds", 0)))
	_lbl_patrol_summary.text = "%s｜巡查区域：%s｜已累计 %s" % [
		str(summary.get("status_text", "自动巡查中")),
		route_name,
		_format_patrol_duration(accumulated_seconds),
	]

	var preview_parts: Array[String] = []
	preview_parts.append("经验 %d" % maxi(0, int(summary.get("exp", 0))))
	preview_parts.append("本地图材料若干")
	if bool(summary.get("has_rare_drop", false)):
		preview_parts.append("低概率获得稀有掉落")
	_lbl_patrol_preview.text = "｜".join(preview_parts)

	if _btn_patrol_claim != null:
		_btn_patrol_claim.visible = true
		var can_claim := bool(summary.get("can_claim", false))
		_btn_patrol_claim.disabled = not can_claim
		_btn_patrol_claim.text = "领取巡查收益" if can_claim else "巡查收益未满"

func _refresh_progression_summary() -> void:
	if _lbl_progression_summary == null or _lbl_progression_next == null:
		return
	var current := ProgressModel.get_current_growth_milestone()
	if current.is_empty():
		_lbl_progression_summary.text = "成长里程碑：暂无配置"
		_lbl_progression_next.text = "请先导出 progression_milestones_v1.json"
		return
	var unlock_text := _milestone_unlock_summary(current)
	var claim_text := _milestone_claim_state_text(current)
	_lbl_progression_summary.text = "成长里程碑：Lv%d %s｜%s" % [
		int(current.get("level", 0)),
		str(current.get("title", "未命名里程碑")),
		str(current.get("summary", "")),
	]
	_lbl_progression_next.text = "%s｜%s" % [unlock_text, claim_text]

	var next := ProgressModel.get_next_growth_milestone()
	if not next.is_empty():
		_lbl_progression_next.text += "｜下一节点 Lv%d %s" % [
			int(next.get("level", 0)),
			str(next.get("title", "下一里程碑")),
		]

func _refresh_mountain_summary() -> void:
	if _lbl_god_summary == null:
		return
	var summary := MountainGodService.get_summary()
	if not bool(summary.get("is_unlocked", false)):
		_lbl_god_summary.text = "山神殿：尚未唤醒，需通关%s" % str(summary.get("unlock_stage_name", "指定主线"))
		return
	_lbl_god_summary.text = "山神殿：%s已苏醒，今日可供奉 %d 项" % [
		str(summary.get("name", "南山山神")),
		int(summary.get("can_offer_count", 0)),
	]

func _on_stage_pressed(stage_id: String) -> void:
	if stage_id.is_empty():
		return
	var stage := _find_stage(stage_id)
	if stage.is_empty():
		return
	var unlock_min_level := maxi(1, int(stage.get("unlock_min_level", 1)))
	if ProgressModel.level < unlock_min_level:
		EventBus.add_log("当前等级不足，需Lv%d" % unlock_min_level)
		return
	if _diff_popup != null and _diff_popup.has_method("open"):
		_diff_popup.call("open", stage)
		return
	BattleService.set_stage(stage_id, 0, false)
	_refresh_current_stage_text()
	get_tree().change_scene_to_file(PAGE_BATTLE)

func _on_hub_mainline_pressed() -> void:
	if _stage_scroll != null:
		_stage_scroll.scroll_vertical = 0
		EventBus.add_log("山门已开启：下方可直接选择主线巡山关卡。")

func _on_hub_dungeon_pressed() -> void:
	get_tree().change_scene_to_file(PAGE_DUNGEON)

func _on_hub_workshop_pressed() -> void:
	get_tree().change_scene_to_file(PAGE_WORKSHOP)

func _on_hub_tasks_pressed() -> void:
	get_tree().change_scene_to_file(PAGE_SECT_TASKS)

func _on_hub_god_pressed() -> void:
	get_tree().change_scene_to_file(PAGE_MOUNTAIN_GOD)

func _on_hub_shop_pressed() -> void:
	get_tree().change_scene_to_file(PAGE_SHOP)

func _on_hub_milestones_pressed() -> void:
	get_tree().change_scene_to_file(PAGE_GROWTH_MILESTONES)

func _on_claim_patrol_pressed() -> void:
	var ret := OfflineService.claim_patrol_rewards()
	if bool(ret.get("ok", false)):
		EventBus.add_log("自动巡查收益到账：%s｜经验+%d" % [
			str(ret.get("route_name", "主线巡查")),
			maxi(0, int(ret.get("exp", 0))),
		])
		_refresh_patrol_summary()
		return
	match str(ret.get("reason", "")):
		"no_route":
			EventBus.add_log("尚未建立巡查路线。")
		"too_soon":
			EventBus.add_log("巡查时长不足，暂时无法领取。")
		"empty":
			EventBus.add_log("当前巡查暂无可领取收益。")
		_:
			EventBus.add_log("当前无法领取自动巡查收益。")

func _on_nav_character_pressed() -> void:
	get_tree().change_scene_to_file(PAGE_CHARACTER)

func _on_nav_skills_pressed() -> void:
	get_tree().change_scene_to_file(PAGE_SKILLS)

func _on_nav_battle_pressed() -> void:
	get_tree().change_scene_to_file(PAGE_BATTLE)

func _on_nav_bag_pressed() -> void:
	get_tree().change_scene_to_file(PAGE_WORKSHOP)

func _on_nav_dex_pressed() -> void:
	get_tree().change_scene_to_file(PAGE_DEX)

func _on_update_cfg_pressed() -> void:
	var bundle_before := RemoteConfigService.get_active_bundle_id()
	var ver_before := _read_cfg_versions()
	_btn_update_cfg.disabled = true
	RemoteConfigService.download_bundle(func(ok: bool, msg: String) -> void:
		_btn_update_cfg.disabled = false
		EventBus.add_log(msg)
		if ok:
			ConfigService.load_all()
			DungeonDisplayService.refresh()
			SourceGuideService.rebuild_indexes()
			refresh_list()
			EventBus.notify_tasks_updated()
			EventBus.notify_mountain_god_updated()
			_refresh_all()

		var bundle_after := RemoteConfigService.get_active_bundle_id()
		var ver_after := _read_cfg_versions()
		var parts: Array[String] = []
		var name_map := {
			"stages": "主线",
			"items": "物品",
			"character_growth_rules": "人物成长",
			"progression_milestones": "里程碑",
			"blue_gear_templates": "蓝装",
			"material_dungeons": "日常副本",
			"sect_tasks": "宗务",
			"mountain_god": "山神",
		}
		for key in ["stages", "items", "character_growth_rules", "progression_milestones", "blue_gear_templates", "material_dungeons", "sect_tasks", "mountain_god"]:
			var b := int(ver_before.get(key, 0))
			var a := int(ver_after.get(key, 0))
			if a != b:
				parts.append("%s v%d→v%d" % [str(name_map.get(key, key)), b, a])

		var bundle_part := "（Bundle %s→%s）" % [bundle_before, bundle_after] if bundle_before != bundle_after else "（Bundle 无变化）"
		if parts.is_empty():
			EventBus.add_log("版本变化：无变化" + bundle_part)
		else:
			EventBus.add_log("版本变化：" + "  ".join(parts) + bundle_part)
	)

func refresh_list() -> void:
	_load_stages()
	_rebuild_stage_list()
	_refresh_all()

func _on_model_changed() -> void:
	_rebuild_stage_list()
	_refresh_all()

func _consume_pending_target() -> void:
	if not has_node("/root/MapNavTargetModel"):
		return
	var target := MapNavTargetModel.consume_target()
	if target.is_empty():
		return
	var stage_id := str(target.get("stage_id", "")).strip_edges()
	var diff_index := int(target.get("difficulty_index", -1))
	if stage_id.is_empty():
		return
	var stage := _find_stage(stage_id)
	if stage.is_empty():
		EventBus.add_log("目标关卡不存在：%s" % stage_id)
		return
	if _diff_popup != null and _diff_popup.has_method("open_with_target"):
		_diff_popup.call("open_with_target", stage, diff_index)
	elif _diff_popup != null and _diff_popup.has_method("open"):
		_diff_popup.call("open", stage)

func _refresh_badges() -> void:
	if _badge_char != null and _badge_char.has_method("set_dot"):
		_badge_char.call("set_dot", ProgressModel.free_attr_points > 0)
	if _badge_skill != null and _badge_skill.has_method("set_value"):
		_badge_skill.call("set_value", int(SkillModel.skill_points), false)
	if _badge_dex_entry != null and _badge_dex_entry.has_method("set_dot"):
		_badge_dex_entry.call("set_dot", DexHubService.has_pending_rewards())

func _refresh_current_stage_text() -> void:
	var target_id := GrindModel.stage_id
	var stage_name := target_id
	for stage_any in _stages:
		if not (stage_any is Dictionary):
			continue
		var stage: Dictionary = stage_any
		if str(stage.get("id", "")) != target_id:
			continue
		stage_name = str(stage.get("name", target_id))
		break
	_current_stage.text = "当前山门：%s" % stage_name

func _refresh_cfg_version() -> void:
	var versions := _read_cfg_versions()
	_lbl_cfg_ver.text = "配置：主线v%d 人物成长v%d 里程碑v%d 蓝装v%d 日常副本v%d 宗务v%d 山神v%d" % [
		int(versions.get("stages", 0)),
		int(versions.get("character_growth_rules", 0)),
		int(versions.get("progression_milestones", 0)),
		int(versions.get("blue_gear_templates", 0)),
		int(versions.get("material_dungeons", 0)),
		int(versions.get("sect_tasks", 0)),
		int(versions.get("mountain_god", 0)),
	]

func _refresh_bundle_id() -> void:
	_lbl_bundle_id.text = "Bundle：" + RemoteConfigService.get_active_bundle_id()

func _read_cfg_versions() -> Dictionary:
	var versions := RemoteConfigService.get_active_versions()
	return {
		"stages": int(versions.get("stages", 0)),
		"items": int(versions.get("items", 0)),
		"character_growth_rules": int(versions.get("character_growth_rules", 0)),
		"progression_milestones": int(versions.get("progression_milestones", 0)),
		"blue_gear_templates": int(versions.get("blue_gear_templates", 0)),
		"material_dungeons": int(versions.get("material_dungeons", 0)),
		"sect_tasks": int(versions.get("sect_tasks", 0)),
		"mountain_god": int(versions.get("mountain_god", 0)),
	}

func _find_stage(stage_id: String) -> Dictionary:
	for stage_any in _stages:
		if not (stage_any is Dictionary):
			continue
		var stage: Dictionary = stage_any
		if str(stage.get("id", "")) == stage_id:
			return stage
	return {}

func _build_card_style(is_current: bool) -> StyleBoxFlat:
	var sb := StyleBoxFlat.new()
	sb.bg_color = Color(0.94, 0.94, 0.96, 0.94)
	sb.border_width_left = 2
	sb.border_width_top = 2
	sb.border_width_right = 2
	sb.border_width_bottom = 2
	sb.corner_radius_top_left = 10
	sb.corner_radius_top_right = 10
	sb.corner_radius_bottom_left = 10
	sb.corner_radius_bottom_right = 10
	sb.border_color = Color(1.0, 0.9, 0.45, 1.0) if is_current else Color(0.72, 0.72, 0.78, 1.0)
	return sb

func _milestone_unlock_summary(row: Dictionary) -> String:
	var unlocks_any = row.get("unlock_contents", [])
	if not (unlocks_any is Array) or (unlocks_any as Array).is_empty():
		return "开放内容：暂无"
	var parts: Array[String] = []
	for unlock_any in unlocks_any:
		if not (unlock_any is Dictionary):
			continue
		var text := str((unlock_any as Dictionary).get("content", "")).strip_edges()
		if text.is_empty():
			continue
		parts.append(text)
		if parts.size() >= 2:
			break
	return "开放内容：" + "、".join(parts) if not parts.is_empty() else "开放内容：暂无"

func _milestone_claim_state_text(row: Dictionary) -> String:
	var level_need := int(row.get("level", 0))
	if ProgressModel.level < level_need:
		return "%d级解锁" % level_need
	if bool(row.get("claim_once", true)) and ProgressModel.has_claimed_milestone(str(row.get("milestone_key", ""))):
		return "已领取"
	return "可领取礼包"

func _format_patrol_duration(total_seconds: int) -> String:
	var seconds := maxi(0, total_seconds)
	var hh := int(seconds / 3600)
	var mm := int((seconds % 3600) / 60)
	return "%02d:%02d" % [hh, mm]
