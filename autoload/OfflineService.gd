extends Node

const SAVE_PATH := "user://offline.json"
const MAX_PATROL_SECONDS := 86400
const MIN_CLAIM_SECONDS := 20
const MARK_WRITE_INTERVAL := 5
const DEFAULT_ELITE_EVERY := 40
const DEFAULT_BOSS_EVERY := 120
const DEFAULT_ROUTE_DIFF := 0

var last_active_ts: int = 0
var _last_mark_write_ts: int = 0
var _pending_summary: Dictionary = {}

var patrol_route_stage_id: String = ""
var patrol_route_diff_index: int = DEFAULT_ROUTE_DIFF
var patrol_accumulated_seconds: int = 0
var patrol_records: Dictionary = {}

func _ready() -> void:
	ConfigService.load_cfg()
	load_state()
	set_process(true)
	mark_active_now()

func _process(_delta: float) -> void:
	mark_active_now()

func _notification(what: int) -> void:
	match what:
		NOTIFICATION_APPLICATION_PAUSED, NOTIFICATION_APPLICATION_FOCUS_OUT, NOTIFICATION_WM_CLOSE_REQUEST:
			mark_active_now()
		NOTIFICATION_APPLICATION_RESUMED, NOTIFICATION_APPLICATION_FOCUS_IN:
			mark_active_now()

func consume_pending_summary() -> Dictionary:
	if _pending_summary.is_empty():
		return {}
	var out := _pending_summary.duplicate(true)
	_pending_summary = {}
	return out

func mark_active_now() -> void:
	var now_ts := _now_ts()
	_tick_patrol(now_ts)
	if now_ts - _last_mark_write_ts < MARK_WRITE_INTERVAL:
		return
	_last_mark_write_ts = now_ts
	save_state()

func apply_offline_rewards() -> Dictionary:
	mark_active_now()
	return {}

func record_patrol_clear(stage_id: String, diff_index: int, clear_seconds: float) -> Dictionary:
	var sid := stage_id.strip_edges()
	var safe_diff := maxi(0, diff_index)
	var safe_clear := maxf(1.0, clear_seconds)
	if sid.is_empty():
		return {"ok": false, "reason": "missing_stage"}

	var route_changed := patrol_route_stage_id != sid or patrol_route_diff_index != safe_diff
	patrol_route_stage_id = sid
	patrol_route_diff_index = safe_diff

	var key := _route_key(sid, safe_diff)
	var record_any = patrol_records.get(key, {})
	var record: Dictionary = record_any if record_any is Dictionary else {}
	var old_best := float(record.get("best_clear_seconds", 0.0))
	var improved := old_best <= 0.0 or safe_clear < old_best
	if improved:
		patrol_records[key] = {
			"stage_id": sid,
			"diff_index": safe_diff,
			"best_clear_seconds": safe_clear,
		}

	save_state()
	if improved or route_changed:
		EventBus.notify_patrol_updated()

	return {
		"ok": true,
		"improved": improved,
		"route_changed": route_changed,
		"best_clear_seconds": float((patrol_records.get(key, {}) as Dictionary).get("best_clear_seconds", safe_clear)),
		"route_name": _stage_diff_name(sid, safe_diff),
	}

