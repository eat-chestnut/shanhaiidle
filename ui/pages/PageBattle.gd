extends Control

const LOG_VIEW_COLLAPSED := 0
const LOG_VIEW_HALF := 1
const LOG_VIEW_EXPANDED := 2

@onready var _log_wrap: PanelContainer = $RootVBox/LogWrap
@onready var _log_panel: Control = $RootVBox/LogWrap/LogBox/LogPanel
@onready var _btn_log_toggle: Button = $RootVBox/LogWrap/LogBox/LogHeader/BtnLogToggle
@onready var _btn_auto_seek: Button = $RootVBox/BattleWrap/BattleLayer/TopRightControls/BtnAutoSeek
@onready var _btn_strategy: Button = $RootVBox/BattleWrap/BattleLayer/TopRightControls/BtnStrategy
@onready var _btn_nav_character: Button = $RootVBox/BottomMenu/BtnNavCharacter
@onready var _btn_nav_skills: Button = $RootVBox/BottomMenu/BtnNavSkills
@onready var _btn_nav_battle: Button = $RootVBox/BottomMenu/BtnNavBattle
@onready var _btn_bag: BaseButton = $RootVBox/BottomMenu/BtnNavBag
@onready var _btn_nav_dex: Button = $RootVBox/BottomMenu/BtnNavDex
@onready var _btn_nav_map: Button = $RootVBox/BottomMenu/BtnNavMap
@onready var _badge_char: Node = $RootVBox/BottomMenu/BtnNavCharacter/Badge
@onready var _badge_skill: Node = $RootVBox/BottomMenu/BtnNavSkills/Badge
@onready var _battle_canvas: Control = $RootVBox/BattleWrap/BattleLayer/BattleCanvas
@onready var _joystick: CanvasItem = $RootVBox/BattleWrap/BattleLayer/VirtualJoystick
@onready var _inventory_overlay: Node = $InventoryOverlay
@onready var _offline_popup: Control = $OfflinePopup

var _log_view_state := LOG_VIEW_HALF
var _overlay_open := false

func _ready() -> void:
	if not EventBus.log_added.is_connected(_log_panel.append_log):
		EventBus.log_added.connect(_log_panel.append_log)

	if not _btn_log_toggle.pressed.is_connected(_on_log_toggle_pressed):
		_btn_log_toggle.pressed.connect(_on_log_toggle_pressed)
	_apply_log_view_state()

	if not _btn_auto_seek.pressed.is_connected(_on_auto_seek_pressed):
		_btn_auto_seek.pressed.connect(_on_auto_seek_pressed)
	if not _btn_strategy.pressed.is_connected(_on_strategy_pressed):
		_btn_strategy.pressed.connect(_on_strategy_pressed)
	if not GameSettings.auto_seek_changed.is_connected(_on_auto_seek_changed):
		GameSettings.auto_seek_changed.connect(_on_auto_seek_changed)
	_refresh_auto_seek_button(GameSettings.auto_seek_enabled)
	_refresh_strategy_button()
	_apply_nav_i18n()

	if not _btn_bag.pressed.is_connected(_on_bag_pressed):
		_btn_bag.pressed.connect(_on_bag_pressed)
	if not _btn_nav_character.pressed.is_connected(_on_nav_character_pressed):
		_btn_nav_character.pressed.connect(_on_nav_character_pressed)
	if not _btn_nav_skills.pressed.is_connected(_on_nav_skills_pressed):
		_btn_nav_skills.pressed.connect(_on_nav_skills_pressed)
	if not _btn_nav_dex.pressed.is_connected(_on_nav_dex_pressed):
		_btn_nav_dex.pressed.connect(_on_nav_dex_pressed)
	if not _btn_nav_map.pressed.is_connected(_on_nav_map_pressed):
		_btn_nav_map.pressed.connect(_on_nav_map_pressed)
	if not EventBus.inventory_updated.is_connected(_on_badge_data_changed):
		EventBus.inventory_updated.connect(_on_badge_data_changed)
	_refresh_badges()
	_refresh_strategy_button()
	if _inventory_overlay != null:
		var opened_cb := Callable(self, "_on_inventory_opened")
		var closed_cb := Callable(self, "_on_inventory_closed")
		if _inventory_overlay.has_signal("opened") and not _inventory_overlay.is_connected("opened", opened_cb):
			_inventory_overlay.connect("opened", opened_cb)
		if _inventory_overlay.has_signal("closed") and not _inventory_overlay.is_connected("closed", closed_cb):
			_inventory_overlay.connect("closed", closed_cb)
	_overlay_open = false
	if _joystick != null:
		_joystick.visible = true

	var s := OfflineService.consume_pending_summary()
	if not s.is_empty() and _offline_popup != null and _offline_popup.has_method("open"):
		_offline_popup.call("open", s)

	var spawn_count := 1
	var cfg: Dictionary = ConfigService.get_cfg()
	var battle_any = cfg.get("battle", {})
	if battle_any is Dictionary:
		var battle_cfg: Dictionary = battle_any
		var spawn_points_any = battle_cfg.get("spawn_points", [])
		if spawn_points_any is Array:
			spawn_count = max(1, spawn_points_any.size())
	EventBus.add_log("进入战斗：刷怪点已激活（%d处）" % spawn_count)
	EventBus.add_log("提示：击杀每满5会播报一次")

