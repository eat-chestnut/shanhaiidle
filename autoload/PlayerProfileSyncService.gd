extends Node

const API_BASE_URL := "http://127.0.0.1:8000/api"
const SYNC_ENDPOINT := "%s/player/profile/sync" % API_BASE_URL
const DEBOUNCE_SECONDS := 1.5
const RETRY_SECONDS := 6.0
const EQUIP_PREVIEW_LIMIT := 12

var _sync_timer: Timer
var _retry_timer: Timer
var _inflight := false
var _queued_after_request := false
var _dirty := false
var _pending_reason := ""
var _last_payload_hash := ""

func _ready() -> void:
	_sync_timer = Timer.new()
	_sync_timer.one_shot = true
	add_child(_sync_timer)
	_sync_timer.timeout.connect(_on_sync_timeout)

	_retry_timer = Timer.new()
	_retry_timer.one_shot = true
	add_child(_retry_timer)
	_retry_timer.timeout.connect(_on_retry_timeout)

	if not EventBus.inventory_updated.is_connected(_on_inventory_related_changed):
		EventBus.inventory_updated.connect(_on_inventory_related_changed)
	if not EventBus.tasks_updated.is_connected(_on_tasks_changed):
		EventBus.tasks_updated.connect(_on_tasks_changed)
	if not EventBus.patrol_updated.is_connected(_on_patrol_changed):
		EventBus.patrol_updated.connect(_on_patrol_changed)
	if not EventBus.profile_sync_requested.is_connected(_on_profile_sync_requested):
		EventBus.profile_sync_requested.connect(_on_profile_sync_requested)

	call_deferred("_schedule_initial_sync")

func request_sync(reason: String = "manual", delay_seconds: float = DEBOUNCE_SECONDS) -> void:
	var clean_reason := reason.strip_edges()
	if clean_reason.is_empty():
		clean_reason = "manual"
	_pending_reason = clean_reason
	_dirty = true
	if _retry_timer != null:
		_retry_timer.stop()
	if _inflight:
		_queued_after_request = true
		return
	if _sync_timer != null:
		_sync_timer.start(maxf(0.1, delay_seconds))

func _schedule_initial_sync() -> void:
	request_sync("startup", 2.0)

func _on_inventory_related_changed() -> void:
	request_sync("inventory")

func _on_tasks_changed() -> void:
	request_sync("tasks")

func _on_patrol_changed() -> void:
	request_sync("patrol")

func _on_profile_sync_requested(reason: String) -> void:
	request_sync(reason)

func _on_sync_timeout() -> void:
	_perform_sync()

func _on_retry_timeout() -> void:
	request_sync("retry", 0.1)

func _perform_sync() -> void:
	if _inflight:
		_queued_after_request = true
		return
	var payload := _build_snapshot_payload()
	if payload.is_empty():
		_sync_log("玩家档案同步已跳过：payload 为空")
		return
	var payload_hash := JSON.stringify(payload)
	if not _dirty and payload_hash == _last_payload_hash:
		return

	_inflight = true
	_dirty = false
	var reason := _pending_reason if not _pending_reason.is_empty() else "manual"
	_pending_reason = ""

	_request_json(SYNC_ENDPOINT, payload, func(result: Dictionary) -> void:
		_inflight = false
		if bool(result.get("ok", false)):
			_last_payload_hash = payload_hash
			_sync_log("玩家档案同步成功：%s｜Lv%d｜%s" % [
				reason,
				int(payload.get("level", 1)),
				str(payload.get("current_stage_id", "—")),
			])
			if _queued_after_request or _dirty:
				_queued_after_request = false
				request_sync("queued", 0.3)
			return
		_dirty = true
		_sync_warn("玩家档案同步失败：%s" % str(result.get("message", result.get("reason", "unknown"))))
		if _retry_timer != null:
			_retry_timer.start(RETRY_SECONDS)
	)

func _build_snapshot_payload() -> Dictionary:
	var player_id := _resolve_player_id()
	if player_id.is_empty():
		return {}

	var highest_stage_id := MapProgressModel.highest_cleared_stage_id()
	var highest_diff := 0
	if not highest_stage_id.is_empty():
		highest_diff = maxi(0, int(MapProgressModel.cleared_stage_diffs.get(highest_stage_id, 0)))

	return {
		"player_id": player_id,
		"nickname": "",
		"level": maxi(1, int(ProgressModel.level)),
		"exp": maxi(0, int(ProgressModel.exp)),
		"gold": maxi(0, int(PlayerModel.gold)),
		"crystal": maxi(0, int(PlayerModel.spirit_stone)),
		"contribution": maxi(0, int(PlayerModel.sect_contribution)),
		"free_attr_points": maxi(0, int(ProgressModel.free_attr_points)),
		"skill_points": maxi(0, int(ProgressModel.skill_points)),
		"current_stage_id": str(GrindModel.stage_id).strip_edges(),
		"current_difficulty": maxi(0, int(GrindModel.diff_index)),
		"highest_cleared_stage_id": highest_stage_id,
		"highest_cleared_difficulty": highest_diff,
		"current_sect_id": str(ProgressModel.current_sect_id).strip_edges(),
		"attrs_json": ProgressModel.attrs.duplicate(true),
		"inventory": InventoryModel.items.duplicate(true),
		"equipment": _build_equipment_snapshot(),
		"claimed_milestones": _claimed_milestone_keys(),
		"patrol_summary": _build_patrol_summary(),
		"task_summary": TaskService.get_summary(),
	}