func get_patrol_status() -> Dictionary:
	var ctx := _resolve_active_patrol_context()
	if not bool(ctx.get("ok", false)):
		return {
			"has_route": false,
			"status_text": "尚未建立巡查路线",
			"summary_text": "通关任一主线难度后，即可建立自动巡查路线。",
			"can_claim": false,
			"accumulated_seconds": 0,
			"cap_seconds": MAX_PATROL_SECONDS,
		}

	var settlement := _estimate_patrol_rewards(patrol_accumulated_seconds, ctx)
	var has_items := false
	var item_grants_any = settlement.get("items", {})
	if item_grants_any is Dictionary:
		has_items = not (item_grants_any as Dictionary).is_empty()

	var status_text := "自动巡查中"
	if patrol_accumulated_seconds >= MAX_PATROL_SECONDS:
		status_text = "巡查累计已达24小时上限，请先领取"

	return {
		"has_route": true,
		"status_text": status_text,
		"route_name": str(ctx.get("route_name", "")),
		"stage_name": str(ctx.get("stage_name", "")),
		"difficulty_name": str(ctx.get("difficulty_name", "")),
		"accumulated_seconds": patrol_accumulated_seconds,
		"cap_seconds": MAX_PATROL_SECONDS,
		"is_capped": patrol_accumulated_seconds >= MAX_PATROL_SECONDS,
		"can_claim": patrol_accumulated_seconds >= MIN_CLAIM_SECONDS and bool(settlement.get("claimable", false)),
		"exp": maxi(0, int(settlement.get("exp", 0))),
		"has_materials": has_items,
		"has_rare_drop": bool(settlement.get("has_rare_drop", false)),
		"item_types": int(settlement.get("item_types", 0)),
	}

func claim_patrol_rewards() -> Dictionary:
	mark_active_now()
	var ctx := _resolve_active_patrol_context()
	if not bool(ctx.get("ok", false)):
		return {"ok": false, "reason": "no_route"}
	if patrol_accumulated_seconds < MIN_CLAIM_SECONDS:
		return {"ok": false, "reason": "too_soon"}

	var settlement := _estimate_patrol_rewards(patrol_accumulated_seconds, ctx)
	if not bool(settlement.get("claimable", false)):
		return {"ok": false, "reason": "empty"}

	var exp_gain := maxi(0, int(settlement.get("exp", 0)))
	var normal_kills := maxi(0, int(settlement.get("kills_normal", 0)))
	var elite_kills := maxi(0, int(settlement.get("kills_elite", 0)))
	var boss_kills := maxi(0, int(settlement.get("kills_boss", 0)))
	var item_grants_any = settlement.get("items", {})
	var item_grants: Dictionary = item_grants_any if item_grants_any is Dictionary else {}
	var total_loot := 0
	for key_any in item_grants.keys():
		total_loot += maxi(0, int(item_grants.get(key_any, 0)))

	TaskService.begin_batch()
	if normal_kills > 0:
		TaskService.on_kill("normal", normal_kills)
	if elite_kills > 0:
		TaskService.on_kill("elite", elite_kills)
	if boss_kills > 0:
		TaskService.on_kill("boss", boss_kills)
	if exp_gain > 0:
		ProgressModel.add_exp(exp_gain, "patrol")
	if not item_grants.is_empty():
		InventoryModel.add_items_bulk(item_grants, "system")
	if total_loot > 0:
		TaskService.on_loot(total_loot)
	var task_msgs := TaskService.end_batch()

	var granted_seconds := patrol_accumulated_seconds
	patrol_accumulated_seconds = 0
	save_state()
	EventBus.notify_patrol_updated()
	EventBus.request_profile_sync("patrol_claim")

	var route_name := str(ctx.get("route_name", "主线巡查"))
	EventBus.add_log("已领取自动巡查收益：%s｜经验+%d｜物品%d类" % [
		route_name,
		exp_gain,
		item_grants.size(),
	])
	if bool(settlement.get("has_rare_drop", false)):
		EventBus.add_log("本轮巡查中含低概率稀有掉落。")
	if task_msgs.size() > 0:
		EventBus.add_log("自动巡查期间新增可领取宗务：%d项" % task_msgs.size())

	return {
		"ok": true,
		"route_name": route_name,
		"seconds": granted_seconds,
		"exp": exp_gain,
		"items": item_grants,
		"item_types": item_grants.size(),
		"kills_normal": normal_kills,
		"kills_elite": elite_kills,
		"kills_boss": boss_kills,
		"has_rare_drop": bool(settlement.get("has_rare_drop", false)),
	}

