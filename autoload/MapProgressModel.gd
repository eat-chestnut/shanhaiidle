extends Node

const SAVE_PATH := "user://map_progress.json"

var cleared_stage_ids: Array[String] = []
var cleared_stage_diffs: Dictionary = {}

func _ready() -> void:
	load_data()

func load_data() -> void:
	cleared_stage_ids.clear()
	cleared_stage_diffs.clear()
	if not FileAccess.file_exists(SAVE_PATH):
		save_data()
		return
	var text := FileAccess.get_file_as_string(SAVE_PATH)
	var parsed: Variant = JSON.parse_string(text)
	if not (parsed is Dictionary):
		save_data()
		return
	var data: Dictionary = parsed
	var ids_any = data.get("cleared_stage_ids", [])
	if ids_any is Array:
		for id_any in (ids_any as Array):
			var stage_id := str(id_any).strip_edges()
			if stage_id.is_empty():
				continue
			cleared_stage_ids.append(stage_id)
	var diffs_any = data.get("cleared_stage_diffs", {})
	if diffs_any is Dictionary:
		for key_any in (diffs_any as Dictionary).keys():
			var stage_id := str(key_any).strip_edges()
			if stage_id.is_empty():
				continue
			cleared_stage_diffs[stage_id] = maxi(0, int((diffs_any as Dictionary).get(key_any, 0)))

func save_data() -> void:
	var f := FileAccess.open(SAVE_PATH, FileAccess.WRITE)
	if f == null:
		return
	f.store_string(JSON.stringify({
		"cleared_stage_ids": cleared_stage_ids,
		"cleared_stage_diffs": cleared_stage_diffs,
	}))

func mark_stage_cleared(stage_id: String, diff_index: int = 0) -> bool:
	var sid := stage_id.strip_edges()
	if sid.is_empty():
		return false
	var changed := false
	if not cleared_stage_ids.has(sid):
		cleared_stage_ids.append(sid)
		changed = true
	var old_diff := maxi(0, int(cleared_stage_diffs.get(sid, -1)))
	if diff_index > old_diff:
		cleared_stage_diffs[sid] = diff_index
		changed = true
	elif old_diff < 0:
		cleared_stage_diffs[sid] = maxi(0, diff_index)
		changed = true
	if changed:
		save_data()
	return changed

func is_stage_cleared(stage_id: String) -> bool:
	var sid := stage_id.strip_edges()
	if sid.is_empty():
		return false
	return cleared_stage_ids.has(sid)

func highest_cleared_order() -> int:
	var order_map := _stage_order_map()
	var best := -1
	for stage_id in cleared_stage_ids:
		best = maxi(best, int(order_map.get(stage_id, -1)))
	return best

func highest_cleared_stage_id() -> String:
	var order_map := _stage_order_map()
	var best_id := ""
	var best_order := -1
	for stage_id in cleared_stage_ids:
		var idx := int(order_map.get(stage_id, -1))
		if idx > best_order:
			best_order = idx
			best_id = stage_id
	return best_id

func _stage_order_map() -> Dictionary:
	var out := {}
	var rows_any = ConfigService.get_stages_db().get("stages", [])
	if not (rows_any is Array):
		return out
	for i in range((rows_any as Array).size()):
		var row_any = (rows_any as Array)[i]
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		var stage_id := str(row.get("id", "")).strip_edges()
		if stage_id.is_empty():
			continue
		out[stage_id] = i
	return out