func _build_equipment_snapshot() -> Dictionary:
	var equipped_rows: Array[Dictionary] = []
	for slot_any in EquipmentModel.equipped.keys():
		var slot_key := str(slot_any).strip_edges()
		if slot_key.is_empty():
			continue
		var inst := EquipmentModel.get_equipped_instance(slot_key)
		if inst.is_empty():
			continue
		equipped_rows.append(_equipment_snapshot_row(inst, true, slot_key))

	var bag_preview_rows: Array[Dictionary] = []
	var bag_rows := EquipmentModel.list_bag_sorted()
	for idx in range(mini(EQUIP_PREVIEW_LIMIT, bag_rows.size())):
		var row_any = bag_rows[idx]
		if not (row_any is Dictionary):
			continue
		bag_preview_rows.append(_equipment_snapshot_row(row_any as Dictionary, false, ""))

	return {
		"equipped": equipped_rows,
		"bag_preview": bag_preview_rows,
		"bag_count": int(EquipmentModel.bag.size()),
		"equipped_count": equipped_rows.size(),
	}

func _equipment_snapshot_row(inst: Dictionary, is_equipped: bool, slot_key: String) -> Dictionary:
	return {
		"template_id": str(inst.get("template_id", "")).strip_edges(),
		"name": str(inst.get("name", "装备")).strip_edges(),
		"slot": str(inst.get("slot", slot_key)).strip_edges(),
		"rarity": str(inst.get("rarity", "")).strip_edges(),
		"star_level": maxi(0, int(inst.get("star_level", 0))),
		"refine_lv": maxi(0, int(inst.get("refine_lv", 0))),
		"equipped": is_equipped,
	}

func _claimed_milestone_keys() -> Array[String]:
	var out: Array[String] = []
	for key_any in ProgressModel.claimed_milestones.keys():
		var milestone_key := str(key_any).strip_edges()
		if milestone_key.is_empty():
			continue
		if not bool(ProgressModel.claimed_milestones.get(key_any, false)):
			continue
		out.append(milestone_key)
	out.sort()
	return out

func _build_patrol_summary() -> Dictionary:
	var patrol := OfflineService.get_patrol_status()
	return {
		"status_text": str(patrol.get("status_text", "")).strip_edges(),
		"route_name": str(patrol.get("route_name", "")).strip_edges(),
		"accumulated_seconds": maxi(0, int(patrol.get("accumulated_seconds", 0))),
		"is_capped": bool(patrol.get("is_capped", false)),
		"exp": maxi(0, int(patrol.get("exp", 0))),
		"has_materials": bool(patrol.get("has_materials", false)),
		"has_rare_drop": bool(patrol.get("has_rare_drop", false)),
	}

func _resolve_player_id() -> String:
	if has_node("/root/ShopService"):
		return str(ShopService.get_player_id()).strip_edges()
	return ""

func _request_json(url: String, payload: Dictionary, on_done: Callable) -> void:
	var req := HTTPRequest.new()
	req.request_completed.connect(_on_request_completed.bind(req, on_done), CONNECT_ONE_SHOT)
	add_child(req)
	var headers := PackedStringArray([
		"Content-Type: application/json",
		"%s: %s" % [_client_api_header_name(), _client_api_token()],
	])
	var err := req.request(url.strip_edges(), headers, HTTPClient.METHOD_POST, JSON.stringify(payload))
	if err != OK:
		if is_instance_valid(req):
			req.queue_free()
		_call_done(on_done, {"ok": false, "reason": "network", "message": "请求启动失败（%d）" % err})

func _on_request_completed(result: int, response_code: int, _headers: PackedStringArray, body: PackedByteArray, req: HTTPRequest, on_done: Callable) -> void:
	if is_instance_valid(req):
		req.queue_free()
	if result != HTTPRequest.RESULT_SUCCESS:
		_call_done(on_done, {"ok": false, "reason": "network", "message": "网络错误（%d）" % result})
		return
	var text := body.get_string_from_utf8()
	var parsed_any: Variant = JSON.parse_string(text)
	var parsed: Dictionary = parsed_any if parsed_any is Dictionary else {}
	if response_code >= 200 and response_code < 300:
		var out := parsed.duplicate(true)
		out["ok"] = bool(parsed.get("success", false))
		_call_done(on_done, out)
		return
	_call_done(on_done, {
		"ok": false,
		"reason": str(parsed.get("reason", "server_rejected")),
		"message": str(parsed.get("detail", parsed.get("reason", "HTTP %d" % response_code))),
	})

func _call_done(on_done: Callable, payload: Dictionary) -> void:
	if on_done.is_valid():
		on_done.call(payload)

func _sync_log(text: String) -> void:
	if OS.is_debug_build():
		print("[PlayerProfileSync] %s" % text)

func _sync_warn(text: String) -> void:
	if OS.is_debug_build():
		push_warning("[PlayerProfileSync] %s" % text)

func _client_api_header_name() -> String:
	return str(ProjectSettings.get_setting("application/config/client_api_header_name", "X-Client-Token")).strip_edges()

func _client_api_token() -> String:
	return str(ProjectSettings.get_setting("application/config/client_api_token", "dev-client-token")).strip_edges()