func load_state() -> void:
	last_active_ts = 0
	_last_mark_write_ts = 0
	_pending_summary = {}
	patrol_route_stage_id = ""
	patrol_route_diff_index = DEFAULT_ROUTE_DIFF
	patrol_accumulated_seconds = 0
	patrol_records = {}
	if not FileAccess.file_exists(SAVE_PATH):
		return
	var text := FileAccess.get_file_as_string(SAVE_PATH)
	var parsed: Variant = JSON.parse_string(text)
	if not (parsed is Dictionary):
		return
	var data: Dictionary = parsed
	last_active_ts = maxi(0, int(data.get("last_active_ts", 0)))
	patrol_route_stage_id = str(data.get("patrol_route_stage_id", "")).strip_edges()
	patrol_route_diff_index = maxi(0, int(data.get("patrol_route_diff_index", DEFAULT_ROUTE_DIFF)))
	patrol_accumulated_seconds = clampi(int(data.get("patrol_accumulated_seconds", 0)), 0, MAX_PATROL_SECONDS)
	var records_any = data.get("patrol_records", {})
	if records_any is Dictionary:
		for key_any in (records_any as Dictionary).keys():
			var route_key := str(key_any).strip_edges()
			if route_key.is_empty():
				continue
			var row_any = (records_any as Dictionary).get(key_any, {})
			if not (row_any is Dictionary):
				continue
			var row: Dictionary = row_any
			var stage_id := str(row.get("stage_id", "")).strip_edges()
			if stage_id.is_empty():
				continue
			patrol_records[route_key] = {
				"stage_id": stage_id,
				"diff_index": maxi(0, int(row.get("diff_index", 0))),
				"best_clear_seconds": maxf(1.0, float(row.get("best_clear_seconds", 1.0))),
			}

func save_state() -> void:
	var file := FileAccess.open(SAVE_PATH, FileAccess.WRITE)
	if file == null:
		push_warning("OfflineService: failed to open save file")
		return
	file.store_string(JSON.stringify({
		"last_active_ts": last_active_ts,
		"patrol_route_stage_id": patrol_route_stage_id,
		"patrol_route_diff_index": patrol_route_diff_index,
		"patrol_accumulated_seconds": patrol_accumulated_seconds,
		"patrol_records": patrol_records,
	}))

func _tick_patrol(now_ts: int) -> void:
	if now_ts <= 0:
		return
	if last_active_ts <= 0:
		last_active_ts = now_ts
		return
	var dt := clampi(now_ts - last_active_ts, 0, MAX_PATROL_SECONDS)
	last_active_ts = now_ts
	if dt <= 0:
		return
	if patrol_route_stage_id.is_empty():
		return
	if not _has_patrol_record(patrol_route_stage_id, patrol_route_diff_index):
		return
	patrol_accumulated_seconds = mini(MAX_PATROL_SECONDS, patrol_accumulated_seconds + dt)

func _has_patrol_record(stage_id: String, diff_index: int) -> bool:
	return patrol_records.has(_route_key(stage_id, diff_index))

func _resolve_active_patrol_context() -> Dictionary:
	if patrol_route_stage_id.is_empty():
		return {"ok": false, "reason": "no_route"}
	var key := _route_key(patrol_route_stage_id, patrol_route_diff_index)
	var record_any = patrol_records.get(key, {})
	if not (record_any is Dictionary):
		return {"ok": false, "reason": "missing_record"}
	var record: Dictionary = record_any
	var stage := _find_stage_cfg(patrol_route_stage_id)
	if stage.is_empty():
		return {"ok": false, "reason": "missing_stage"}
	var difficulty := _find_stage_difficulty(stage, patrol_route_diff_index)
	if difficulty.is_empty():
		return {"ok": false, "reason": "missing_difficulty"}
	return {
		"ok": true,
		"record": record,
		"stage": stage,
		"difficulty": difficulty,
		"stage_name": str(stage.get("name", patrol_route_stage_id)),
		"difficulty_name": str(difficulty.get("difficulty_name", "普通")),
		"route_name": _stage_diff_name(patrol_route_stage_id, patrol_route_diff_index),
		"monster_map": _build_monster_map(),
	}

