extends Node

signal data_changed()

const SAVE_SECTION := "dungeon_runs_v1"
const TAB_NORMAL := "normal"
const TAB_ELITE := "elite"
const TAB_EVENT := "event"
const TABS := [TAB_ELITE, TAB_NORMAL, TAB_EVENT]

const BANNER_BY_ROUTE := {
	"dungeon_craft": "res://assets/ui/dungeons/banners/dungeon_craft_banner.png",
	"dungeon_star": "res://assets/ui/dungeons/banners/dungeon_star_banner.png",
	"dungeon_blueprint": "res://assets/ui/dungeons/banners/dungeon_blueprint_banner.png",
	"dungeon_boss_mat": "res://assets/ui/dungeons/banners/dungeon_boss_mat_banner.png",
	"dungeon_gem": "res://assets/ui/dungeons/banners/dungeon_gem_banner.png",
	"dungeon_refine": "res://assets/ui/dungeons/banners/dungeon_refine_banner.png",
}

var _cache_dirty := true
var _rows_by_tab: Dictionary = {
	TAB_ELITE: [],
	TAB_NORMAL: [],
	TAB_EVENT: [],
}
var _rows_by_id: Dictionary = {}
var _item_name_map: Dictionary = {}

var _state_loaded := false
var _state: Dictionary = {
	"date": "",
	"used": {},
	"cleared": {},
}

func refresh() -> void:
	_cache_dirty = true
	_ensure_cache()
	emit_signal("data_changed")

func get_tabs() -> Array[String]:
	return TABS.duplicate()

func get_rows(tab: String) -> Array[Dictionary]:
	_ensure_cache()
	var arr_any = _rows_by_tab.get(tab, [])
	if not (arr_any is Array):
		return []
	var out: Array[Dictionary] = []
	for row_any in (arr_any as Array):
		if row_any is Dictionary:
			out.append((row_any as Dictionary).duplicate(true))
	return out

func get_row(dungeon_id: String) -> Dictionary:
	_ensure_cache()
	var row_any = _rows_by_id.get(dungeon_id, {})
	if row_any is Dictionary:
		return (row_any as Dictionary).duplicate(true)
	return {}

func challenge(dungeon_id: String) -> Dictionary:
	_ensure_cache()
	var row := get_row(dungeon_id)
	if row.is_empty():
		return {"ok": false, "reason": "not_found"}
	if not bool(row.get("is_unlocked", false)):
		return {"ok": false, "reason": "locked", "unlock_level": int(row.get("unlock_level", 0))}
	if not bool(row.get("can_challenge", false)):
		return {"ok": false, "reason": "no_count"}
	_consume_run(dungeon_id)
	_mark_cleared(dungeon_id)
	_cache_dirty = true
	_ensure_cache()
	emit_signal("data_changed")
	row = get_row(dungeon_id)
	return {
		"ok": true,
		"reason": "",
		"dungeon_id": dungeon_id,
		"name": str(row.get("title", dungeon_id)),
		"route_type": str(row.get("route_type", "material_dungeon")),
		"route_target": str(row.get("route_target", dungeon_id)),
	}

func sweep(dungeon_id: String) -> Dictionary:
	_ensure_cache()
	var row := get_row(dungeon_id)
	if row.is_empty():
		return {"ok": false, "reason": "not_found"}
	if not bool(row.get("is_unlocked", false)):
		return {"ok": false, "reason": "locked", "unlock_level": int(row.get("unlock_level", 0))}
	if not bool(row.get("is_sweep_available", false)):
		if not bool(row.get("is_cleared", false)):
			return {"ok": false, "reason": "need_clear"}
		return {"ok": false, "reason": "no_count"}
	_consume_run(dungeon_id)
	_cache_dirty = true
	_ensure_cache()
	emit_signal("data_changed")
	row = get_row(dungeon_id)
	return {
		"ok": true,
		"reason": "",
		"dungeon_id": dungeon_id,
		"name": str(row.get("title", dungeon_id)),
		"reward_preview": row.get("reward_preview", []),
	}

func plus_action(dungeon_id: String) -> Dictionary:
	_ensure_cache()
	var row := get_row(dungeon_id)
	if row.is_empty():
		return {"ok": false, "reason": "not_found"}
	return {
		"ok": false,
		"reason": "not_open",
		"dungeon_id": dungeon_id,
		"name": str(row.get("title", dungeon_id)),
	}

func get_empty_hint(tab: String) -> String:
	match tab:
		TAB_EVENT:
			return "暂无活动副本"
		TAB_ELITE:
			return "暂无精英副本"
		_:
			return "暂无普通副本"

