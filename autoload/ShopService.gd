extends Node

signal data_changed()

const API_BASE_URL := "http://127.0.0.1:8000/api"
const SECTION_KEY := "shop_state"
const PLACEHOLDER_ICON := "res://assets/icons/stage_node_placeholder.png"

var _player_id := ""
var _limit_cache: Dictionary = {}

func _ready() -> void:
	_load_state()

func get_rows(shop_type: String) -> Array[Dictionary]:
	var rows_any = ConfigService.get_shop_goods_db().get("shop_goods", [])
	if not (rows_any is Array):
		return []
	var rows: Array[Dictionary] = []
	for row_any in rows_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = (row_any as Dictionary).duplicate(true)
		if not bool(row.get("is_enabled", false)):
			continue
		if str(row.get("shop_type", "")).strip_edges() != shop_type:
			continue
		rows.append(_build_goods_row(row))
	rows.sort_custom(_sort_goods_rows)
	return rows

func get_player_id() -> String:
	_ensure_player_id()
	return _player_id

func purchase(goods_id: String, on_done: Callable) -> void:
	var clean_goods_id := goods_id.strip_edges()
	if clean_goods_id.is_empty():
		_call_done(on_done, {"ok": false, "reason": "invalid_goods"})
		return
	var goods := _find_goods(clean_goods_id)
	if goods.is_empty():
		_call_done(on_done, {"ok": false, "reason": "not_found"})
		return
	var local_state := _resolve_goods_state(goods)
	if str(local_state.get("status_code", "")) == "locked":
		_call_done(on_done, {"ok": false, "reason": "level_locked", "need_level": int(goods.get("unlock_level", 1))})
		return
	if str(local_state.get("status_code", "")) == "daily_sold_out":
		_call_done(on_done, {"ok": false, "reason": "daily_limit_reached"})
		return
	if str(local_state.get("status_code", "")) == "weekly_sold_out":
		_call_done(on_done, {"ok": false, "reason": "weekly_limit_reached"})
		return
	if str(local_state.get("status_code", "")) == "lifetime_sold_out":
		_call_done(on_done, {"ok": false, "reason": "lifetime_limit_reached"})
		return

	_sync_player_profile(func(sync_ret: Dictionary) -> void:
		if not bool(sync_ret.get("ok", false)):
			_call_done(on_done, sync_ret)
			return
		_request_json("%s/shop/purchase" % API_BASE_URL, {
			"player_id": get_player_id(),
			"goods_id": clean_goods_id,
		}, func(result: Dictionary) -> void:
			_handle_purchase_response(clean_goods_id, result)
			_call_done(on_done, result)
		)
	)

func purchase_result_text(result: Dictionary) -> String:
	if bool(result.get("ok", false)):
		var granted_any = result.get("granted_items", [])
		var granted: Array = granted_any if granted_any is Array else []
		if not granted.is_empty() and granted[0] is Dictionary:
			var first: Dictionary = granted[0]
			var item_id := str(first.get("item_id", "")).strip_edges()
			var item_name := _item_name(item_id)
			var count := maxi(1, int(first.get("count", 1)))
			return "兑换成功：%s x%d" % [item_name, count]
		return "兑换成功"
	match str(result.get("reason", "")):
		"level_locked":
			return "尚未开放：需达到 Lv%d" % int(result.get("need_level", 1))
		"daily_limit_reached":
			return "今日已售罄"
		"weekly_limit_reached":
			return "本周已售罄"
		"lifetime_limit_reached":
			return "终身已售罄"
		"insufficient_currency":
			return "货币不足"
		"network":
			return "商城请求失败：%s" % str(result.get("message", "网络异常"))
		"server_rejected":
			return "购买失败：%s" % str(result.get("message", "服务端拒绝"))
		_:
			return "购买失败"

func _build_goods_row(row: Dictionary) -> Dictionary:
	var out := row.duplicate(true)
	out["reward_item_name"] = str(out.get("reward_item_name", _item_name(str(out.get("reward_item_id", "")))))
	out["reward_icon"] = _goods_icon_path(out)
	out["limit_text"] = _limit_text(out)
	var state := _resolve_goods_state(out)
	for key_any in state.keys():
		out[str(key_any)] = state.get(key_any)
	return out

func _resolve_goods_state(goods: Dictionary) -> Dictionary:
	var unlock_level := maxi(1, int(goods.get("unlock_level", 1)))
	var remaining := _effective_remaining_limits(goods)
	var currency_type := str(goods.get("cost_currency_type", "gold")).strip_edges()
	var cost_amount := maxi(0, int(goods.get("cost_amount", 0)))
	var current_level := maxi(1, int(ProgressModel.level))
	var state_code := "can_buy"
	var state_text := "可购买"
	if current_level < unlock_level:
		state_code = "locked"
		state_text = "Lv%d 开放" % unlock_level
	elif remaining.get("daily_remaining", null) == 0:
		state_code = "daily_sold_out"
		state_text = "今日已售罄"
	elif remaining.get("weekly_remaining", null) == 0:
		state_code = "weekly_sold_out"
		state_text = "本周已售罄"
	elif remaining.get("lifetime_remaining", null) == 0:
		state_code = "lifetime_sold_out"
		state_text = "终身已售罄"
	elif PlayerModel.get_currency_amount(currency_type) < cost_amount:
		state_code = "no_currency"
		state_text = "货币不足"
	return {
		"is_unlocked": current_level >= unlock_level,
		"status_code": state_code,
		"status_text": state_text,
		"remaining_limits": remaining,
		"can_buy": state_code == "can_buy",
	}