func _estimate_patrol_rewards(seconds: int, ctx: Dictionary) -> Dictionary:
	if seconds <= 0:
		return {"claimable": false, "items": {}, "exp": 0}
	if not bool(ctx.get("ok", false)):
		return {"claimable": false, "items": {}, "exp": 0}
	var record_any = ctx.get("record", {})
	if not (record_any is Dictionary):
		return {"claimable": false, "items": {}, "exp": 0}
	var record: Dictionary = record_any
	var best_clear_seconds := maxf(1.0, float(record.get("best_clear_seconds", 1.0)))
	var difficulty_any = ctx.get("difficulty", {})
	if not (difficulty_any is Dictionary):
		return {"claimable": false, "items": {}, "exp": 0}
	var difficulty: Dictionary = difficulty_any
	var monster_map_any = ctx.get("monster_map", {})
	var monster_map: Dictionary = monster_map_any if monster_map_any is Dictionary else {}
	var sequence := _build_patrol_sequence(difficulty)
	if sequence.is_empty():
		return {"claimable": false, "items": {}, "exp": 0}

	var profiles := {
		"normal": _build_pool_profile(difficulty.get("normal_monsters", []), monster_map),
		"elite": _build_pool_profile(difficulty.get("elite_monsters", []), monster_map),
		"boss": _build_pool_profile(difficulty.get("boss_monsters", []), monster_map),
	}

	var step_total := 0
	var per_cycle := {"normal": 0, "elite": 0, "boss": 0}
	for segment_any in sequence:
		if not (segment_any is Dictionary):
			continue
		var segment: Dictionary = segment_any
		var count := maxi(0, int(segment.get("count", 0)))
		var kind := str(segment.get("kind", "normal")).strip_edges()
		step_total += count
		per_cycle[kind] = int(per_cycle.get(kind, 0)) + count
	if step_total <= 0:
		return {"claimable": false, "items": {}, "exp": 0}

	var full_cycles := int(floor(float(seconds) / best_clear_seconds))
	var remaining_seconds := maxf(0.0, float(seconds) - float(full_cycles) * best_clear_seconds)
	var step_seconds := best_clear_seconds / float(step_total)
	var partial_steps := int(floor(remaining_seconds / maxf(0.01, step_seconds)))

	var total_counts := {
		"normal": int(per_cycle.get("normal", 0)) * full_cycles,
		"elite": int(per_cycle.get("elite", 0)) * full_cycles,
		"boss": int(per_cycle.get("boss", 0)) * full_cycles,
	}

	var steps_left := partial_steps
	for segment_any in sequence:
		if steps_left <= 0:
			break
		if not (segment_any is Dictionary):
			continue
		var segment: Dictionary = segment_any
		var count := maxi(0, int(segment.get("count", 0)))
		if count <= 0:
			continue
		var take := mini(steps_left, count)
		var kind := str(segment.get("kind", "normal")).strip_edges()
		total_counts[kind] = int(total_counts.get(kind, 0)) + take
		steps_left -= take

	var exp_total := 0.0
	var expected_items: Dictionary = {}
	var has_rare_drop := false
	for kind_any in ["normal", "elite", "boss"]:
		var kind := str(kind_any)
		var kill_count := maxi(0, int(total_counts.get(kind, 0)))
		if kill_count <= 0:
			continue
		var profile_any = profiles.get(kind, {})
		if not (profile_any is Dictionary):
			continue
		var profile: Dictionary = profile_any
		exp_total += float(profile.get("avg_exp", 0.0)) * float(kill_count)
		has_rare_drop = has_rare_drop or bool(profile.get("has_rare_drop", false))
		_accumulate_expected_items(expected_items, profile.get("expected_items", {}), kill_count)

	var item_grants := _realize_expected_items(expected_items)
	return {
		"claimable": full_cycles > 0 or partial_steps > 0,
		"full_cycles": full_cycles,
		"partial_steps": partial_steps,
		"kills_normal": int(total_counts.get("normal", 0)),
		"kills_elite": int(total_counts.get("elite", 0)),
		"kills_boss": int(total_counts.get("boss", 0)),
		"exp": maxi(0, int(round(exp_total))),
		"items": item_grants,
		"item_types": item_grants.size(),
		"has_rare_drop": has_rare_drop,
	}