func _process(_delta: float) -> void:
	if _battle_canvas == null:
		return
	BattleService.set_viewport_arena_size(_battle_canvas.size)

	var input_vec := Vector2.ZERO
	if not _overlay_open and _joystick != null and _joystick.has_method("get_vector"):
		var vec_any = _joystick.call("get_vector")
		if vec_any is Vector2:
			input_vec = vec_any
	BattleService.set_manual_input(input_vec)

	if _offline_popup != null and not _offline_popup.visible:
		var s2 := OfflineService.consume_pending_summary()
		if not s2.is_empty() and _offline_popup.has_method("open"):
			_offline_popup.call("open", s2)

func _on_auto_seek_pressed() -> void:
	GameSettings.set_auto_seek(not GameSettings.auto_seek_enabled, "按钮切换")

func _on_strategy_pressed() -> void:
	SkillModel.cycle_ai_profile()
	EventBus.add_log("%s：%s" % [
		I18nService.t("ui.strategy.changed", "切换策略"),
		SkillModel.get_ai_profile_name(),
	])
	_refresh_strategy_button()

func _on_auto_seek_changed(enabled: bool) -> void:
	_refresh_auto_seek_button(enabled)

func _refresh_auto_seek_button(enabled: bool) -> void:
	_btn_auto_seek.text = "自动索敌：开" if enabled else "自动索敌：关"
	if enabled:
		_btn_auto_seek.add_theme_color_override("font_color", Color.WHITE)
		_btn_auto_seek.add_theme_color_override("font_hover_color", Color(1.0, 1.0, 1.0, 1.0))
		_btn_auto_seek.add_theme_color_override("font_pressed_color", Color.WHITE)
		_btn_auto_seek.add_theme_color_override("font_focus_color", Color.WHITE)
	else:
		var dim := Color(0.7, 0.7, 0.7, 1.0)
		_btn_auto_seek.add_theme_color_override("font_color", dim)
		_btn_auto_seek.add_theme_color_override("font_hover_color", dim)
		_btn_auto_seek.add_theme_color_override("font_pressed_color", dim)
		_btn_auto_seek.add_theme_color_override("font_focus_color", dim)

func _refresh_strategy_button() -> void:
	if _btn_strategy == null:
		return
	_btn_strategy.text = "%s：%s" % [
		I18nService.t("ui.strategy", "策略"),
		SkillModel.get_ai_profile_name(),
	]

func _on_log_toggle_pressed() -> void:
	_log_view_state = (_log_view_state + 1) % 3
	_apply_log_view_state()

func _apply_log_view_state() -> void:
	match _log_view_state:
		LOG_VIEW_COLLAPSED:
			_log_panel.visible = false
			_log_wrap.custom_minimum_size = Vector2(0, 48)
			_log_wrap.size_flags_stretch_ratio = 0.0
			_btn_log_toggle.text = "▲"
		LOG_VIEW_HALF:
			_log_panel.visible = true
			_log_wrap.custom_minimum_size = Vector2.ZERO
			_log_wrap.size_flags_stretch_ratio = 0.7
			_btn_log_toggle.text = "▼"
		LOG_VIEW_EXPANDED:
			_log_panel.visible = true
			_log_wrap.custom_minimum_size = Vector2.ZERO
			_log_wrap.size_flags_stretch_ratio = 1.4
			_btn_log_toggle.text = "▼"
		_:
			_log_view_state = LOG_VIEW_HALF
			_apply_log_view_state()

func _on_bag_pressed() -> void:
	if _inventory_overlay != null and _inventory_overlay.has_method("open"):
		_inventory_overlay.call("open")
	_overlay_open = true
	if _joystick != null:
		_joystick.visible = false
	BattleService.set_manual_input(Vector2.ZERO)

func _on_inventory_opened() -> void:
	_overlay_open = true
	if _joystick != null:
		_joystick.visible = false
	BattleService.set_manual_input(Vector2.ZERO)

func _on_inventory_closed() -> void:
	_overlay_open = false
	if _joystick != null:
		_joystick.visible = true

func _apply_nav_i18n() -> void:
	_btn_nav_character.text = I18nService.t("ui.nav.character", "人物")
	_btn_nav_skills.text = I18nService.t("ui.nav.skills", "技能")
	_btn_nav_battle.text = I18nService.t("ui.nav.battle", "战斗")
	_btn_bag.text = I18nService.t("ui.nav.bag", "背包")
	_btn_nav_dex.text = I18nService.t("ui.nav.dex", "图鉴")
	_btn_nav_map.text = I18nService.t("ui.nav.map", "地图")
	_btn_nav_battle.disabled = true
	_btn_nav_dex.disabled = false
	_btn_nav_map.disabled = false

func _on_nav_character_pressed() -> void:
	get_tree().change_scene_to_file("res://ui/pages/PageCharacter.tscn")

func _on_nav_skills_pressed() -> void:
	get_tree().change_scene_to_file("res://ui/pages/PageSkills.tscn")

func _on_nav_dex_pressed() -> void:
	get_tree().change_scene_to_file("res://ui/pages/PageMonsterDex.tscn")

func _on_nav_map_pressed() -> void:
	get_tree().change_scene_to_file("res://ui/pages/PageMap.tscn")

func _on_badge_data_changed() -> void:
	_refresh_badges()
	_refresh_strategy_button()

func _refresh_badges() -> void:
	if _badge_char != null and _badge_char.has_method("set_dot"):
		_badge_char.call("set_dot", ProgressModel.free_attr_points > 0)
	if _badge_skill != null and _badge_skill.has_method("set_value"):
		_badge_skill.call("set_value", int(SkillModel.skill_points), false)
