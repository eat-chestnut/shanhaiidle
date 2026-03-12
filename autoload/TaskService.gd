extends Node

const SAVE_KEY := "sect_tasks"
const GROUP_DAILY := "daily"
const GROUP_MILESTONE := "milestone"

var daily_date: String = ""
var daily: Dictionary = {}
var milestone: Dictionary = {}
var _batch_mode := false
var _batch_msgs: Array[String] = []

func _ready() -> void:
	_load_state()
	var changed := reset_daily_if_needed()
	changed = _ensure_entries() or changed
	if changed:
		_save_state()

func begin_batch() -> void:
	_batch_mode = true
	_batch_msgs.clear()

func end_batch() -> Array[String]:
	_batch_mode = false
	var out: Array[String] = _batch_msgs.duplicate()
	_batch_msgs.clear()
	return out

func on_visit_sect() -> void:
	if _before_progress_change():
		_apply_goal("visit_sect", 1)
		_after_progress_change()

func on_kill(kind: String = "normal", count: int = 1) -> void:
	if count <= 0 or not _before_progress_change():
		return
	var safe_kind := kind.strip_edges().to_lower()
	if safe_kind == "boss":
		_apply_goal("kill_boss", count)
	elif safe_kind == "normal":
		_apply_goal("kill_normal", count)
	_after_progress_change()

func on_loot(count: int = 1) -> void:
	if count <= 0 or not _before_progress_change():
		return
	_apply_goal("collect_loot", count)
	_after_progress_change()

func on_level_changed(_new_lv: int) -> void:
	if reset_daily_if_needed() or _ensure_entries():
		_save_state()

func on_class_switched() -> void:
	if reset_daily_if_needed() or _ensure_entries():
		_save_state()

func on_stage_cleared(_stage_id: String) -> void:
	if not _before_progress_change():
		return
	_apply_goal("clear_stage", 1)
	_after_progress_change()

func on_material_dungeon_challenged(_dungeon_id: String) -> void:
	if not _before_progress_change():
		return
	_apply_goal("complete_dungeon", 1)
	_after_progress_change()

func on_spend_stamina(amount: int) -> void:
	if amount <= 0 or not _before_progress_change():
		return
	_apply_goal("spend_stamina", amount)
	_after_progress_change()

func on_forge_done(count: int = 1) -> void:
	if count <= 0 or not _before_progress_change():
		return
	_apply_goal("forge", count)
	_after_progress_change()

func on_dungeon_upgraded(_dungeon_id: String) -> void:
	if reset_daily_if_needed() or _ensure_entries():
		_save_state()

func get_daily_rows() -> Array[Dictionary]:
	return _build_rows(GROUP_DAILY)

func get_milestone_rows() -> Array[Dictionary]:
	return _build_rows(GROUP_MILESTONE)

func get_summary() -> Dictionary:
	var changed := reset_daily_if_needed()
	changed = _ensure_entries() or changed
	if changed:
		_save_state()
	var daily_rows := get_daily_rows()
	var milestone_rows := get_milestone_rows()
	var claimable := 0
	for row in daily_rows:
		if bool(row.get("can_claim", false)):
			claimable += 1
	for row in milestone_rows:
		if bool(row.get("can_claim", false)):
			claimable += 1
	return {
		"daily_total": daily_rows.size(),
		"milestone_total": milestone_rows.size(),
		"daily_unlocked": _count_unlocked(daily_rows),
		"milestone_unlocked": _count_unlocked(milestone_rows),
		"claimable": claimable,
	}

