extends Control

@onready var _log_panel = $RootVBox/LogWrap/LogVBox/LogPanel

func _ready() -> void:
	if not EventBus.log_added.is_connected(_log_panel.append_log):
		EventBus.log_added.connect(_log_panel.append_log)

	EventBus.add_log("进入战斗：刷怪点已激活（3处）")
	EventBus.add_log("提示：击杀每满5会播报一次")
