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
		progress[stage_id] = _normalize_stage_row(row_any)

func save_data() -> void:
	var f := FileAccess.open(SAVE_PATH, FileAccess.WRITE)
	if f == null:
		return
	f.store_string(JSON.stringify(progress))

func get_stage(stage_id: String) -> Dictionary:
	var sid := stage_id.strip_edges()
	if sid.is_empty():
		return _default_stage_row()
	if not progress.has(sid):
		progress[sid] = _default_stage_row()
		save_data()
	var row_any = progress.get(sid, {})
	var row: Dictionary = _normalize_stage_row(row_any if row_any is Dictionary else {})
	progress[sid] = row
	return row.duplicate(true)

func get_boss_kills(stage_id: String, diff: int = -1) -> int:
	var row := get_stage(stage_id)
	if diff < 0:
		return maxi(0, int(row.get("boss_kills", 0)))
	var kills_by_diff_any = row.get("boss_kills_by_diff", {})
	if not (kills_by_diff_any is Dictionary):
		return 0
	return maxi(0, int((kills_by_diff_any as Dictionary).get(str(maxi(0, diff)), 0)))

func add_boss_kill(stage_id: String, diff: int = 0, n: int = 1) -> void:
	if n <= 0:
		return
	var sid := stage_id.strip_edges()
	if sid.is_empty():
		return
	var d := maxi(0, diff)
	var row := get_stage(sid)
	row["boss_kills"] = maxi(0, int(row.get("boss_kills", 0)) + n)
	var kills_by_diff_any = row.get("boss_kills_by_diff", {})
	var kills_by_diff: Dictionary = kills_by_diff_any if kills_by_diff_any is Dictionary else {}
	var key := str(d)
	kills_by_diff[key] = maxi(0, int(kills_by_diff.get(key, 0)) + n)
	row["boss_kills_by_diff"] = kills_by_diff
	progress[sid] = row
	save_data()
	_try_grant_first_clear(sid, d)
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

func is_first_clear_claimed(stage_id: String, diff: int) -> bool:
	var row := get_stage(stage_id)
	var claimed_any = row.get("first_clear_claimed", {})
	if not (claimed_any is Dictionary):
		return false
	var key := str(maxi(0, diff))
	return bool((claimed_any as Dictionary).get(key, false))

func set_first_clear_claimed(stage_id: String, diff: int, v: bool, emit_update: bool = true) -> void:
	var sid := stage_id.strip_edges()
	if sid.is_empty():
		return
	var row := get_stage(sid)
	var claimed_any = row.get("first_clear_claimed", {})
	var claimed: Dictionary = claimed_any if claimed_any is Dictionary else {}
	claimed[str(maxi(0, diff))] = bool(v)
	row["first_clear_claimed"] = claimed
	progress[sid] = row
	save_data()
	if emit_update:
		EventBus.notify_inventory_updated()

func can_upgrade(stage_id: String, stage_def: Dictionary, next_diff_index: int) -> Dictionary:
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
	var kill_diff := maxi(0, next_diff_index - 1)
	var have_kills := get_boss_kills(stage_id, kill_diff)
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
		"reward": {},
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

	var chk := can_upgrade(stage_id, stage_def, next)
	if not bool(chk.get("ok", false)):
		out["reason"] = str(chk.get("reason", "invalid"))
		out["need_kills"] = int(chk.get("need_kills", 0))
		out["have_kills"] = int(chk.get("have_kills", 0))
		out["need_items"] = chk.get("need_items", {})
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
	var reward := grant_upgrade_reward(stage_def, next)
	out["ok"] = true
	out["reason"] = ""
	out["new_diff"] = next
	out["reward"] = reward
	return out

