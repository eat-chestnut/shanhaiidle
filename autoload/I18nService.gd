extends Node

var _dict: Dictionary = {}

func _ready() -> void:
	_load_strings()

func _load_strings() -> void:
	_dict.clear()
	var path := "res://data/strings_zh.json"
	if not FileAccess.file_exists(path):
		return
	var text := FileAccess.get_file_as_string(path)
	var parsed: Variant = JSON.parse_string(text)
	if parsed is Dictionary:
		_dict = parsed

func t(key: String, fallback: String = "") -> String:
	if _dict.has(key):
		return str(_dict.get(key, key))
	if not fallback.is_empty():
		return fallback
	return key