func _ensure_cache() -> void:
	_ensure_state()
	if not _cache_dirty:
		return
	_cache_dirty = false
	_rows_by_tab = {
		TAB_ELITE: [],
		TAB_NORMAL: [],
		TAB_EVENT: [],
	}
	_rows_by_id.clear()
	_rebuild_item_name_map()

	var cfg := ConfigService.get_cfg()
	var db_any = cfg.get("material_dungeons_db", {})
	if not (db_any is Dictionary):
		return
	var rows_any = (db_any as Dictionary).get("material_dungeons", [])
	if not (rows_any is Array):
		return

	for row_any in (rows_any as Array):
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		var vm := _build_row_vm(row)
		if vm.is_empty():
			continue
		var tab := str(vm.get("tab", TAB_NORMAL))
		if not _rows_by_tab.has(tab):
			_rows_by_tab[tab] = []
		var arr_any = _rows_by_tab.get(tab, [])
		if arr_any is Array:
			(arr_any as Array).append(vm)
		_rows_by_id[str(vm.get("dungeon_id", ""))] = vm

	for tab_key in TABS:
		var arr_any = _rows_by_tab.get(tab_key, [])
		if not (arr_any is Array):
			continue
		(arr_any as Array).sort_custom(_sort_rows)

func _build_row_vm(row: Dictionary) -> Dictionary:
	var dungeon_id := str(row.get("dungeon_id", row.get("id", ""))).strip_edges()
	if dungeon_id.is_empty():
		return {}
	var dungeon_type := str(row.get("dungeon_type", "")).strip_edges().to_lower()
	var tab := _tab_by_type(dungeon_type, dungeon_id)
	var route_target := _route_target(dungeon_type, dungeon_id)
	var unlock_level := maxi(1, int(row.get("unlock_level", 1)))
	var daily_limit := maxi(0, int(row.get("daily_limit", 0)))
	var remaining := _remaining_count(dungeon_id, daily_limit)
	var is_unlocked := ProgressModel.level >= unlock_level
	var is_cleared := bool((_state.get("cleared", {}) as Dictionary).get(dungeon_id, false))
	var can_challenge := is_unlocked and (daily_limit <= 0 or remaining > 0)
	var can_sweep := is_unlocked and is_cleared and (daily_limit <= 0 or remaining > 0)
	var layer_cfg_any = row.get("layer_config", {})
	var layer_cfg: Dictionary = layer_cfg_any if layer_cfg_any is Dictionary else {}
	var max_layer := maxi(1, int(layer_cfg.get("max_layer", 1)))
	var recommended_power := maxi(0, int(row.get("recommended_power", unlock_level * 120 + max_layer * 40)))
	var reward_ids := _normalize_str_array(row.get("drop_pools", []))
	var reward_preview := _normalize_reward_preview(reward_ids)
	var title := str(row.get("name", dungeon_id)).strip_edges()
	var desc := str(row.get("description", "")).strip_edges()
	var banner := _banner_path(row, route_target, dungeon_id)
	var sort_order := int(row.get("sort_order", 0))

	return {
		"dungeon_id": dungeon_id,
		"tab": tab,
		"title": title,
		"desc": desc,
		"banner_image": banner,
		"unlock_level": unlock_level,
		"recommended_power": recommended_power,
		"daily_limit": daily_limit,
		"remaining_count": remaining,
		"is_unlocked": is_unlocked,
		"can_challenge": can_challenge,
		"is_sweep_available": can_sweep,
		"is_cleared": is_cleared,
		"show_plus_button": daily_limit > 0,
		"reward_ids": reward_ids,
		"reward_preview": reward_preview,
		"route_type": "material_dungeon",
		"route_target": route_target,
		"dungeon_type": dungeon_type,
		"sort_order": sort_order,
	}

func _tab_by_type(dungeon_type: String, dungeon_id: String) -> String:
	if dungeon_type == "boss" or dungeon_type == "elite":
		return TAB_ELITE
	if dungeon_type == "event" or dungeon_id.begins_with("event_"):
		return TAB_EVENT
	return TAB_NORMAL

func _route_target(dungeon_type: String, dungeon_id: String) -> String:
	match dungeon_type:
		"craft":
			return "dungeon_craft"
		"star":
			return "dungeon_star"
		"blueprint":
			return "dungeon_blueprint"
		"boss":
			return "dungeon_boss_mat"
		"gem":
			return "dungeon_gem"
		"refine":
			return "dungeon_refine"
		"event":
			return "event_%s" % dungeon_id
		_:
			return dungeon_id

