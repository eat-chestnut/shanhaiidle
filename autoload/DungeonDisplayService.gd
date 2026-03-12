extends Node

signal data_changed()

const SAVE_SECTION := "dungeon_runs_v2"
const TAB_NORMAL := "normal"
const TABS := [TAB_NORMAL]

const BANNER_BY_DUNGEON := {
	"daily_gold": "res://assets/ui/dungeons/banners/dungeon_craft_banner.png",
	"daily_exp": "res://assets/ui/dungeons/banners/dungeon_star_banner.png",
	"daily_material": "res://assets/ui/dungeons/banners/dungeon_blueprint_banner.png",
	"daily_gem": "res://assets/ui/dungeons/banners/dungeon_gem_banner.png",
}

var _cache_dirty := true
var _rows_by_tab: Dictionary = {TAB_NORMAL: []}
var _rows_by_id: Dictionary = {}
var _item_name_map: Dictionary = {}
var _drop_groups_by_id: Dictionary = {}

var _state_loaded := false
var _state: Dictionary = {
	"date": "",
	"used": {},
	"cleared": {},
	"levels": {},
}

func refresh() -> void:
	_cache_dirty = true
	_ensure_cache()
	emit_signal("data_changed")

func get_tabs() -> Array[String]:
	return TABS.duplicate()

func get_rows(tab: String = TAB_NORMAL) -> Array[Dictionary]:
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
		return {
			"ok": false,
			"reason": "locked",
			"unlock_level": int(row.get("unlock_level", 0)),
			"unlock_stage_name": str(row.get("unlock_stage_name", "")),
			"unlock_hint": str(row.get("unlock_hint", "")),
		}
	if not bool(row.get("can_challenge", false)):
		if not bool(row.get("has_stamina", true)):
			return {"ok": false, "reason": "no_stamina", "stamina_cost": int(row.get("stamina_cost", 0))}
		return {"ok": false, "reason": "no_count"}
	if not PlayerModel.spend_stamina(int(row.get("stamina_cost", 0))):
		return {"ok": false, "reason": "no_stamina", "stamina_cost": int(row.get("stamina_cost", 0))}

	_consume_run(dungeon_id)
	var first_clear := not bool(row.get("is_cleared", false))
	if first_clear:
		_mark_cleared(dungeon_id)
	TaskService.on_spend_stamina(int(row.get("stamina_cost", 0)))
	TaskService.on_material_dungeon_challenged(dungeon_id)
	var grant := _grant_dungeon_rewards(row, first_clear)

	_cache_dirty = true
	_ensure_cache()
	emit_signal("data_changed")
	row = get_row(dungeon_id)

	return {
		"ok": true,
		"reason": "",
		"dungeon_id": dungeon_id,
		"name": str(row.get("title", dungeon_id)),
		"reward_lines": grant.get("lines", []),
		"reward_preview": row.get("reward_preview", []),
		"current_level": int(row.get("current_level", 1)),
	}

func sweep(dungeon_id: String) -> Dictionary:
	_ensure_cache()
	var row := get_row(dungeon_id)
	if row.is_empty():
		return {"ok": false, "reason": "not_found"}
	if not bool(row.get("is_unlocked", false)):
		return {
			"ok": false,
			"reason": "locked",
			"unlock_level": int(row.get("unlock_level", 0)),
			"unlock_stage_name": str(row.get("unlock_stage_name", "")),
			"unlock_hint": str(row.get("unlock_hint", "")),
		}
	if not bool(row.get("is_sweep_available", false)):
		if not bool(row.get("is_cleared", false)):
			return {"ok": false, "reason": "need_clear"}
		if not bool(row.get("has_stamina", true)):
			return {"ok": false, "reason": "no_stamina", "stamina_cost": int(row.get("stamina_cost", 0))}
		return {"ok": false, "reason": "no_count"}
	if not PlayerModel.spend_stamina(int(row.get("stamina_cost", 0))):
		return {"ok": false, "reason": "no_stamina", "stamina_cost": int(row.get("stamina_cost", 0))}

	_consume_run(dungeon_id)
	TaskService.on_spend_stamina(int(row.get("stamina_cost", 0)))
	TaskService.on_material_dungeon_challenged(dungeon_id)
	var grant := _grant_dungeon_rewards(row, false)

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
		"reward_lines": grant.get("lines", []),
		"current_level": int(row.get("current_level", 1)),
	}