func claim_task(group: String, task_id: String) -> Dictionary:
	var safe_group := _normalize_group(group)
	var safe_id := task_id.strip_edges()
	if safe_id.is_empty():
		return {"ok": false, "reason": "not_found"}
	reset_daily_if_needed()
	_ensure_entries()
	var row := _find_row(safe_group, safe_id)
	if row.is_empty():
		return {"ok": false, "reason": "not_found"}
	if not _is_row_unlocked(row):
		return {"ok": false, "reason": "locked", "unlock_stage_name": _stage_name(str(row.get("unlock_stage_id", "")))}
	var entry := _entry_for_group(safe_group, safe_id)
	if bool(entry.get("claimed", false)):
		return {"ok": false, "reason": "claimed"}
	var target := maxi(1, int(row.get("target", 1)))
	var progress := maxi(0, int(entry.get("progress", 0)))
	if progress < target:
		return {"ok": false, "reason": "incomplete", "progress": progress, "target": target}
	entry["claimed"] = true
	_set_entry(safe_group, safe_id, entry)
	_grant_reward(row.get("rewards", {}), str(row.get("name", safe_id)))
	_save_state()
	EventBus.notify_tasks_updated()
	if not _batch_mode:
		EventBus.add_log("宗门任务完成：%s" % str(row.get("name", safe_id)))
	return {"ok": true, "reason": "", "task_id": safe_id, "name": str(row.get("name", safe_id))}

func claim_all_available(group: String = "") -> Dictionary:
	var groups: Array[String] = []
	if group.strip_edges().is_empty():
		groups = [GROUP_DAILY, GROUP_MILESTONE]
	else:
		groups = [_normalize_group(group)]
	var claimed_names: Array[String] = []
	for group_key in groups:
		for row in _build_rows(group_key):
			if not bool(row.get("can_claim", false)):
				continue
			var ret := claim_task(group_key, str(row.get("task_id", "")))
			if bool(ret.get("ok", false)):
				claimed_names.append(str(ret.get("name", "")))
	return {
		"ok": not claimed_names.is_empty(),
		"claimed_names": claimed_names,
	}

func reset_daily_if_needed() -> bool:
	var today := Time.get_date_string_from_system()
	if daily_date == today and not daily.is_empty():
		return false
	daily_date = today
	daily.clear()
	for row in _task_rows(GROUP_DAILY):
		var task_id := str(row.get("task_id", "")).strip_edges()
		if task_id.is_empty():
			continue
		daily[task_id] = {"progress": 0, "claimed": false}
	return true

func _before_progress_change() -> bool:
	var changed := reset_daily_if_needed()
	changed = _ensure_entries() or changed
	if changed:
		_save_state()
	return true

func _after_progress_change() -> void:
	_save_state()
	EventBus.notify_tasks_updated()

func _apply_goal(goal_type: String, delta: int) -> void:
	if delta <= 0:
		return
	for group_key in [GROUP_DAILY, GROUP_MILESTONE]:
		for row in _task_rows(group_key):
			if str(row.get("goal_type", "")) != goal_type:
				continue
			if not _is_row_unlocked(row):
				continue
			var task_id := str(row.get("task_id", "")).strip_edges()
			if task_id.is_empty():
				continue
			var entry := _entry_for_group(group_key, task_id)
			if bool(entry.get("claimed", false)):
				continue
			var old_progress := maxi(0, int(entry.get("progress", 0)))
			var target := maxi(1, int(row.get("target", 1)))
			var new_progress := mini(target, old_progress + delta)
			entry["progress"] = new_progress
			_set_entry(group_key, task_id, entry)
			if old_progress < target and new_progress >= target:
				_push_reached_msg(str(row.get("name", task_id)))

func _push_reached_msg(task_name: String) -> void:
	var msg := "宗门任务可领取：%s" % task_name
	if _batch_mode:
		if _batch_msgs.find(msg) == -1:
			_batch_msgs.append(msg)
		return
	EventBus.add_log(msg)

