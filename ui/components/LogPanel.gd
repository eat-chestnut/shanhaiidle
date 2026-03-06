extends ScrollContainer

const MAX_LOGS := 200

@onready var _log_container: VBoxContainer = $VBoxContainer

func _ready() -> void:
	var event_bus = get_node_or_null("/root/EventBus")
	if event_bus and not event_bus.log_added.is_connected(_on_log_added):
		event_bus.log_added.connect(_on_log_added)

func append_log(text: String) -> void:
	var label := Label.new()
	label.text = text
	label.autowrap_mode = TextServer.AUTOWRAP_WORD_SMART
	label.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	_log_container.add_child(label)

	var excess := _log_container.get_child_count() - MAX_LOGS
	for _i in range(excess):
		var oldest := _log_container.get_child(0)
		_log_container.remove_child(oldest)
		oldest.queue_free()

	call_deferred("_scroll_to_bottom")

func _on_log_added(text: String) -> void:
	append_log(text)

func _scroll_to_bottom() -> void:
	scroll_vertical = int(get_v_scroll_bar().max_value)