func plus_action(dungeon_id: String) -> Dictionary:
	_ensure_cache()
	var row := get_row(dungeon_id)
	if row.is_empty():
		return {"ok": false, "reason": "not_found"}
	if not bool(row.get("is_unlocked", false)):
		return {"ok": false, "reason": "locked"}
	if not bool(row.get("can_upgrade", false)):
		return {"ok": false, "reason": "max_level", "name": str(row.get("title", dungeon_id))}

	var next_cfg_any = row.get("next_level_cfg", {})
	var next_cfg: Dictionary = next_cfg_any if next_cfg_any is Dictionary else {}
	var costs_any = next_cfg.get("upgrade_costs", [])
	var costs: Array = costs_any if costs_any is Array else []
	for cost_any in costs:
		if not (cost_any is Dictionary):
			continue
		var cost: Dictionary = cost_any
		var item_id := str(cost.get("item_id", "")).strip_edges()
		var need := maxi(1, int(cost.get("count", 1)))
		if InventoryModel.get_count(item_id) < need:
			return {
				"ok": false,
				"reason": "no_material",
				"name": str(row.get("title", dungeon_id)),
				"item_name": str(_item_name_map.get(item_id, item_id)),
				"need": need,
			}
	for cost_any in costs:
		if not (cost_any is Dictionary):
			continue
		var cost: Dictionary = cost_any
		InventoryModel.spend_item(str(cost.get("item_id", "")), maxi(1, int(cost.get("count", 1))), "dungeon_upgrade")

	_set_dungeon_level(dungeon_id, int(next_cfg.get("level", int(row.get("current_level", 1)))))
	TaskService.on_dungeon_upgraded(dungeon_id)
	_cache_dirty = true
	_ensure_cache()
	emit_signal("data_changed")
	row = get_row(dungeon_id)
	return {
		"ok": true,
		"reason": "",
		"dungeon_id": dungeon_id,
		"name": str(row.get("title", dungeon_id)),
		"current_level": int(row.get("current_level", 1)),
	}

func get_empty_hint(_tab: String = TAB_NORMAL) -> String:
	return "暂无可用日常副本"

func _ensure_cache() -> void:
	_ensure_state()
	if not _cache_dirty:
		return
	_cache_dirty = false
	_rows_by_tab = {TAB_NORMAL: []}
	_rows_by_id.clear()
	_rebuild_item_name_map()
	_rebuild_drop_group_map()

	var db := ConfigService.get_daily_dungeons_db()
	var rows_any = db.get("material_dungeons", [])
	if not (rows_any is Array):
		return

	for row_any in (rows_any as Array):
		if not (row_any is Dictionary):
			continue
		var vm := _build_row_vm(row_any as Dictionary)
		if vm.is_empty():
			continue
		(_rows_by_tab[TAB_NORMAL] as Array).append(vm)
		_rows_by_id[str(vm.get("dungeon_id", ""))] = vm

	(_rows_by_tab[TAB_NORMAL] as Array).sort_custom(_sort_rows)

