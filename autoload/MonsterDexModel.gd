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

func is_reward_claimed(monster_id: String) -> bool:
	return bool(rewarded.get(monster_id, false))

func mark_seen(monster_def: Dictionary) -> void:
	var monster_id := str(monster_def.get("id", ""))
	if monster_id.is_empty():
		return
	var was_unlocked := bool(unlocked.get(monster_id, false))
	if not was_unlocked:
		unlocked[monster_id] = true
		EventBus.add_log("图鉴解锁：%s（可领取奖励）" % str(monster_def.get("name", monster_id)))
	else:
		return

	save_data()
	EventBus.notify_inventory_updated()

func can_claim(monster_id: String) -> bool:
	return bool(unlocked.get(monster_id, false)) and not bool(rewarded.get(monster_id, false))

func claim(monster_def: Dictionary) -> bool:
	var monster_id := str(monster_def.get("id", ""))
	if monster_id.is_empty():
		return false
	if not can_claim(monster_id):
		return false
	var gold := maxi(0, int(monster_def.get("dex_gold", 5)))
	if gold > 0:
		PlayerModel.add_gold(gold)
	rewarded[monster_id] = true
	save_data()
	EventBus.add_log("领取图鉴奖励：%s 金币+%d" % [str(monster_def.get("name", monster_id)), gold])
	EventBus.notify_inventory_updated()
	return true

func get_total_entry_ids() -> Array[String]:
	var ids: Array[String] = []
	var seen: Dictionary = {}
	var cfg: Dictionary = ConfigService.get_cfg()
	var db_any = cfg.get("monsters_db", {})
	if not (db_any is Dictionary):
		db_any = {}
	var rows_any = (db_any as Dictionary).get("monsters", [])
	if rows_any is Array:
		for row_any in rows_any:
			if not (row_any is Dictionary):
				continue
			var row: Dictionary = row_any
			if row.has("is_enabled") and not bool(row.get("is_enabled", true)):
				continue
			var monster_id := str(row.get("id", "")).strip_edges()
			if monster_id.is_empty() or seen.has(monster_id):
				continue
			seen[monster_id] = true
			ids.append(monster_id)
	if not ids.is_empty():
		return ids

	# 兼容旧配置：没有 monsters_db 时回退 battle_config 三类怪定义。
	var battle_any = cfg.get("battle", {})
	if not (battle_any is Dictionary):
		return ids
	var battle_cfg: Dictionary = battle_any
	var normal_any = battle_cfg.get("monsters", [])
	if normal_any is Array and not (normal_any as Array).is_empty():
		var first_any = (normal_any as Array)[0]
		if first_any is Dictionary:
			var normal_id := str((first_any as Dictionary).get("id", "")).strip_edges()
			if not normal_id.is_empty() and not seen.has(normal_id):
				seen[normal_id] = true
				ids.append(normal_id)
	var elite_any = battle_cfg.get("elite_monster", {})
	if elite_any is Dictionary:
		var elite_id := str((elite_any as Dictionary).get("id", "")).strip_edges()
		if not elite_id.is_empty() and not seen.has(elite_id):
			seen[elite_id] = true
			ids.append(elite_id)
	var boss_any = battle_cfg.get("boss_monster", {})
	if boss_any is Dictionary:
		var boss_id := str((boss_any as Dictionary).get("id", "")).strip_edges()
		if not boss_id.is_empty() and not seen.has(boss_id):
			ids.append(boss_id)
	return ids

func get_unlock_count() -> int:
	var count := 0
	for monster_id in get_total_entry_ids():
		if is_unlocked(monster_id):
			count += 1
	return count

func get_reward_claimed_count() -> int:
	var count := 0
	for monster_id in get_total_entry_ids():
		if is_reward_claimed(monster_id):
			count += 1
	return count

func get_reward_pending_count() -> int:
	var count := 0
	for monster_id in get_total_entry_ids():
		if can_claim(monster_id):
			count += 1
	return count

func has_pending_rewards() -> bool:
	return get_reward_pending_count() > 0
