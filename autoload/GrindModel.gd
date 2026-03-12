extends Node

const SAVE_PATH := "user://grind.json"

var stage_id: String = "nan_01"
var diff_index: int = 0

func _ready() -> void:
	load_data()

func load_data() -> void:
	stage_id = "nan_01"
	diff_index = 0
	if not FileAccess.file_exists(SAVE_PATH):
		save_data()
		return
	var text := FileAccess.get_file_as_string(SAVE_PATH)
	var parsed: Variant = JSON.parse_string(text)
	if not (parsed is Dictionary):
		save_data()
		return
	var data: Dictionary = parsed
	stage_id = str(data.get("stage_id", "nan_01"))
	if stage_id.is_empty():
		stage_id = "nan_01"
	diff_index = maxi(0, int(data.get("diff_index", 0)))

func save_data() -> void:
	var f := FileAccess.open(SAVE_PATH, FileAccess.WRITE)
	if f == null:
		return
	f.store_string(JSON.stringify({
		"stage_id": stage_id,
		"diff_index": diff_index,
	}))

func set_stage(id: String) -> void:
	set_stage_and_diff(id, diff_index)

func set_stage_and_diff(id: String, diff: int, emit_update: bool = true) -> void:
	if id.is_empty():
		return
	var next_diff := maxi(0, diff)
	if stage_id == id and diff_index == next_diff:
		return
	stage_id = id
	diff_index = next_diff
	save_data()
	if emit_update:
		EventBus.notify_inventory_updated()
		EventBus.request_profile_sync("stage_route_changed")
