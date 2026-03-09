extends Node

const SAVE_PATH := "user://offline.json"
const MAX_OFFLINE_SECONDS := 86400
const MIN_SETTLE_SECONDS := 20
const MARK_WRITE_INTERVAL := 5

var last_active_ts: int = 0
var _last_mark_write_ts: int = 0
var _pending_summary: Dictionary = {}

func _ready() -> void:
	ConfigService.load_cfg()
	PerfTracker.load_data()
	load_state()
	set_process(true)
	var s := apply_offline_rewards()
	if not s.is_empty():
		_pending_summary = s

func _process(_delta: float) -> void:
	mark_active_now()

func _notification(what: int) -> void:
	match what:
		NOTIFICATION_APPLICATION_PAUSED, NOTIFICATION_APPLICATION_FOCUS_OUT, NOTIFICATION_WM_CLOSE_REQUEST:
			mark_active_now()
		NOTIFICATION_APPLICATION_RESUMED, NOTIFICATION_APPLICATION_FOCUS_IN:
			var s := apply_offline_rewards()
			if not s.is_empty():
				_pending_summary = s

func consume_pending_summary() -> Dictionary:
	if _pending_summary.is_empty():
		return {}
	var s := _pending_summary
	_pending_summary = {}
	return s

func mark_active_now() -> void:
	var now_ts := _now_ts()
	PerfTracker.tick(now_ts)
	PerfTracker.maybe_save(now_ts)
	if now_ts - _last_mark_write_ts < MARK_WRITE_INTERVAL:
		return
	last_active_ts = now_ts
	_last_mark_write_ts = now_ts
	save_state()

func apply_offline_rewards() -> Dictionary:
	var now_ts := _now_ts()
	if last_active_ts <= 0:
		last_active_ts = now_ts
		save_state()
		return {}

	var dt := clampi(now_ts - last_active_ts, 0, MAX_OFFLINE_SECONDS)
	if dt < MIN_SETTLE_SECONDS:
		last_active_ts = now_ts
		save_state()
		return {}

	var totals: Dictionary = PerfTracker.sum_last_hour()
	var win := maxi(60, PerfTracker.window_seconds())
	var scale := float(dt) / float(win)

	var offline_kills := int(round(float(int(totals.get("kills", 0))) * scale))
	var offline_elite := int(round(float(int(totals.get("kills_elite", 0))) * scale))
	var offline_boss := int(round(float(int(totals.get("kills_boss", 0))) * scale))
	var offline_exp := int(round(float(int(totals.get("exp", 0))) * scale))
	if offline_boss > 0:
		var sid := str(GrindModel.stage_id).strip_edges()
		if not sid.is_empty():
			MapProgressModel.add_boss_kill(sid, int(GrindModel.diff_index), offline_boss)

	TaskService.begin_batch()
	TaskService.on_kill("normal", offline_kills)
	TaskService.on_kill("elite", offline_elite)
	TaskService.on_kill("boss", offline_boss)

	if offline_exp > 0:
		ProgressModel.add_exp(offline_exp, "offline")
	TaskService.on_level_changed(ProgressModel.level)

	var items_summary: Dictionary = {}
	var items_any = totals.get("items", {})
	if items_any is Dictionary:
		var items: Dictionary = items_any
		for item_id_any in items.keys():
			var item_id := str(item_id_any)
			if item_id.is_empty():
				continue
			var count := int(items.get(item_id_any, 0))
			var add := int(round(float(count) * scale))
			if add <= 0:
				continue
			InventoryModel.add_item(item_id, add, "offline")
			items_summary[item_id] = add

	var equip_total := 0
	var equips_any = totals.get("equips", {})
	if equips_any is Dictionary:
		var equips: Dictionary = equips_any
		for template_id_any in equips.keys():
			var template_id := str(template_id_any)
			if template_id.is_empty():
				continue
			var count := int(equips.get(template_id_any, 0))
			var add := int(round(float(count) * scale))
			if add <= 0:
				continue
			for _i in range(add):
				EquipmentModel.add_equip(template_id, "offline")
			equip_total += add

	var loot_times := equip_total
	for item_id_any in items_summary.keys():
		loot_times += int(items_summary.get(item_id_any, 0))
	TaskService.on_loot(loot_times)
	var task_msgs: Array[String] = TaskService.end_batch()

	var item_types := items_summary.size()

	EventBus.add_log(
		"离线结算：%d秒｜击杀%d(精英%d/Boss%d)｜经验+%d｜物品%d类｜装备%d件" %
		[dt, offline_kills, offline_elite, offline_boss, offline_exp, item_types, equip_total]
	)
	if task_msgs.size() > 0:
		EventBus.add_log("离线期间任务/成就达成：%d项（已自动领奖）" % task_msgs.size())

	var summary := {
		"seconds": dt,
		"kills": offline_kills,
		"elite": offline_elite,
		"boss": offline_boss,
		"exp": offline_exp,
		"items": items_summary,
		"equip_count": equip_total,
	}

	last_active_ts = now_ts
	_last_mark_write_ts = now_ts
	save_state()
	PerfTracker.tick(now_ts)
	PerfTracker.maybe_save(now_ts)
	return summary

func load_state() -> void:
	last_active_ts = 0
	_last_mark_write_ts = 0
	if not FileAccess.file_exists(SAVE_PATH):
		return
	var text := FileAccess.get_file_as_string(SAVE_PATH)
	var parsed: Variant = JSON.parse_string(text)
	if not (parsed is Dictionary):
		return
	var data: Dictionary = parsed
	last_active_ts = maxi(0, int(data.get("last_active_ts", 0)))

func save_state() -> void:
	var file := FileAccess.open(SAVE_PATH, FileAccess.WRITE)
	if file == null:
		push_warning("OfflineService: failed to open save file")
		return
	file.store_string(JSON.stringify({
		"last_active_ts": last_active_ts,
	}))

func _now_ts() -> int:
	return int(Time.get_unix_time_from_system())
