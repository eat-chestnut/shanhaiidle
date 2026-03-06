extends Control

const LOG_VIEW_COLLAPSED := 0
const LOG_VIEW_HALF := 1
const LOG_VIEW_EXPANDED := 2

@onready var _log_wrap: PanelContainer = $RootVBox/LogWrap
@onready var _log_panel: Control = $RootVBox/LogWrap/LogBox/LogPanel
@onready var _btn_log_toggle: Button = $RootVBox/LogWrap/LogBox/LogHeader/BtnLogToggle
@onready var _btn_auto_seek: Button = $RootVBox/BattleWrap/BattleLayer/BtnAutoSeek
@onready var _btn_bag: BaseButton = $RootVBox/BottomMenu/BagGroup/BtnBag
@onready var _battle_canvas: Node = $RootVBox/BattleWrap/BattleLayer/BattleCanvas
@onready var _inventory_overlay: Node = $InventoryOverlay

var _log_view_state := LOG_VIEW_HALF

func _ready() -> void:
	if not EventBus.log_added.is_connected(_log_panel.append_log):
		EventBus.log_added.connect(_log_panel.append_log)

	if not _btn_log_toggle.pressed.is_connected(_on_log_toggle_pressed):
		_btn_log_toggle.pressed.connect(_on_log_toggle_pressed)
	_apply_log_view_state()

	if not _btn_auto_seek.pressed.is_connected(_on_auto_seek_pressed):
		_btn_auto_seek.pressed.connect(_on_auto_seek_pressed)
	if not GameSettings.auto_seek_changed.is_connected(_on_auto_seek_changed):
		GameSettings.auto_seek_changed.connect(_on_auto_seek_changed)
	_refresh_auto_seek_button(GameSettings.auto_seek_enabled)

	if not _btn_bag.pressed.is_connected(_on_bag_pressed):
		_btn_bag.pressed.connect(_on_bag_pressed)
	if _inventory_overlay != null:
		var opened_cb := Callable(self, "_on_inventory_opened")
		var closed_cb := Callable(self, "_on_inventory_closed")
		if _inventory_overlay.has_signal("opened") and not _inventory_overlay.is_connected("opened", opened_cb):
			_inventory_overlay.connect("opened", opened_cb)
		if _inventory_overlay.has_signal("closed") and not _inventory_overlay.is_connected("closed", closed_cb):
			_inventory_overlay.connect("closed", closed_cb)
	_set_battle_paused(false)

	EventBus.add_log("进入战斗：刷怪点已激活（3处）")
	EventBus.add_log("提示：击杀每满5会播报一次")

func _on_auto_seek_pressed() -> void:
	GameSettings.set_auto_seek(not GameSettings.auto_seek_enabled, "按钮切换")

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
	_set_battle_paused(true)

func _on_inventory_opened() -> void:
	_set_battle_paused(true)

func _on_inventory_closed() -> void:
	_set_battle_paused(false)

func _set_battle_paused(paused: bool) -> void:
	if _battle_canvas == null:
		return
	_battle_canvas.process_mode = Node.PROCESS_MODE_DISABLED if paused else Node.PROCESS_MODE_INHERIT