func _build_patrol_sequence(difficulty: Dictionary) -> Array[Dictionary]:
	var elite_every := _resolve_spawn_every(difficulty.get("elite_spawn_rule", null), DEFAULT_ELITE_EVERY)
	var boss_every := _resolve_spawn_every(difficulty.get("boss_spawn_rule", null), DEFAULT_BOSS_EVERY)
	var out: Array[Dictionary] = []
	var consumed_normals := 0
	var elite_count := 0
	if elite_every > 0 and elite_every < boss_every:
		elite_count = int(floor(float(maxi(0, boss_every - 1)) / float(elite_every)))
	for idx in range(elite_count):
		var threshold := elite_every * (idx + 1)
		var normal_count := maxi(0, threshold - consumed_normals)
		if normal_count > 0:
			out.append({"kind": "normal", "count": normal_count})
		out.append({"kind": "elite", "count": 1})
		consumed_normals = threshold
	var tail_normals := maxi(0, boss_every - consumed_normals)
	if tail_normals > 0:
		out.append({"kind": "normal", "count": tail_normals})
	out.append({"kind": "boss", "count": 1})
	return out

func _resolve_spawn_every(rule_any: Variant, fallback_value: int) -> int:
	if not (rule_any is Dictionary):
		return fallback_value
	var rule: Dictionary = rule_any
	if not rule.has("every_kills"):
		return fallback_value
	return maxi(1, int(rule.get("every_kills", fallback_value)))

func _build_pool_profile(pool_any: Variant, monster_map: Dictionary) -> Dictionary:
	var out := {
		"avg_exp": 0.0,
		"expected_items": {},
		"has_rare_drop": false,
	}
	if not (pool_any is Array):
		return out
	var pool: Array = pool_any
	var total_weight := 0.0
	for entry_any in pool:
		if not (entry_any is Dictionary):
			continue
		var entry: Dictionary = entry_any
		total_weight += float(maxi(0, int(entry.get("weight", entry.get("w", 0)))))
	if total_weight <= 0.0:
		return out

	for entry_any in pool:
		if not (entry_any is Dictionary):
			continue
		var entry: Dictionary = entry_any
		var weight := float(maxi(0, int(entry.get("weight", entry.get("w", 0)))))
		if weight <= 0.0:
			continue
		var monster_id := str(entry.get("monster_id", entry.get("id", ""))).strip_edges()
		if monster_id.is_empty():
			continue
		var monster_any = monster_map.get(monster_id, {})
		if not (monster_any is Dictionary):
			continue
		var monster: Dictionary = monster_any
		var ratio := weight / total_weight
		out["avg_exp"] = float(out.get("avg_exp", 0.0)) + float(monster.get("exp", 0)) * ratio
		var drops_any = monster.get("drops", [])
		if not (drops_any is Array):
			continue
		for drop_any in drops_any:
			if not (drop_any is Dictionary):
				continue
			var drop: Dictionary = drop_any
			if not bool(drop.get("is_enabled", true)):
				continue
			var item_id := str(drop.get("item_id", "")).strip_edges()
			if item_id.is_empty():
				continue
			var count_min := maxi(1, int(drop.get("count_min", 1)))
			var count_max := maxi(count_min, int(drop.get("count_max", count_min)))
			var avg_count := float(count_min + count_max) / 2.0
			var chance := 1.0
			if drop.has("drop_rate") and drop.get("drop_rate", null) != null:
				chance = clampf(float(drop.get("drop_rate", 1.0)), 0.0, 1.0)
			var expected_items_any = out.get("expected_items", {})
			var expected_items: Dictionary = expected_items_any if expected_items_any is Dictionary else {}
			expected_items[item_id] = float(expected_items.get(item_id, 0.0)) + ratio * avg_count * chance
			out["expected_items"] = expected_items
			if chance < 1.0 or _is_rare_item(item_id):
				out["has_rare_drop"] = true
	return out