func _build_rows(group: String) -> Array[Dictionary]:
	var changed := reset_daily_if_needed()
	changed = _ensure_entries() or changed
	if changed:
		_save_state()
	var safe_group := _normalize_group(group)
	var rows: Array[Dictionary] = []
	for row in _task_rows(safe_group):
		var task_id := str(row.get("task_id", "")).strip_edges()
		if task_id.is_empty():
			continue
		var entry := _entry_for_group(safe_group, task_id)
		var target := maxi(1, int(row.get("target", 1)))
		var progress := maxi(0, int(entry.get("progress", 0)))
		var claimed := bool(entry.get("claimed", false))
		var unlocked := _is_row_unlocked(row)
		var reward_summary := _reward_summary(row.get("rewards", {}))
		var unlock_stage_id := str(row.get("unlock_stage_id", "")).strip_edges()
		rows.append({
			"task_id": task_id,
			"name": str(row.get("name", task_id)),
			"desc": str(row.get("desc", "")),
			"goal_type": str(row.get("goal_type", "")),
			"target": target,
			"progress": progress,
			"claimed": claimed,
			"is_unlocked": unlocked,
			"can_claim": unlocked and not claimed and progress >= target,
			"reward_summary": reward_summary,
			"unlock_stage_id": unlock_stage_id,
			"unlock_stage_name": _stage_name(unlock_stage_id),
		})
	return rows

func _count_unlocked(rows: Array[Dictionary]) -> int:
	var count := 0
	for row in rows:
		if bool(row.get("is_unlocked", false)):
			count += 1
	return count

func _reward_summary(reward_any: Variant) -> String:
	if not (reward_any is Dictionary):
		return "—"
	var reward: Dictionary = reward_any
	var parts: Array[String] = []
	var gold := maxi(0, int(reward.get("gold", 0)))
	var contribution := maxi(0, int(reward.get("sect_contribution", 0)))
	var skill_points := maxi(0, int(reward.get("skill_points", 0)))
	var spirit_stone := maxi(0, int(reward.get("spirit_stone", 0)))
	if gold > 0:
		parts.append("金币+%d" % gold)
	if spirit_stone > 0:
		parts.append("灵石+%d" % spirit_stone)
	if contribution > 0:
		parts.append("宗门贡献+%d" % contribution)
	if skill_points > 0:
		parts.append("技能点+%d" % skill_points)
	var items_any = reward.get("items", [])
	if items_any is Array:
		for item_any in items_any:
			if not (item_any is Dictionary):
				continue
			var item: Dictionary = item_any
			var item_id := str(item.get("item_id", "")).strip_edges()
			if item_id.is_empty():
				continue
			parts.append("%s×%d" % [_item_name(item_id), maxi(1, int(item.get("count", 1)))])
	return "、".join(parts) if not parts.is_empty() else "—"

func _task_rows(group: String) -> Array[Dictionary]:
	var cfg := _config()
	var source_any: Variant = []
	match _normalize_group(group):
		GROUP_DAILY:
			source_any = cfg.get("daily_tasks", [])
		_:
			source_any = cfg.get("milestone_tasks", [])
	var out: Array[Dictionary] = []
	if source_any is Array:
		for row_any in source_any:
			if not (row_any is Dictionary):
				continue
			var row: Dictionary = row_any
			if not bool(row.get("is_enabled", true)):
				continue
			out.append(row.duplicate(true))
	out.sort_custom(func(a: Dictionary, b: Dictionary) -> bool:
		var sa := int(a.get("sort_order", 0))
		var sb := int(b.get("sort_order", 0))
		if sa != sb:
			return sa < sb
		return str(a.get("task_id", "")) < str(b.get("task_id", ""))
	)
	return out

func _find_row(group: String, task_id: String) -> Dictionary:
	for row in _task_rows(group):
		if str(row.get("task_id", "")) == task_id:
			return row
	return {}

func _is_row_unlocked(row: Dictionary) -> bool:
	var unlock_stage_id := str(row.get("unlock_stage_id", "")).strip_edges()
	return unlock_stage_id.is_empty() or MapProgressModel.is_stage_cleared(unlock_stage_id)

func _normalize_group(group: String) -> String:
	var safe_group := group.strip_edges().to_lower()
	if safe_group == GROUP_MILESTONE:
		return GROUP_MILESTONE
	return GROUP_DAILY

func _entry_dict(group: String) -> Dictionary:
	return daily if _normalize_group(group) == GROUP_DAILY else milestone