func grant_upgrade_reward(stage_def: Dictionary, diff_index: int) -> Dictionary:
	var diffs_any = stage_def.get("difficulties", [])
	if not (diffs_any is Array):
		return _empty_reward()
	var diffs: Array = diffs_any
	if diff_index < 0 or diff_index >= diffs.size():
		return _empty_reward()
	var diff_any = diffs[diff_index]
	if not (diff_any is Dictionary):
		return _empty_reward()
	var diff_def: Dictionary = diff_any
	var unlock_any = diff_def.get("unlock", {})
	if not (unlock_any is Dictionary):
		return _empty_reward()
	var unlock: Dictionary = unlock_any
	var reward := _normalize_reward(unlock.get("reward", {}))

	var gold := int(reward.get("gold", 0))
	var skill_points := int(reward.get("skill_points", 0))
	var items_any = reward.get("items", {})
	var items: Dictionary = items_any if items_any is Dictionary else {}

	if gold > 0:
		PlayerModel.add_gold(gold)
	if skill_points > 0:
		SkillModel.grant_skill_points(skill_points, "")
	for item_id_any in items.keys():
		var item_id := str(item_id_any).strip_edges()
		var cnt := maxi(0, int(items.get(item_id_any, 0)))
		if item_id.is_empty() or cnt <= 0:
			continue
		InventoryModel.add_item(item_id, cnt, "system")

	return reward

func _try_grant_first_clear(stage_id: String, diff: int) -> void:
	var sid := stage_id.strip_edges()
	if sid.is_empty():
		return
	var d := maxi(0, diff)
	if is_first_clear_claimed(sid, d):
		return
	if get_boss_kills(sid, d) < 1:
		return
	var ctx := _find_stage_and_diff(sid, d)
	if ctx.is_empty():
		return
	var stage_name := str(ctx.get("stage_name", sid))
	var diff_def_any = ctx.get("diff_def", {})
	if not (diff_def_any is Dictionary):
		return
	var diff_def: Dictionary = diff_def_any
	var diff_name := str(diff_def.get("name", "难度%d" % d))
	var reward := _normalize_reward(diff_def.get("first_clear_reward", {}))

	set_first_clear_claimed(sid, d, true, false)
	if not _has_reward(reward):
		return

	var gold := int(reward.get("gold", 0))
	var skill_points := int(reward.get("skill_points", 0))
	var items_any = reward.get("items", {})
	var items: Dictionary = items_any if items_any is Dictionary else {}
	if gold > 0:
		PlayerModel.add_gold(gold)
	if skill_points > 0:
		SkillModel.grant_skill_points(skill_points, "")
	for item_id_any in items.keys():
		var item_id := str(item_id_any).strip_edges()
		var cnt := maxi(0, int(items.get(item_id_any, 0)))
		if item_id.is_empty() or cnt <= 0:
			continue
		InventoryModel.add_item(item_id, cnt, "system")

	EventBus.add_log("首通奖励：%s（%s） %s" % [stage_name, diff_name, _build_reward_summary_text(reward)])

func _find_stage_and_diff(stage_id: String, diff: int) -> Dictionary:
	var cfg: Dictionary = ConfigService.get_cfg()
	var stages_db_any = cfg.get("stages_db", {})
	if not (stages_db_any is Dictionary):
		return {}
	var stages_any = (stages_db_any as Dictionary).get("stages", [])
	if not (stages_any is Array):
		return {}
	for stage_any in stages_any:
		if not (stage_any is Dictionary):
			continue
		var stage: Dictionary = stage_any
		if str(stage.get("id", "")).strip_edges() != stage_id:
			continue
		var diffs_any = stage.get("difficulties", [])
		if not (diffs_any is Array):
			return {}
		var diffs: Array = diffs_any
		if diff < 0 or diff >= diffs.size():
			return {}
		var diff_def_any = diffs[diff]
		if not (diff_def_any is Dictionary):
			return {}
		return {
			"stage_name": str(stage.get("name", stage_id)),
			"diff_def": (diff_def_any as Dictionary).duplicate(true),
		}
	return {}

func _default_stage_row() -> Dictionary:
	return {
		"boss_kills": 0,
		"unlocked_diff": 0,
		"boss_kills_by_diff": {},
		"first_clear_claimed": {},
	}

