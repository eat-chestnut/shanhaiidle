extends Control

@onready var _log_panel = $RootVBox/LogWrap/LogPanel

func _ready() -> void:
	if not EventBus.log_added.is_connected(_log_panel.append_log):
		EventBus.log_added.connect(_log_panel.append_log)

	EventBus.add_log("进入战斗：自动普攻已开启")
	EventBus.add_log("布局：上2/3战斗，下1/3日志，底部菜单80px占位")
