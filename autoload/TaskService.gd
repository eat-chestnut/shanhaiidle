extends Node

const SAVE_PATH := "user://tasks.json"

const DAILY_DEFS := [
	{
		"id": "d_kill_100",
		"name": "每日击杀100",
		"target": 100,
		"reward": {"skill_points": 1},
	},
	{
		"id": "d_kill_300",
		"name": "每日击杀300",
		"target": 300,
		"reward": {"skill_points": 2},
	},
	{
		"id": "d_loot_30",
		"name": "每日获得30次掉落",
		"target": 30,
		"reward": {"skill_points": 1},
	},
]

const ACH_DEFS := [
	{
		"id": "a_total_kill_100",
		"name": "累计击杀100",
		"target": 100,
		"reward": {"skill_points": 1, "gold": 50},
	},
	{
		"id": "a_level_10",
		"name": "达到Lv10",
		"target": 10,
		"reward": {"skill_points": 2},
	},
	{
		"id": "a_first_class_switch",
		"name": "首次切换宗门",
		"target": 1,
		"reward": {"sect_token": 1},
	},
]

var daily_date: String = ""
var daily: Dictionary = {}
var ach: Dictionary = {}
var _batch_mode: bool = false
var _batch_msgs: Array[String] = []

func begin_batch() -> void:
	_batch_mode = true
	_batch_msgs.clear()

func end_batch() -> Array[String]:
	_batch_mode = false
	var out: Array[String] = _batch_msgs.duplicate()
	_batch_msgs.clear()
	return out

func _ready() -> void:
	_load_data()
	if reset_daily_if_needed():
		_save_data()

func on_kill(_kind: String = "normal", count: int = 1) -> void:
	if count <= 0:
		return
	reset_daily_if_needed()
	_add_progress(daily, "d_kill_100", count)
	_add_progress(daily, "d_kill_300", count)
	_add_progress(ach, "a_total_kill_100", count)
	_check_and_claim_all()
	_save_data()

func on_loot(count: int = 1) -> void:
	if count <= 0:
		return
	reset_daily_if_needed()
	_add_progress(daily, "d_loot_30", count)
	_check_and_claim_all()
	_save_data()

func on_level_changed(new_lv: int) -> void:
	reset_daily_if_needed()
	_set_progress_max(ach, "a_level_10", maxi(0, new_lv))
	_check_and_claim_all()
	_save_data()

func on_class_switched() -> void:
	reset_daily_if_needed()
	_add_progress(ach, "a_first_class_switch", 1)
	_check_and_claim_all()
	_save_data()

func reset_daily_if_needed() -> bool:
	var today := Time.get_date_string_from_system()
	if daily_date == today and not daily.is_empty():
		return false
	daily_date = today
	daily.clear()
	for def in DAILY_DEFS:
		var id := str((def as Dictionary).get("id", ""))
		if id.is_empty():
			continue
		daily[id] = {"progress": 0, "claimed": false}
	return true

func _check_and_claim_all() -> void:
	for def_any in DAILY_DEFS:
		if def_any is Dictionary:
			_check_single_claim(daily, def_any as Dictionary, true)
	for def_any in ACH_DEFS:
		if def_any is Dictionary:
			_check_single_claim(ach, def_any as Dictionary, false)

func _check_single_claim(container: Dictionary, def: Dictionary, is_daily: bool) -> void:
	var id := str(def.get("id", ""))
	if id.is_empty():
		return
	var entry := _ensure_entry(container, id)
	var claimed := bool(entry.get("claimed", false))
	var progress := int(entry.get("progress", 0))
	var target := maxi(1, int(def.get("target", 1)))
	if claimed or progress < target:
		return
	entry["claimed"] = true
	container[id] = entry
	_grant_reward(def)
	var title := str(def.get("name", id))
	if _batch_mode:
		_batch_msgs.append("完成：%s" % title)
	else:
		EventBus.add_log("完成：%s，已领取奖励" % title)

func _grant_reward(def: Dictionary) -> void:
	var reward_any = def.get("reward", {})
	if not (reward_any is Dictionary):
		return
	var reward: Dictionary = reward_any
	var reason := str(def.get("name", "任务奖励"))

	var sp := maxi(0, int(reward.get("skill_points", 0)))
	if sp > 0:
		SkillModel.grant_skill_points(sp, "" if _batch_mode else reason)

	var token := maxi(0, int(reward.get("sect_token", 0)))
	if token > 0:
		InventoryModel.add_item("宗门令", token, "system")
		if not _batch_mode:
			EventBus.add_log("获得宗门令+%d（%s）" % [token, reason])

	var gold := maxi(0, int(reward.get("gold", 0)))
	if gold > 0:
		PlayerModel.add_gold(gold)
		if not _batch_mode:
			EventBus.add_log("获得金币+%d（%s）" % [gold, reason])

func _ensure_entry(container: Dictionary, key: String) -> Dictionary:
	var entry_any = container.get(key, {})
	var entry: Dictionary = {}
	if entry_any is Dictionary:
		entry = (entry_any as Dictionary).duplicate(true)
	entry["progress"] = maxi(0, int(entry.get("progress", 0)))
	entry["claimed"] = bool(entry.get("claimed", false))
	container[key] = entry
	return entry

func _add_progress(container: Dictionary, key: String, delta: int) -> void:
	var entry := _ensure_entry(container, key)
	entry["progress"] = maxi(0, int(entry.get("progress", 0)) + delta)
	container[key] = entry

func _set_progress_max(container: Dictionary, key: String, value: int) -> void:
	var entry := _ensure_entry(container, key)
	entry["progress"] = maxi(int(entry.get("progress", 0)), value)
	container[key] = entry

func _load_data() -> void:
	daily_date = ""
	daily = {}
	ach = {}
	if FileAccess.file_exists(SAVE_PATH):
		var txt := FileAccess.get_file_as_string(SAVE_PATH)
		var parsed: Variant = JSON.parse_string(txt)
		if parsed is Dictionary:
			var data: Dictionary = parsed
			daily_date = str(data.get("daily_date", ""))
			var daily_any = data.get("daily", {})
			if daily_any is Dictionary:
				daily = (daily_any as Dictionary).duplicate(true)
			var ach_any = data.get("ach", {})
			if ach_any is Dictionary:
				ach = (ach_any as Dictionary).duplicate(true)

	# 补齐缺失的任务/成就条目
	for def_any in DAILY_DEFS:
		if def_any is Dictionary:
			_ensure_entry(daily, str((def_any as Dictionary).get("id", "")))
	for def_any in ACH_DEFS:
		if def_any is Dictionary:
			_ensure_entry(ach, str((def_any as Dictionary).get("id", "")))

func _save_data() -> void:
	var f := FileAccess.open(SAVE_PATH, FileAccess.WRITE)
	if f == null:
		push_warning("TaskService: failed to open save file")
		return
	f.store_string(JSON.stringify({
		"daily_date": daily_date,
		"daily": daily,
		"ach": ach,
	}))
