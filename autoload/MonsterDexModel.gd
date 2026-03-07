extends Node

const SAVE_PATH := "user://monster_dex.json"

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
	var unlocked_any = data.get("unlocked", {})
	if unlocked_any is Dictionary:
		for key_any in (unlocked_any as Dictionary).keys():
			var id := str(key_any)
			if id.is_empty():
				continue
			if bool((unlocked_any as Dictionary).get(key_any, false)):
				unlocked[id] = true
	var rewarded_any = data.get("rewarded", {})
	if rewarded_any is Dictionary:
		for key_any in (rewarded_any as Dictionary).keys():
			var id := str(key_any)
			if id.is_empty():
				continue
			if bool((rewarded_any as Dictionary).get(key_any, false)):
				rewarded[id] = true

func save_data() -> void:
	var file := FileAccess.open(SAVE_PATH, FileAccess.WRITE)
	if file == null:
		push_warning("MonsterDexModel: failed to open save file")
		return
	file.store_string(JSON.stringify({
		"unlocked": unlocked,
		"rewarded": rewarded,
	}))

func is_unlocked(id: String) -> bool:
	return bool(unlocked.get(id, false))

func mark_seen(monster_def: Dictionary) -> void:
	var monster_id := str(monster_def.get("id", ""))
	if monster_id.is_empty():
		return
	var reward_gold := maxi(0, int(monster_def.get("dex_gold", 5)))
	var was_unlocked := bool(unlocked.get(monster_id, false))
	if not was_unlocked:
		unlocked[monster_id] = true
	if not bool(rewarded.get(monster_id, false)):
		if reward_gold > 0:
			PlayerModel.add_gold(reward_gold)
		rewarded[monster_id] = true
		EventBus.add_log("图鉴解锁：%s 金币+%d" % [str(monster_def.get("name", monster_id)), reward_gold])
	elif was_unlocked:
		return

	save_data()
	EventBus.notify_inventory_updated()
