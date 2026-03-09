extends Node

const SAVE_PATH := "user://equip_dex.json"

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
		push_warning("EquipDexModel: failed to open save file")
		return
	file.store_string(JSON.stringify({
		"unlocked": unlocked,
		"rewarded": rewarded,
	}))

func is_unlocked(template_id: String) -> bool:
	return bool(unlocked.get(template_id, false))

func is_reward_claimed(template_id: String) -> bool:
	return bool(rewarded.get(template_id, false))

func can_claim(template_id: String) -> bool:
	return is_unlocked(template_id) and not is_reward_claimed(template_id)

func unlock(template_id: String, emit_update: bool = true) -> bool:
	var id := template_id.strip_edges()
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

func claim_reward(template_def: Dictionary) -> Dictionary:
	var template_id := str(template_def.get("id", "")).strip_edges()
	if template_id.is_empty():
		return {"ok": false, "reason": "invalid"}
	if not can_claim(template_id):
		return {"ok": false, "reason": "cannot_claim"}

	var gold := _resolve_reward_gold(template_def)
	if gold > 0:
		PlayerModel.add_gold(gold)
	rewarded[template_id] = true
	save_data()
	EventBus.notify_inventory_updated()
	EventBus.add_log("领取装备图鉴奖励：%s 金币+%d" % [str(template_def.get("name", template_id)), gold])
	return {"ok": true, "gold": gold}

func get_unlock_count() -> int:
	return unlocked.size()

func _resolve_reward_gold(template_def: Dictionary) -> int:
	if template_def.has("dex_gold"):
		return maxi(0, int(template_def.get("dex_gold", 0)))
	var rarity := str(template_def.get("rarity", "white")).strip_edges().to_lower()
	match rarity:
		"gold":
			return 35
		"blue":
			return 20
		_:
			return 12

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