func _build_row_vm(row: Dictionary) -> Dictionary:
	var dungeon_id := str(row.get("dungeon_id", row.get("id", ""))).strip_edges()
	if dungeon_id.is_empty():
		return {}
	var unlock_level := maxi(1, int(row.get("unlock_level", 1)))
	var unlock_stage_id := str(row.get("unlock_stage_id", "")).strip_edges()
	var unlock_stage_name := str(row.get("unlock_stage_name", unlock_stage_id)).strip_edges()
	if unlock_stage_name.is_empty() and not unlock_stage_id.is_empty():
		unlock_stage_name = unlock_stage_id
	var stage_ready := unlock_stage_id.is_empty() or MapProgressModel.is_stage_cleared(unlock_stage_id)
	var level_ready := ProgressModel.level >= unlock_level
	var daily_limit := maxi(0, int(row.get("daily_limit", 0)))
	var remaining := _remaining_count(dungeon_id, daily_limit)
	var is_unlocked := stage_ready and level_ready
	var is_cleared := bool((_state.get("cleared", {}) as Dictionary).get(dungeon_id, false))
	var stamina_cost := maxi(0, int(row.get("stamina_cost", 0)))
	var has_stamina := PlayerModel.can_spend_stamina(stamina_cost)
	var can_challenge := is_unlocked and has_stamina and (daily_limit <= 0 or remaining > 0)
	var can_sweep := is_unlocked and is_cleared and has_stamina and (daily_limit <= 0 or remaining > 0)
	var level_cfgs := _normalize_level_configs(row.get("level_configs", []))
	var current_level := _current_dungeon_level(dungeon_id, level_cfgs.size())
	var current_level_cfg := _find_level_cfg(level_cfgs, current_level)
	var next_level_cfg := _find_level_cfg(level_cfgs, current_level + 1)
	var layer_rules := _normalize_layer_rules(row.get("layer_rules", []))
	var recommended_power := 0
	for rule_any in layer_rules:
		if rule_any is Dictionary:
			recommended_power = maxi(recommended_power, maxi(0, int((rule_any as Dictionary).get("recommended_power", 0))))
	if recommended_power <= 0:
		recommended_power = unlock_level * 120
	var reward_ids := _normalize_str_array(row.get("display_rewards", []))
	var reward_preview := _normalize_reward_preview(reward_ids)
	var reward_multiplier := float(current_level_cfg.get("reward_multiplier", 1.0))
	var title := str(row.get("name", dungeon_id)).strip_edges()
	var desc := str(row.get("description", "")).strip_edges()
	var banner := _banner_path(dungeon_id)
	var sort_order := int(row.get("sort_order", 0))
	var max_level := maxi(1, level_cfgs.size())
	var upgrade_cost_summary := _upgrade_cost_summary(next_level_cfg)
	var unlock_hint := _build_unlock_hint(unlock_level, unlock_stage_name, stage_ready, level_ready)

	if reward_multiplier > 1.0:
		desc = "%s 当前产出×%.2f" % [desc, reward_multiplier]

	return {
		"dungeon_id": dungeon_id,
		"tab": TAB_NORMAL,
		"title": title,
		"desc": desc,
		"banner_image": banner,
		"unlock_level": unlock_level,
		"unlock_stage_id": unlock_stage_id,
		"unlock_stage_name": unlock_stage_name,
		"unlock_hint": unlock_hint,
		"recommended_power": recommended_power,
		"stamina_cost": stamina_cost,
		"daily_limit": daily_limit,
		"remaining_count": remaining,
		"is_unlocked": is_unlocked,
		"has_stamina": has_stamina,
		"can_challenge": can_challenge,
		"is_sweep_available": can_sweep,
		"is_cleared": is_cleared,
		"show_plus_button": max_level > 1,
		"layer_rules": layer_rules,
		"level_configs": level_cfgs,
		"current_level": current_level,
		"max_level": max_level,
		"reward_multiplier": reward_multiplier,
		"can_upgrade": current_level < max_level,
		"next_level_cfg": next_level_cfg,
		"upgrade_cost_summary": upgrade_cost_summary,
		"reward_ids": reward_ids,
		"reward_preview": reward_preview,
		"route_type": "material_dungeon",
		"route_target": dungeon_id,
		"dungeon_type": str(row.get("dungeon_type", "")).strip_edges().to_lower(),
		"sort_order": sort_order,
	}

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

func _banner_path(dungeon_id: String) -> String:
	return str(BANNER_BY_DUNGEON.get(dungeon_id, ""))

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

func _normalize_level_configs(arr_any: Variant) -> Array[Dictionary]:
	var out: Array[Dictionary] = []
	if not (arr_any is Array):
		return out
	for row_any in (arr_any as Array):
		if row_any is Dictionary:
			out.append((row_any as Dictionary).duplicate(true))
	return out

func _normalize_layer_rules(arr_any: Variant) -> Array[Dictionary]:
	var out: Array[Dictionary] = []
	if not (arr_any is Array):
		return out
	for row_any in (arr_any as Array):
		if row_any is Dictionary:
			out.append((row_any as Dictionary).duplicate(true))
	return out

func _find_level_cfg(level_cfgs: Array[Dictionary], level: int) -> Dictionary:
	for row in level_cfgs:
		if int(row.get("level", 0)) == level:
			return row.duplicate(true)
	return {}

func _current_dungeon_level(dungeon_id: String, max_level: int) -> int:
	var levels_any = _state.get("levels", {})
	var levels: Dictionary = levels_any if levels_any is Dictionary else {}
	return clampi(maxi(1, int(levels.get(dungeon_id, 1))), 1, maxi(1, max_level))

func _set_dungeon_level(dungeon_id: String, level: int) -> void:
	var levels_any = _state.get("levels", {})
	var levels: Dictionary = levels_any if levels_any is Dictionary else {}
	levels[dungeon_id] = maxi(1, level)
	_state["levels"] = levels
	_save_state()