func _accumulate_expected_items(target: Dictionary, source_any: Variant, multiplier: int) -> void:
	if multiplier <= 0:
		return
	if not (source_any is Dictionary):
		return
	var source: Dictionary = source_any
	for item_id_any in source.keys():
		var item_id := str(item_id_any).strip_edges()
		if item_id.is_empty():
			continue
		target[item_id] = float(target.get(item_id, 0.0)) + float(source.get(item_id_any, 0.0)) * float(multiplier)

func _realize_expected_items(expected_items: Dictionary) -> Dictionary:
	var out: Dictionary = {}
	for item_id_any in expected_items.keys():
		var item_id := str(item_id_any).strip_edges()
		if item_id.is_empty():
			continue
		var expected := maxf(0.0, float(expected_items.get(item_id_any, 0.0)))
		if expected <= 0.0:
			continue
		var count := int(floor(expected))
		var remainder := expected - float(count)
		if randf() < remainder:
			count += 1
		if count > 0:
			out[item_id] = count
	return out

func _build_monster_map() -> Dictionary:
	var out: Dictionary = {}
	var monsters_any = ConfigService.get_monsters_db().get("monsters", [])
	if not (monsters_any is Array):
		return out
	for row_any in monsters_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		var monster_id := str(row.get("id", "")).strip_edges()
		if monster_id.is_empty():
			continue
		out[monster_id] = row
	return out

func _find_stage_cfg(stage_id: String) -> Dictionary:
	var stages_any = ConfigService.get_stages_db().get("stages", [])
	if not (stages_any is Array):
		return {}
	for row_any in stages_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		if str(row.get("id", "")).strip_edges() == stage_id:
			return row.duplicate(true)
	return {}

func _find_stage_difficulty(stage: Dictionary, diff_index: int) -> Dictionary:
	var diffs_any = stage.get("difficulties", [])
	if not (diffs_any is Array):
		return {}
	var diffs: Array = diffs_any
	if diffs.is_empty():
		return {}
	var safe_index := clampi(diff_index, 0, diffs.size() - 1)
	var row_any = diffs[safe_index]
	if row_any is Dictionary:
		return (row_any as Dictionary).duplicate(true)
	return {}

func _stage_diff_name(stage_id: String, diff_index: int) -> String:
	var stage := _find_stage_cfg(stage_id)
	if stage.is_empty():
		return stage_id
	var diff := _find_stage_difficulty(stage, diff_index)
	var stage_name := str(stage.get("name", stage_id)).strip_edges()
	var diff_name := str(diff.get("difficulty_name", "")).strip_edges()
	return stage_name if diff_name.is_empty() else "%s（%s）" % [stage_name, diff_name]

func _is_rare_item(item_id: String) -> bool:
	if item_id.is_empty():
		return false
	var items_db_any = ConfigService.get_cfg().get("items_db", {})
	if not (items_db_any is Dictionary):
		return false
	var items_any = (items_db_any as Dictionary).get("items", [])
	if not (items_any is Array):
		return false
	for row_any in items_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		if str(row.get("id", "")).strip_edges() != item_id:
			continue
		var rarity := str(row.get("rarity", "white")).strip_edges().to_lower()
		return rarity != "white"
	return false

func _route_key(stage_id: String, diff_index: int) -> String:
	return "%s::%d" % [stage_id.strip_edges(), maxi(0, diff_index)]

func _now_ts() -> int:
	return int(Time.get_unix_time_from_system())
