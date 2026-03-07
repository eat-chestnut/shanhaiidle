extends Node

const SAVE_PATH := "user://grind.json"

var stage_id: String = "nan_01"

func _ready() -> void:
	load_data()

func load_data() -> void:
	stage_id = "nan_01"
	if not FileAccess.file_exists(SAVE_PATH):
		save_data()
		return
	var text := FileAccess.get_file_as_string(SAVE_PATH)
	var parsed: Variant = JSON.parse_string(text)
	if not (parsed is Dictionary):
		save_data()
		return
	stage_id = str((parsed as Dictionary).get("stage_id", "nan_01"))
	if stage_id.is_empty():
		stage_id = "nan_01"

func save_data() -> void:
	var f := FileAccess.open(SAVE_PATH, FileAccess.WRITE)
	if f == null:
		return
	f.store_string(JSON.stringify({"stage_id": stage_id}))

func set_stage(id: String) -> void:
	if id.is_empty():
		return
	if stage_id == id:
		return
	stage_id = id
	save_data()
	EventBus.notify_inventory_updated()
