extends Control

@onready var _log_panel = $RootVBox/LogWrap/LogVBox/LogPanel
@onready var _btn_auto_seek: Button = $RootVBox/BattleWrap/BattleLayer/BtnAutoSeek

func _ready() -> void:
	if not EventBus.log_added.is_connected(_log_panel.append_log):
		EventBus.log_added.connect(_log_panel.append_log)

	if not _btn_auto_seek.pressed.is_connected(_on_auto_seek_pressed):
		_btn_auto_seek.pressed.connect(_on_auto_seek_pressed)
	if not GameSettings.auto_seek_changed.is_connected(_on_auto_seek_changed):
		GameSettings.auto_seek_changed.connect(_on_auto_seek_changed)
	_refresh_auto_seek_button(GameSettings.auto_seek_enabled)

	EventBus.add_log("进入战斗：刷怪点已激活（3处）")
	EventBus.add_log("提示：击杀每满5会播报一次")

func _on_auto_seek_pressed() -> void:
	GameSettings.set_auto_seek(not GameSettings.auto_seek_enabled, "按钮切换")

func _on_auto_seek_changed(enabled: bool) -> void:
	_refresh_auto_seek_button(enabled)

func _refresh_auto_seek_button(enabled: bool) -> void:
	_btn_auto_seek.text = "自动索敌：开" if enabled else "自动索敌：关"
