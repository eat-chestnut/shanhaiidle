extends Node

signal auto_seek_changed(enabled: bool)

const SETTINGS_PATH := "user://settings.json"

var auto_seek_enabled: bool = true

func _ready() -> void:
	load_settings()

func load_settings() -> void:
	auto_seek_enabled = true
	if not FileAccess.file_exists(SETTINGS_PATH):
		return

	var text := FileAccess.get_file_as_string(SETTINGS_PATH)
	var parsed = JSON.parse_string(text)
	if parsed is Dictionary:
		auto_seek_enabled = bool(parsed.get("auto_seek_enabled", true))

func save_settings() -> void:
	var file := FileAccess.open(SETTINGS_PATH, FileAccess.WRITE)
	if file == null:
		push_warning("GameSettings: failed to open settings file for write: %s" % SETTINGS_PATH)
		return

	var data := {
		"auto_seek_enabled": auto_seek_enabled,
	}
	file.store_string(JSON.stringify(data))

func set_auto_seek(v: bool, reason: String = "") -> void:
	if auto_seek_enabled == v:
		return

	auto_seek_enabled = v
	auto_seek_changed.emit(auto_seek_enabled)
	save_settings()

	if reason.is_empty():
		return

	var status := "开" if auto_seek_enabled else "关"
	EventBus.add_log("自动索敌=%s（%s）" % [status, reason])