func _effective_remaining_limits(goods: Dictionary) -> Dictionary:
	var goods_id := str(goods.get("goods_id", "")).strip_edges()
	var cache_any = _limit_cache.get(goods_id, {})
	var cache: Dictionary = cache_any if cache_any is Dictionary else {}
	var today_bucket := _today_bucket()
	var week_bucket := _week_bucket()
	return {
		"daily_limit": goods.get("daily_limit", null),
		"daily_remaining": _remaining_value(cache, goods, "daily", today_bucket),
		"weekly_limit": goods.get("weekly_limit", null),
		"weekly_remaining": _remaining_value(cache, goods, "weekly", week_bucket),
		"lifetime_limit": goods.get("lifetime_limit", null),
		"lifetime_remaining": _remaining_value(cache, goods, "lifetime", ""),
	}

func _remaining_value(cache: Dictionary, goods: Dictionary, scope: String, bucket_value: String) -> Variant:
	var limit_key := "%s_limit" % scope
	var remaining_key := "%s_remaining" % scope
	var configured: Variant = goods.get(limit_key, null)
	if configured == null:
		return null
	if scope == "lifetime":
		if cache.has(remaining_key):
			return maxi(0, int(cache.get(remaining_key, int(configured))))
		return int(configured)
	var bucket_key := "%s_bucket" % scope
	var cached_bucket := str(cache.get(bucket_key, "")).strip_edges()
	if cached_bucket == bucket_value and cache.has(remaining_key):
		return maxi(0, int(cache.get(remaining_key, int(configured))))
	return int(configured)

func _limit_text(goods: Dictionary) -> String:
	var lines: Array[String] = []
	var remaining_any = _effective_remaining_limits(goods)
	var remaining: Dictionary = remaining_any if remaining_any is Dictionary else {}
	for scope in ["daily", "weekly", "lifetime"]:
		var limit_key := "%s_limit" % scope
		var limit_any: Variant = goods.get(limit_key, null)
		if limit_any == null:
			continue
		var limit_value := int(limit_any)
		var label := ""
		match scope:
			"daily":
				label = "每日限购 %d 次" % limit_value
			"weekly":
				label = "每周限购 %d 次" % limit_value
			"lifetime":
				label = "终身限购 %d 次" % limit_value
		var remaining_key := "%s_remaining" % scope
		if remaining.has(remaining_key) and remaining.get(remaining_key, null) != null:
			label += "（剩 %d）" % int(remaining.get(remaining_key, limit_value))
		lines.append(label)
	if lines.is_empty():
		return "不限购"
	return "｜".join(lines)

func _goods_icon_path(goods: Dictionary) -> String:
	var icon_path := str(goods.get("icon", "")).strip_edges()
	if not icon_path.is_empty() and ResourceLoader.exists(icon_path):
		return icon_path
	var reward_item_id := str(goods.get("reward_item_id", "")).strip_edges()
	var item_def := _item_def(reward_item_id)
	var item_icon := str(item_def.get("icon", "")).strip_edges()
	if not item_icon.is_empty() and ResourceLoader.exists(item_icon):
		return item_icon
	return PLACEHOLDER_ICON

func _item_name(item_id: String) -> String:
	var item_def := _item_def(item_id)
	return str(item_def.get("name", item_id if not item_id.is_empty() else "奖励物品"))

func _item_def(item_id: String) -> Dictionary:
	if item_id.is_empty():
		return {}
	var items_any = ConfigService.get_cfg().get("items_db", {})
	if not (items_any is Dictionary):
		return {}
	var rows_any = (items_any as Dictionary).get("items", [])
	if not (rows_any is Array):
		return {}
	for row_any in rows_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		if str(row.get("id", "")).strip_edges() == item_id:
			return row
	return {}

func _find_goods(goods_id: String) -> Dictionary:
	var rows_any = ConfigService.get_shop_goods_db().get("shop_goods", [])
	if not (rows_any is Array):
		return {}
	for row_any in rows_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		if str(row.get("goods_id", "")).strip_edges() == goods_id and bool(row.get("is_enabled", false)):
			return row
	return {}

func _sync_player_profile(on_done: Callable) -> void:
	_request_json("%s/shop/profile/sync" % API_BASE_URL, {
		"player_id": get_player_id(),
		"level": maxi(1, int(ProgressModel.level)),
		"gold": maxi(0, int(PlayerModel.gold)),
		"crystal": maxi(0, int(PlayerModel.spirit_stone)),
		"contribution": maxi(0, int(PlayerModel.sect_contribution)),
		"inventory": InventoryModel.items.duplicate(true),
	}, func(result: Dictionary) -> void:
		if not bool(result.get("ok", false)):
			_call_done(on_done, result)
			return
		_call_done(on_done, {"ok": true})
	)