func _normalize_stage_row(row_any: Variant) -> Dictionary:
	var row: Dictionary = row_any if row_any is Dictionary else {}
	var out := _default_stage_row()
	var boss_total := maxi(0, int(row.get("boss_kills", 0)))
	out["boss_kills"] = boss_total
	out["unlocked_diff"] = maxi(0, int(row.get("unlocked_diff", 0)))

	var by_diff: Dictionary = {}
	var by_diff_any = row.get("boss_kills_by_diff", {})
	var by_diff_sum := 0
	if by_diff_any is Dictionary:
		for key_any in (by_diff_any as Dictionary).keys():
			var key := str(key_any).strip_edges()
			if key.is_empty():
				continue
			var cnt := maxi(0, int((by_diff_any as Dictionary).get(key_any, 0)))
			by_diff[key] = cnt
			by_diff_sum += cnt
	if by_diff.is_empty() and boss_total > 0:
		by_diff["0"] = boss_total
	elif by_diff_sum > boss_total:
		out["boss_kills"] = by_diff_sum
	out["boss_kills_by_diff"] = by_diff

	var claimed: Dictionary = {}
	var claimed_any = row.get("first_clear_claimed", {})
	if claimed_any is Dictionary:
		for key_any in (claimed_any as Dictionary).keys():
			var key := str(key_any).strip_edges()
			if key.is_empty():
				continue
			claimed[key] = bool((claimed_any as Dictionary).get(key_any, false))
	out["first_clear_claimed"] = claimed
	return out

func _empty_reward() -> Dictionary:
	return {
		"gold": 0,
		"skill_points": 0,
		"items": {},
	}

func _normalize_reward(reward_any: Variant) -> Dictionary:
	var out := _empty_reward()
	if not (reward_any is Dictionary):
		return out
	var reward: Dictionary = reward_any
	out["gold"] = maxi(0, int(reward.get("gold", 0)))
	out["skill_points"] = maxi(0, int(reward.get("skill_points", 0)))
	var items: Dictionary = {}
	var items_any = reward.get("items", {})
	if items_any is Dictionary:
		for item_id_any in (items_any as Dictionary).keys():
			var item_id := str(item_id_any).strip_edges()
			var cnt := maxi(0, int((items_any as Dictionary).get(item_id_any, 0)))
			if item_id.is_empty() or cnt <= 0:
				continue
			items[item_id] = cnt
	out["items"] = items
	return out

func _has_reward(reward: Dictionary) -> bool:
	if int(reward.get("gold", 0)) > 0:
		return true
	if int(reward.get("skill_points", 0)) > 0:
		return true
	var items_any = reward.get("items", {})
	if items_any is Dictionary and not (items_any as Dictionary).is_empty():
		return true
	return false

func _build_reward_summary_text(reward: Dictionary) -> String:
	if reward.is_empty():
		return "无"
	var parts: Array[String] = []
	var gold := maxi(0, int(reward.get("gold", 0)))
	if gold > 0:
		parts.append("金币+%d" % gold)
	var skill_points := maxi(0, int(reward.get("skill_points", 0)))
	if skill_points > 0:
		parts.append("技能点+%d" % skill_points)
	var items_any = reward.get("items", {})
	if items_any is Dictionary:
		var item_parts: Array[String] = []
		for item_id_any in (items_any as Dictionary).keys():
			var item_id := str(item_id_any).strip_edges()
			var cnt := maxi(0, int((items_any as Dictionary).get(item_id_any, 0)))
			if item_id.is_empty() or cnt <= 0:
				continue
			item_parts.append("%s+%d" % [item_id, cnt])
		item_parts.sort()
		if item_parts.size() > 4:
			item_parts = item_parts.slice(0, 4)
			item_parts.append("...")
		parts.append_array(item_parts)
	if parts.is_empty():
		return "无"
	return " ".join(parts)
