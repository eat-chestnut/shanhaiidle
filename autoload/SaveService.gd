extends Node

const SAVE_PATH := "user://save.json"

var _loaded := false
var _data: Dictionary = {}
var _dirty := false
var _save_at: float = -1.0

func _ready() -> void:
	ensure_loaded()
	set_process(true)

func ensure_loaded() -> void:
	if _loaded:
		return
	_loaded = true
	if not FileAccess.file_exists(SAVE_PATH):
		return
	var f := FileAccess.open(SAVE_PATH, FileAccess.READ)
	if f == null:
		return
	var parsed: Variant = JSON.parse_string(f.get_as_text())
	if parsed is Dictionary:
		_data = parsed

func get_section(key: String) -> Dictionary:
	ensure_loaded()
	var v: Variant = _data.get(key, {})
	return v if v is Dictionary else {}

func set_section(key: String, value: Dictionary) -> void:
	ensure_loaded()
	_data[key] = value
	request_save()

func request_save(delay_sec: float = 0.5) -> void:
	_dirty = true
	var t := Time.get_ticks_msec() / 1000.0
	var target := t + delay_sec
	if _save_at < 0.0 or target < _save_at:
		_save_at = target

func flush() -> void:
	if not _dirty:
		return
	ensure_loaded()
	var f := FileAccess.open(SAVE_PATH, FileAccess.WRITE)
	if f == null:
		return
	f.store_string(JSON.stringify(_data))
	_dirty = false
	_save_at = -1.0

func _process(_delta: float) -> void:
	if not _dirty:
		return
	var t := Time.get_ticks_msec() / 1000.0
	if _save_at > 0.0 and t >= _save_at:
		flush()

func _notification(what: int) -> void:
	if what == NOTIFICATION_WM_CLOSE_REQUEST \
	or what == NOTIFICATION_APPLICATION_PAUSED \
	or what == NOTIFICATION_APPLICATION_FOCUS_OUT:
		flush()