func _handle_purchase_response(goods_id: String, result: Dictionary) -> void:
	var remaining_any: Variant = result.get("remaining_limits", null)
	if remaining_any is Dictionary:
		_update_limit_cache(goods_id, remaining_any)
	if not bool(result.get("ok", false)):
		emit_signal("data_changed")
		return
	PlayerModel.apply_shop_currency_snapshot(result.get("updated_currencies", {}))
	var granted_any = result.get("granted_items", [])
	if granted_any is Array:
		var reward_map: Dictionary = {}
		for row_any in granted_any:
			if not (row_any is Dictionary):
				continue
			var row: Dictionary = row_any
			var item_id := str(row.get("item_id", "")).strip_edges()
			var count := maxi(0, int(row.get("count", 0)))
			if item_id.is_empty() or count <= 0:
				continue
			reward_map[item_id] = int(reward_map.get(item_id, 0)) + count
		if not reward_map.is_empty():
			InventoryModel.add_items_bulk(reward_map, "shop", true)
	EventBus.request_profile_sync("shop_purchase")
	_save_state()
	emit_signal("data_changed")

func _update_limit_cache(goods_id: String, remaining_any: Variant) -> void:
	if goods_id.is_empty() or not (remaining_any is Dictionary):
		return
	var remaining: Dictionary = remaining_any
	_limit_cache[goods_id] = {
		"daily_remaining": remaining.get("daily_remaining", null),
		"weekly_remaining": remaining.get("weekly_remaining", null),
		"lifetime_remaining": remaining.get("lifetime_remaining", null),
		"daily_bucket": _today_bucket(),
		"weekly_bucket": _week_bucket(),
	}
	_save_state()

func _sort_goods_rows(a: Dictionary, b: Dictionary) -> bool:
	var sort_a := maxi(0, int(a.get("sort_order", 0)))
	var sort_b := maxi(0, int(b.get("sort_order", 0)))
	if sort_a != sort_b:
		return sort_a < sort_b
	return str(a.get("goods_id", "")) < str(b.get("goods_id", ""))

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
		if parsed.is_empty():
			_call_done(on_done, {"ok": false, "reason": "server_rejected", "message": "返回内容无效"})
			return
		var out := parsed.duplicate(true)
		out["ok"] = bool(parsed.get("success", false))
		_call_done(on_done, out)
		return
	_call_done(on_done, {
		"ok": false,
		"reason": str(parsed.get("reason", "server_rejected")),
		"message": str(parsed.get("detail", parsed.get("reason", "HTTP %d" % response_code))),
		"remaining_limits": parsed.get("remaining_limits", null),
		"updated_currencies": parsed.get("updated_currencies", null),
	})

func _load_state() -> void:
	var data := SaveService.get_section(SECTION_KEY)
	_player_id = str(data.get("player_id", "")).strip_edges()
	var cache_any = data.get("limit_cache", {})
	_limit_cache = cache_any if cache_any is Dictionary else {}
	_ensure_player_id()

func _save_state() -> void:
	SaveService.set_section(SECTION_KEY, {
		"player_id": _player_id,
		"limit_cache": _limit_cache.duplicate(true),
	})

func _ensure_player_id() -> void:
	if not _player_id.is_empty():
		return
	var seed := OS.get_unique_id().strip_edges()
	if seed.is_empty():
		seed = "%d_%d" % [Time.get_unix_time_from_system(), randi_range(1000, 9999)]
	var clean := seed.replace(":", "_").replace("/", "_").replace("\\", "_").replace(" ", "_")
	_player_id = "local_%s" % clean
	_save_state()

func _today_bucket() -> String:
	var d := Time.get_datetime_dict_from_system()
	return "%04d-%02d-%02d" % [int(d.get("year", 1970)), int(d.get("month", 1)), int(d.get("day", 1))]

func _week_bucket() -> String:
	var d := Time.get_datetime_dict_from_system()
	var year := int(d.get("year", 1970))
	var month := int(d.get("month", 1))
	var day := int(d.get("day", 1))
	var day_of_year := _day_of_year(year, month, day)
	return "%04d-W%02d" % [year, int(floor(float(maxi(0, day_of_year - 1)) / 7.0)) + 1]

func _day_of_year(year: int, month: int, day: int) -> int:
	var month_days := [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31]
	if _is_leap_year(year):
		month_days[1] = 29
	var total := 0
	for idx in range(clampi(month - 1, 0, 11)):
		total += int(month_days[idx])
	return total + maxi(1, day)

func _is_leap_year(year: int) -> bool:
	if year % 400 == 0:
		return true
	if year % 100 == 0:
		return false
	return year % 4 == 0

func _call_done(on_done: Callable, payload: Dictionary) -> void:
	if on_done.is_valid():
		on_done.call(payload)

func _client_api_header_name() -> String:
	return str(ProjectSettings.get_setting("application/config/client_api_header_name", "X-Client-Token")).strip_edges()

func _client_api_token() -> String:
	return str(ProjectSettings.get_setting("application/config/client_api_token", "dev-client-token")).strip_edges()
