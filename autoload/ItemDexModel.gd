extends Node

const SAVE_PATH := "user://item_dex.json"

var unlocked: Dictionary = {}
var rewarded: Dictionary = {}

func _ready() -> void:
	load_data()

func load_data() -> void:
	unlocked.clear()
	rewarded.clear()
	if not FileAccess.file_exists(SAVE_PATH):
		return
	var text := FileAccess.get_file_as_string(SAVE_PATH)
	var parsed: Variant = JSON.parse_string(text)
	if not (parsed is Dictionary):
		return
	var data: Dictionary = parsed
	_load_bool_dict(unlocked, data.get("unlocked", {}))
	_load_bool_dict(rewarded, data.get("rewarded", {}))

func save_data() -> void:
	var file := FileAccess.open(SAVE_PATH, FileAccess.WRITE)
	if file == null:
		push_warning("ItemDexModel: failed to open save file")
		return
	file.store_string(JSON.stringify({
		"unlocked": unlocked,
		"rewarded": rewarded,
	}))

func is_unlocked(item_id: String) -> bool:
	return bool(unlocked.get(item_id, false))

func is_reward_claimed(item_id: String) -> bool:
	return bool(rewarded.get(item_id, false))

func can_claim(item_id: String) -> bool:
	return is_unlocked(item_id) and not is_reward_claimed(item_id)

func unlock(item_id: String, emit_update: bool = true) -> bool:
	var id := item_id.strip_edges()
	if id.is_empty():
		return false
	if bool(unlocked.get(id, false)):
		return false
	unlocked[id] = true
	save_data()
	if emit_update:
		EventBus.notify_inventory_updated()
	return true

func unlock_many(ids: Array[String], emit_update: bool = true) -> int:
	var changed := 0
	for id in ids:
		var key := id.strip_edges()
		if key.is_empty():
			continue
		if bool(unlocked.get(key, false)):
			continue
		unlocked[key] = true
		changed += 1
	if changed <= 0:
		return 0
	save_data()
	if emit_update:
		EventBus.notify_inventory_updated()
	return changed

func claim_reward(item_def: Dictionary) -> Dictionary:
	var item_id := str(item_def.get("id", "")).strip_edges()
	if item_id.is_empty():
		return {"ok": false, "reason": "invalid"}
	if not can_claim(item_id):
		return {"ok": false, "reason": "cannot_claim"}

	var gold := _resolve_reward_gold(item_def)
	if gold > 0:
		PlayerModel.add_gold(gold)
	rewarded[item_id] = true
	save_data()
	EventBus.notify_inventory_updated()
	EventBus.add_log("领取材料图鉴奖励：%s 金币+%d" % [str(item_def.get("name", item_id)), gold])
	return {"ok": true, "gold": gold}

func get_unlock_count() -> int:
	var count := 0
	for item_id in get_total_entry_ids():
		if is_unlocked(item_id):
			count += 1
	return count

func get_total_entry_ids() -> Array[String]:
	var ids: Array[String] = []
	var cfg: Dictionary = ConfigService.get_cfg()
	var items_db_any = cfg.get("items_db", {})
	if not (items_db_any is Dictionary):
		return ids
	var rows_any = (items_db_any as Dictionary).get("items", [])
	if not (rows_any is Array):
		return ids
	for row_any in rows_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		var item_id := str(row.get("id", "")).strip_edges()
		if item_id.is_empty():
			continue
		var item_type := str(row.get("type", "item")).strip_edges().to_lower()
		if item_type == "equip":
			continue
		ids.append(item_id)
	return ids

func get_reward_claimed_count() -> int:
	var count := 0
	for item_id in get_total_entry_ids():
		if is_reward_claimed(item_id):
			count += 1
	return count

func get_reward_pending_count() -> int:
	var count := 0
	for item_id in get_total_entry_ids():
		if can_claim(item_id):
			count += 1
	return count

func has_pending_rewards() -> bool:
	return get_reward_pending_count() > 0

func _resolve_reward_gold(item_def: Dictionary) -> int:
	if item_def.has("dex_gold"):
		return maxi(0, int(item_def.get("dex_gold", 0)))
	var rarity := str(item_def.get("rarity", "white")).strip_edges().to_lower()
	match rarity:
		"gold":
			return 12
		"blue":
			return 8
		_:
			return 5

func _load_bool_dict(target: Dictionary, raw_any: Variant) -> void:
	if not (raw_any is Dictionary):
		return
	var raw: Dictionary = raw_any
	for key_any in raw.keys():
		var key := str(key_any).strip_edges()
		if key.is_empty():
			continue
		if bool(raw.get(key_any, false)):
			target[key] = true