func _entry_for_group(group: String, task_id: String) -> Dictionary:
	var src := _entry_dict(group)
	var entry_any = src.get(task_id, {})
	var entry: Dictionary = {}
	if entry_any is Dictionary:
		entry = (entry_any as Dictionary).duplicate(true)
	entry["progress"] = maxi(0, int(entry.get("progress", 0)))
	entry["claimed"] = bool(entry.get("claimed", false))
	return entry

func _set_entry(group: String, task_id: String, entry: Dictionary) -> void:
	if _normalize_group(group) == GROUP_DAILY:
		daily[task_id] = entry
	else:
		milestone[task_id] = entry

func _ensure_entries() -> bool:
	var changed := false
	for row in _task_rows(GROUP_DAILY):
		var task_id := str(row.get("task_id", "")).strip_edges()
		if task_id.is_empty() or daily.has(task_id):
			continue
		daily[task_id] = {"progress": 0, "claimed": false}
		changed = true
	for row in _task_rows(GROUP_MILESTONE):
		var task_id := str(row.get("task_id", "")).strip_edges()
		if task_id.is_empty() or milestone.has(task_id):
			continue
		milestone[task_id] = {"progress": 0, "claimed": false}
		changed = true
	return changed

func _grant_reward(reward_any: Variant, reason: String) -> void:
	if not (reward_any is Dictionary):
		return
	var reward: Dictionary = reward_any
	var gold := maxi(0, int(reward.get("gold", 0)))
	var spirit_stone := maxi(0, int(reward.get("spirit_stone", 0)))
	var contribution := maxi(0, int(reward.get("sect_contribution", 0)))
	var skill_points := maxi(0, int(reward.get("skill_points", 0)))
	if gold > 0:
		PlayerModel.add_gold(gold)
	if spirit_stone > 0:
		PlayerModel.add_spirit_stone(spirit_stone)
	if contribution > 0:
		PlayerModel.add_sect_contribution(contribution)
	if skill_points > 0:
		SkillModel.grant_skill_points(skill_points, reason)
	var items_any = reward.get("items", [])
	if items_any is Array:
		for item_any in items_any:
			if not (item_any is Dictionary):
				continue
			var item: Dictionary = item_any
			var item_id := str(item.get("item_id", "")).strip_edges()
			var count := maxi(0, int(item.get("count", 0)))
			if item_id.is_empty() or count <= 0:
				continue
			InventoryModel.add_item(item_id, count, "system")

func _config() -> Dictionary:
	return ConfigService.get_sect_tasks()

func _load_state() -> void:
	daily_date = ""
	daily = {}
	milestone = {}
	var data := SaveService.get_section(SAVE_KEY)
	daily_date = str(data.get("daily_date", ""))
	var daily_any = data.get("daily", {})
	if daily_any is Dictionary:
		daily = (daily_any as Dictionary).duplicate(true)
	var milestone_any = data.get("milestone", {})
	if milestone_any is Dictionary:
		milestone = (milestone_any as Dictionary).duplicate(true)

func _save_state() -> void:
	SaveService.set_section(SAVE_KEY, {
		"daily_date": daily_date,
		"daily": daily.duplicate(true),
		"milestone": milestone.duplicate(true),
	})

func _stage_name(stage_id: String) -> String:
	if stage_id.is_empty():
		return ""
	var rows_any = ConfigService.get_stages_db().get("stages", [])
	if not (rows_any is Array):
		return stage_id
	for row_any in rows_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		if str(row.get("id", "")).strip_edges() == stage_id:
			return str(row.get("name", stage_id))
	return stage_id

func _item_name(item_id: String) -> String:
	if item_id.is_empty():
		return ""
	var cfg: Dictionary = ConfigService.get_cfg()
	var items_db_any = cfg.get("items_db", {})
	if not (items_db_any is Dictionary):
		return item_id
	var rows_any = (items_db_any as Dictionary).get("items", [])
	if not (rows_any is Array):
		return item_id
	for row_any in rows_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		if str(row.get("id", "")).strip_edges() == item_id:
			return str(row.get("name", item_id))
	return item_id
