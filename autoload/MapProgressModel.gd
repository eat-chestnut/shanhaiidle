extends Node

const SAVE_PATH := "user://map_progress.json"

var progress: Dictionary = {}

func _ready() -> void:
	load_data()

func load_data() -> void:
	progress.clear()
	if not FileAccess.file_exists(SAVE_PATH):
		save_data()
		return
	var text := FileAccess.get_file_as_string(SAVE_PATH)
	var parsed: Variant = JSON.parse_string(text)
	if not (parsed is Dictionary):
		save_data()
		return
	var src: Dictionary = parsed
	for stage_id_any in src.keys():
		var stage_id := str(stage_id_any).strip_edges()
		if stage_id.is_empty():
			continue
		var row_any = src.get(stage_id_any, {})
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		progress[stage_id] = {
			"boss_kills": maxi(0, int(row.get("boss_kills", 0))),
			"unlocked_diff": maxi(0, int(row.get("unlocked_diff", 0))),
		}

func save_data() -> void:
	var f := FileAccess.open(SAVE_PATH, FileAccess.WRITE)
	if f == null:
		return
	f.store_string(JSON.stringify(progress))

func get_stage(stage_id: String) -> Dictionary:
	var sid := stage_id.strip_edges()
	if sid.is_empty():
		return {
			"boss_kills": 0,
			"unlocked_diff": 0,
		}
	if not progress.has(sid):
		progress[sid] = {
			"boss_kills": 0,
			"unlocked_diff": 0,
		}
		save_data()
	var row_any = progress.get(sid, {})
	if row_any is Dictionary:
		return (row_any as Dictionary).duplicate(true)
	return {
		"boss_kills": 0,
		"unlocked_diff": 0,
	}

func get_boss_kills(stage_id: String) -> int:
	return maxi(0, int(get_stage(stage_id).get("boss_kills", 0)))

func add_boss_kill(stage_id: String, n: int = 1) -> void:
	if n <= 0:
		return
	var sid := stage_id.strip_edges()
	if sid.is_empty():
		return
	var row := get_stage(sid)
	row["boss_kills"] = maxi(0, int(row.get("boss_kills", 0)) + n)
	progress[sid] = row
	save_data()
	EventBus.notify_inventory_updated()

func get_unlocked_diff(stage_id: String) -> int:
	return maxi(0, int(get_stage(stage_id).get("unlocked_diff", 0)))

func set_unlocked_diff(stage_id: String, v: int) -> void:
	var sid := stage_id.strip_edges()
	if sid.is_empty():
		return
	var row := get_stage(sid)
	row["unlocked_diff"] = maxi(0, v)
	progress[sid] = row
	save_data()
	EventBus.notify_inventory_updated()

func can_upgrade(stage_id: String, next_diff_index: int, stage_def: Dictionary) -> Dictionary:
	var out := {
		"ok": false,
		"reason": "invalid",
		"need_kills": 0,
		"have_kills": 0,
		"need_items": {},
	}
	if next_diff_index < 0:
		return out
	var diffs_any = stage_def.get("difficulties", [])
	if not (diffs_any is Array):
		out["reason"] = "no_difficulties"
		return out
	var diffs: Array = diffs_any
	if next_diff_index >= diffs.size():
		out["reason"] = "max"
		return out
	var diff_any = diffs[next_diff_index]
	if not (diff_any is Dictionary):
		out["reason"] = "invalid_diff"
		return out
	var diff_def: Dictionary = diff_any
	var unlock_any = diff_def.get("unlock", {})
	var unlock: Dictionary = unlock_any if unlock_any is Dictionary else {}
	var need_kills := maxi(0, int(unlock.get("boss_kills_required", 0)))
	var have_kills := get_boss_kills(stage_id)
	out["need_kills"] = need_kills
	out["have_kills"] = have_kills

	var material_cost_any = unlock.get("material_cost", {})
	var material_cost: Dictionary = material_cost_any if material_cost_any is Dictionary else {}
	var need_items: Dictionary = {}
	var has_items := true
	for item_id_any in material_cost.keys():
		var item_id := str(item_id_any).strip_edges()
		var need := maxi(0, int(material_cost.get(item_id_any, 0)))
		if item_id.is_empty() or need <= 0:
			continue
		need_items[item_id] = need
		if InventoryModel.get_count(item_id) < need:
			has_items = false
	out["need_items"] = need_items

	if have_kills < need_kills:
		out["reason"] = "kills"
		return out
	if not has_items:
		out["reason"] = "items"
		return out
	out["ok"] = true
	out["reason"] = ""
	return out

func upgrade(stage_id: String, stage_def: Dictionary) -> Dictionary:
	var out := {
		"ok": false,
		"reason": "invalid",
		"new_diff": 0,
	}
	var cur := get_unlocked_diff(stage_id)
	var next := cur + 1
	var diffs_any = stage_def.get("difficulties", [])
	if not (diffs_any is Array):
		out["reason"] = "no_difficulties"
		return out
	var diffs: Array = diffs_any
	if next >= diffs.size():
		out["reason"] = "max"
		return out

	var chk := can_upgrade(stage_id, next, stage_def)
	if not bool(chk.get("ok", false)):
		out["reason"] = str(chk.get("reason", "invalid"))
		return out

	var need_items_any = chk.get("need_items", {})
	var need_items: Dictionary = need_items_any if need_items_any is Dictionary else {}
	var spent: Array[Dictionary] = []
	for item_id_any in need_items.keys():
		var item_id := str(item_id_any).strip_edges()
		var need := maxi(0, int(need_items.get(item_id_any, 0)))
		if item_id.is_empty() or need <= 0:
			continue
		if not InventoryModel.consume_item(item_id, need, "system"):
			for row_any in spent:
				if not (row_any is Dictionary):
					continue
				var row: Dictionary = row_any
				var rollback_id := str(row.get("id", "")).strip_edges()
				var rollback_cnt := maxi(0, int(row.get("count", 0)))
				if rollback_id.is_empty() or rollback_cnt <= 0:
					continue
				InventoryModel.add_item(rollback_id, rollback_cnt, "system")
			out["reason"] = "items"
			return out
		spent.append({"id": item_id, "count": need})

	set_unlocked_diff(stage_id, next)
	out["ok"] = true
	out["reason"] = ""
	out["new_diff"] = next
	return out