func _banner_path(row: Dictionary, route_target: String, dungeon_id: String) -> String:
	var raw_any: Variant = row.get("banner_image", row.get("banner", ""))
	var raw := str(raw_any).strip_edges()
	if not raw.is_empty():
		return raw
	if BANNER_BY_ROUTE.has(route_target):
		return str(BANNER_BY_ROUTE.get(route_target, ""))
	return "res://assets/ui/dungeons/banners/%s_banner.png" % dungeon_id

func _sort_rows(a_any: Variant, b_any: Variant) -> bool:
	if not (a_any is Dictionary) or not (b_any is Dictionary):
		return false
	var a: Dictionary = a_any
	var b: Dictionary = b_any
	var au := bool(a.get("is_unlocked", false))
	var bu := bool(b.get("is_unlocked", false))
	if au != bu:
		return au and not bu
	var asort := int(a.get("sort_order", 0))
	var bsort := int(b.get("sort_order", 0))
	if asort != bsort:
		return asort < bsort
	return str(a.get("dungeon_id", "")) < str(b.get("dungeon_id", ""))

func _normalize_str_array(arr_any: Variant) -> Array[String]:
	var out: Array[String] = []
	if not (arr_any is Array):
		return out
	for v_any in (arr_any as Array):
		var s := str(v_any).strip_edges()
		if s.is_empty():
			continue
		out.append(s)
	return out

func _normalize_reward_preview(reward_ids: Array[String]) -> Array[String]:
	var out: Array[String] = []
	for item_id in reward_ids:
		var sid := str(item_id).strip_edges()
		if sid.is_empty():
			continue
		out.append(str(_item_name_map.get(sid, sid)))
	return out

func _rebuild_item_name_map() -> void:
	_item_name_map.clear()
	var cfg := ConfigService.get_cfg()
	var materials_any = cfg.get("material_catalog_db", {})
	if materials_any is Dictionary:
		var rows_any = (materials_any as Dictionary).get("material_catalog", [])
		if rows_any is Array:
			for row_any in (rows_any as Array):
				if not (row_any is Dictionary):
					continue
				var row: Dictionary = row_any
				var item_id := str(row.get("id", "")).strip_edges()
				if item_id.is_empty():
					continue
				var name := str(row.get("name", item_id)).strip_edges()
				_item_name_map[item_id] = name if not name.is_empty() else item_id
	var items_any = cfg.get("items_db", {})
	if items_any is Dictionary:
		var list_any = (items_any as Dictionary).get("items", [])
		if list_any is Array:
			for row_any in (list_any as Array):
				if not (row_any is Dictionary):
					continue
				var row: Dictionary = row_any
				var item_id := str(row.get("id", "")).strip_edges()
				if item_id.is_empty():
					continue
				var name := str(row.get("name", item_id)).strip_edges()
				_item_name_map[item_id] = name if not name.is_empty() else item_id

func _remaining_count(dungeon_id: String, daily_limit: int) -> int:
	if daily_limit <= 0:
		return -1
	var used_map_any = _state.get("used", {})
	var used_map: Dictionary = used_map_any if used_map_any is Dictionary else {}
	var used := maxi(0, int(used_map.get(dungeon_id, 0)))
	return maxi(0, daily_limit - used)

func _consume_run(dungeon_id: String) -> void:
	var used_map_any = _state.get("used", {})
	var used_map: Dictionary = used_map_any if used_map_any is Dictionary else {}
	used_map[dungeon_id] = maxi(0, int(used_map.get(dungeon_id, 0))) + 1
	_state["used"] = used_map
	_save_state()

func _mark_cleared(dungeon_id: String) -> void:
	var cleared_any = _state.get("cleared", {})
	var cleared: Dictionary = cleared_any if cleared_any is Dictionary else {}
	cleared[dungeon_id] = true
	_state["cleared"] = cleared
	_save_state()

func _ensure_state() -> void:
	if _state_loaded:
		return
	_state_loaded = true
	var sec := SaveService.get_section(SAVE_SECTION)
	if sec is Dictionary:
		_state = (sec as Dictionary).duplicate(true)

	var changed := false
	if not (_state.get("used", {}) is Dictionary):
		_state["used"] = {}
		changed = true
	if not (_state.get("cleared", {}) is Dictionary):
		_state["cleared"] = {}
		changed = true

	var today := Time.get_date_string_from_system()
	if str(_state.get("date", "")) != today:
		_state["date"] = today
		_state["used"] = {}
		changed = true

	if changed:
		_save_state()

func _save_state() -> void:
	SaveService.set_section(SAVE_SECTION, _state)