func _upgrade_cost_summary(level_cfg: Dictionary) -> String:
	if level_cfg.is_empty():
		return "已满级"
	var costs_any = level_cfg.get("upgrade_costs", [])
	if not (costs_any is Array) or (costs_any as Array).is_empty():
		return "无需材料"
	var parts: Array[String] = []
	for cost_any in (costs_any as Array):
		if not (cost_any is Dictionary):
			continue
		var cost: Dictionary = cost_any
		var item_id := str(cost.get("item_id", "")).strip_edges()
		var item_name := str(_item_name_map.get(item_id, item_id))
		var count := maxi(1, int(cost.get("count", 1)))
		parts.append("%s×%d" % [item_name, count])
	if parts.is_empty():
		return "无需材料"
	return "升下一级：%s" % "、".join(parts)

func _build_unlock_hint(unlock_level: int, unlock_stage_name: String, stage_ready: bool, level_ready: bool) -> String:
	var stage_name := unlock_stage_name.strip_edges()
	if not stage_name.is_empty():
		if not stage_ready and not level_ready:
			return "需通关%s，并达到Lv%d" % [stage_name, unlock_level]
		if not stage_ready:
			return "需先通关%s" % stage_name
		if not level_ready:
			return "需达到Lv%d" % unlock_level
		return "已解锁"
	if not level_ready:
		return "需达到Lv%d" % unlock_level
	return "已解锁"

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
				_item_name_map[item_id] = str(row.get("name", item_id)).strip_edges()
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
				_item_name_map[item_id] = str(row.get("name", item_id)).strip_edges()

func _rebuild_drop_group_map() -> void:
	_drop_groups_by_id.clear()
	var rows_any = ConfigService.get_daily_dungeons_db().get("material_dungeon_drop_groups", [])
	if not (rows_any is Array):
		return
	for row_any in (rows_any as Array):
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		var group_id := str(row.get("group_id", "")).strip_edges()
		if group_id.is_empty():
			continue
		_drop_groups_by_id[group_id] = row.duplicate(true)

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

func _grant_dungeon_rewards(row: Dictionary, first_clear: bool) -> Dictionary:
	var lines: Array[String] = []
	var multiplier := float(row.get("reward_multiplier", 1.0))
	var layer_rules_any = row.get("layer_rules", [])
	var layer_rules: Array = layer_rules_any if layer_rules_any is Array else []
	if layer_rules.is_empty():
		return {"lines": lines}
	var primary_rule_any = layer_rules[0]
	if primary_rule_any is Dictionary:
		_grant_group_rewards(str((primary_rule_any as Dictionary).get("drop_group_id", "")), multiplier, lines)
		if first_clear:
			var first_clear_group_id := str((primary_rule_any as Dictionary).get("first_clear_reward_group_id", "")).strip_edges()
			if not first_clear_group_id.is_empty():
				_grant_group_rewards(first_clear_group_id, 1.0, lines)
	return {"lines": lines}

func _grant_group_rewards(group_id: String, multiplier: float, lines: Array[String]) -> void:
	var group_any = _drop_groups_by_id.get(group_id, {})
	if not (group_any is Dictionary):
		return
	var rewards_any = (group_any as Dictionary).get("rewards", [])
	if not (rewards_any is Array):
		return
	for reward_any in (rewards_any as Array):
		if not (reward_any is Dictionary):
			continue
		var reward: Dictionary = reward_any
		var probability := clampf(float(reward.get("probability", 1.0)), 0.0, 1.0)
		if probability <= 0.0 or randf() > probability:
			continue
		var item_id := str(reward.get("item_id", "")).strip_edges()
		if item_id.is_empty():
			continue
		var count_min := maxi(1, int(reward.get("count_min", 1)))
		var count_max := maxi(count_min, int(reward.get("count_max", count_min)))
		var count := count_min if count_min >= count_max else randi_range(count_min, count_max)
		count = maxi(1, int(round(float(count) * multiplier)))
		_grant_reward_by_item_id(item_id, count, lines)

func _grant_reward_by_item_id(item_id: String, count: int, lines: Array[String]) -> void:
	var reward_count := maxi(1, count)
	match item_id:
		"金币":
			PlayerModel.add_gold(reward_count)
		"角色经验":
			InventoryModel.add_item(item_id, reward_count, "online")
		"灵石":
			PlayerModel.add_spirit_stone(reward_count)
		_:
			InventoryModel.add_item(item_id, reward_count, "online")
	var label := str(_item_name_map.get(item_id, item_id))
	lines.append("%s×%d" % [label, reward_count])

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
	if not (_state.get("levels", {}) is Dictionary):
		_state["levels"] = {}
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
