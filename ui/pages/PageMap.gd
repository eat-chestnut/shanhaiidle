extends Control

const PAGE_BATTLE := "res://ui/pages/PageBattle.tscn"
const PAGE_CHARACTER := "res://ui/pages/PageCharacter.tscn"
const PAGE_SKILLS := "res://ui/pages/PageSkills.tscn"
const PAGE_DEX := "res://ui/pages/PageMonsterDex.tscn"
const STAGE_ICON_PATH := "res://assets/icons/stage_node_placeholder.png"

@onready var _title: Label = $RootVBox/Header/HeaderRow/Title
@onready var _current_stage: Label = $RootVBox/Header/HeaderRow/CurrentInfo/CurrentStage
@onready var _lbl_cfg_ver: Label = $RootVBox/Header/HeaderRow/CurrentInfo/LblCfgVersion
@onready var _lbl_bundle_id: Label = $RootVBox/Header/HeaderRow/CurrentInfo/LblBundleId
@onready var _btn_dex: Button = $RootVBox/Header/HeaderRow/BtnDex
@onready var _btn_update_cfg: Button = $RootVBox/Header/HeaderRow/BtnUpdateConfig
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

func _ready() -> void:
	_apply_i18n()
	_connect_signals()
	_load_stages()
	_rebuild_stage_list()
	_refresh_current_stage_text()
	_refresh_cfg_version()
	_refresh_bundle_id()
	_refresh_badges()

func _apply_i18n() -> void:
	_title.text = I18nService.t("ui.nav.map", "地图")
	_btn_dex.text = I18nService.t("ui.nav.dex", "图鉴")
	_btn_update_cfg.text = "更新配置"
	_btn_nav_character.text = I18nService.t("ui.nav.character", "人物")
	_btn_nav_skills.text = I18nService.t("ui.nav.skills", "技能")
	_btn_nav_battle.text = I18nService.t("ui.nav.battle", "战斗")
	_btn_nav_bag.text = I18nService.t("ui.nav.bag", "背包")
	_btn_nav_map.text = I18nService.t("ui.nav.map", "地图")
	_btn_nav_bag.disabled = false
	_btn_nav_map.disabled = true
	_current_stage.text = "当前地图：—"
	_lbl_cfg_ver.text = "配置：地图v0 物品v0 装备v0 怪物v0 技能v0 战斗v0"
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
	if not EventBus.inventory_updated.is_connected(_on_model_changed):
		EventBus.inventory_updated.connect(_on_model_changed)

func _load_stages() -> void:
	_stages.clear()
	var cfg: Dictionary = ConfigService.get_cfg()
	var db_any = cfg.get("stages_db", {})
	if not (db_any is Dictionary):
		return
	var stages_any = (db_any as Dictionary).get("stages", [])
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
		empty.text = "暂无关卡配置"
		empty.add_theme_font_size_override("font_size", 24)
		_stage_list.add_child(empty)
		return

	for stage in _stages:
		var stage_id := str(stage.get("id", ""))
		var stage_name := str(stage.get("name", stage_id))
		var unlock_min_level := maxi(1, int(stage.get("unlock_min_level", 1)))
		var unlocked := ProgressModel.level >= unlock_min_level
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
		name_label.vertical_alignment = VERTICAL_ALIGNMENT_CENTER
		name_label.add_theme_font_size_override("font_size", 24)
		name_label.text = stage_name
		center.add_child(name_label)

		var unlock_label := Label.new()
		unlock_label.size_flags_horizontal = Control.SIZE_EXPAND_FILL
		unlock_label.vertical_alignment = VERTICAL_ALIGNMENT_CENTER
		unlock_label.add_theme_font_size_override("font_size", 18)
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
		current_label.vertical_alignment = VERTICAL_ALIGNMENT_CENTER
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

func _on_nav_character_pressed() -> void:
	get_tree().change_scene_to_file(PAGE_CHARACTER)

func _on_nav_skills_pressed() -> void:
	get_tree().change_scene_to_file(PAGE_SKILLS)

func _on_nav_battle_pressed() -> void:
	get_tree().change_scene_to_file(PAGE_BATTLE)

func _on_nav_bag_pressed() -> void:
	get_tree().change_scene_to_file(PAGE_BATTLE)

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
			if not GrindModel.stage_id.is_empty():
				BattleService.set_stage(GrindModel.stage_id, int(BattleService.current_diff_index), true)
			refresh_list()
			_refresh_cfg_version()
			_refresh_bundle_id()

		var bundle_after := RemoteConfigService.get_active_bundle_id()
		var ver_after := _read_cfg_versions()
		var parts: Array[String] = []
		var name_map := {
			"stages": "地图",
			"items": "物品",
			"equip_templates": "装备",
			"monsters": "怪物",
			"skills_catalog": "技能",
			"battle_defaults": "战斗",
		}
		for key in ["stages", "items", "equip_templates", "monsters", "skills_catalog", "battle_defaults"]:
			var b := int(ver_before.get(key, 0))
			var a := int(ver_after.get(key, 0))
			if a != b:
				parts.append("%s v%d→v%d" % [str(name_map.get(key, key)), b, a])

		var bundle_part := ""
		if bundle_before != bundle_after:
			bundle_part = "（Bundle %s→%s）" % [bundle_before, bundle_after]
		else:
			bundle_part = "（Bundle 无变化）"

		if parts.is_empty():
			EventBus.add_log("版本变化：无变化" + bundle_part)
		else:
			EventBus.add_log("版本变化：" + "  ".join(parts) + bundle_part)
	)

func refresh_list() -> void:
	_load_stages()
	_rebuild_stage_list()
	_refresh_current_stage_text()

func _on_model_changed() -> void:
	_rebuild_stage_list()
	_refresh_current_stage_text()
	_refresh_cfg_version()
	_refresh_bundle_id()
	_refresh_badges()

func _refresh_badges() -> void:
	if _badge_char != null and _badge_char.has_method("set_dot"):
		_badge_char.call("set_dot", ProgressModel.free_attr_points > 0)
	if _badge_skill != null and _badge_skill.has_method("set_value"):
		_badge_skill.call("set_value", int(SkillModel.skill_points), false)

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
	_current_stage.text = "当前地图：%s" % stage_name

func _refresh_cfg_version() -> void:
	var versions := _read_cfg_versions()

	_lbl_cfg_ver.text = "配置：地图v%d 物品v%d 装备v%d 怪物v%d 技能v%d 战斗v%d" % [
		int(versions.get("stages", 0)),
		int(versions.get("items", 0)),
		int(versions.get("equip_templates", 0)),
		int(versions.get("monsters", 0)),
		int(versions.get("skills_catalog", 0)),
		int(versions.get("battle_defaults", 0)),
	]

func _refresh_bundle_id() -> void:
	if _lbl_bundle_id == null:
		return
	_lbl_bundle_id.text = "Bundle：" + RemoteConfigService.get_active_bundle_id()

func _read_cfg_versions() -> Dictionary:
	var versions := RemoteConfigService.get_active_versions()
	return {
		"stages": int(versions.get("stages", 0)),
		"items": int(versions.get("items", 0)),
		"equip_templates": int(versions.get("equip_templates", 0)),
		"monsters": int(versions.get("monsters", 0)),
		"skills_catalog": int(versions.get("skills_catalog", 0)),
		"battle_defaults": int(versions.get("battle_defaults", 0)),
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
