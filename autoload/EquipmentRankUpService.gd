extends Node

const RANK_LEVELS := [20, 40, 50, 60]
const RANK_ORDER := {
	20: 1,
	40: 2,
	50: 3,
	60: 4,
}
const INHERIT_KEYS := [
	"blue_affixes",
	"purple_affixes",
	"purple_reforges",
	"reforge_affixes",
	"wash_affixes",
]

func get_next_template_for_equip(equip_input: Variant) -> Dictionary:
	var inst := _resolve_instance(equip_input)
	if inst.is_empty():
		return {}
	if _is_blue_gear_instance(inst):
		return {}
	var template := _get_template_for_inst(inst)
	if template.is_empty():
		return {}

	var explicit_next := str(template.get("next_rank_template_id", "")).strip_edges()
	if not explicit_next.is_empty():
		return EquipmentModel.get_template(explicit_next)

	var series_id := str(template.get("rank_series_id", "")).strip_edges()
	var quality_tier := str(template.get("quality_tier", "normal")).strip_edges().to_lower()
	var current_level := int(template.get("rank_stage", template.get("required_level", 0)))
	var target_level := _next_rank_level(current_level)
	if target_level <= 0:
		return {}

	var candidates := _all_templates()
	for row_any in candidates:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		if str(row.get("id", "")).strip_edges() == str(template.get("id", "")).strip_edges():
			continue
		if not series_id.is_empty() and str(row.get("rank_series_id", "")).strip_edges() == series_id:
			if int(row.get("rank_stage", row.get("required_level", 0))) == target_level and str(row.get("quality_tier", "normal")).strip_edges().to_lower() == quality_tier:
				return row

	# Fallback only when config is sparse but match is still unambiguous.
	var fallback: Array[Dictionary] = []
	for row_any in candidates:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		if int(row.get("required_level", 0)) != target_level:
			continue
		if str(row.get("slot", "")).strip_edges() != str(template.get("slot", "")).strip_edges():
			continue
		if str(row.get("equip_type", "")).strip_edges() != str(template.get("equip_type", "")).strip_edges():
			continue
		if str(row.get("quality_tier", "normal")).strip_edges().to_lower() != quality_tier:
			continue
		if str(row.get("flow_tag", "")).strip_edges() != str(template.get("flow_tag", "")).strip_edges():
			continue
		if str(row.get("set_line_id", "")).strip_edges() != str(template.get("set_line_id", "")).strip_edges():
			continue
		fallback.append(row)
	if fallback.size() == 1:
		return fallback[0]
	return {}

func get_rank_up_recipe(equip_input: Variant, target_template: Dictionary = {}) -> Dictionary:
	var inst := _resolve_instance(equip_input)
	if inst.is_empty():
		return {}
	var current_template := _get_template_for_inst(inst)
	if current_template.is_empty():
		return {}
	var target := target_template
	if target.is_empty():
		target = get_next_template_for_equip(inst)
	if target.is_empty():
		return {}

	var recipes := _all_rank_up_recipes()
	var current_template_id := str(current_template.get("id", "")).strip_edges()
	var target_template_id := str(target.get("id", "")).strip_edges()
	var current_series_id := str(current_template.get("rank_series_id", "")).strip_edges()
	for row_any in recipes:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		if str(row.get("output_id", "")).strip_edges() != target_template_id:
			continue
		var input_template_id := str(row.get("input_template_id", "")).strip_edges()
		if not input_template_id.is_empty() and input_template_id != current_template_id:
			continue
		var input_series_id := str(row.get("input_rank_series_id", "")).strip_edges()
		if not input_series_id.is_empty() and not current_series_id.is_empty() and input_series_id != current_series_id:
			continue
		return row
	return {}

func can_rank_up(equip_input: Variant) -> Dictionary:
	var result := {
		"ok": false,
		"reason": "invalid",
		"target_template_id": "",
		"recipe_id": "",
		"required_gold": 0,
		"material_costs": [],
	}
	var inst := _resolve_instance(equip_input)
	if inst.is_empty():
		return result
	if _is_blue_gear_instance(inst):
		result["reason"] = "blue_gear"
		return result

	var template := _get_template_for_inst(inst)
	if template.is_empty():
		result["reason"] = "template_not_found"
		return result

	var target := get_next_template_for_equip(inst)
	if target.is_empty():
		result["reason"] = "no_target"
		return result
	result["target_template_id"] = str(target.get("id", ""))

	var recipe := get_rank_up_recipe(inst, target)
	if recipe.is_empty():
		result["reason"] = "no_recipe"
		return result
	result["recipe_id"] = str(recipe.get("recipe_id", ""))

	var need_level := maxi(int(recipe.get("unlock_level", 0)), int(target.get("required_level", 0)))
	if ProgressModel.level < need_level:
		result["reason"] = "level_low"
		result["required_level"] = need_level
		return result

	var gold_cost := maxi(0, int(recipe.get("cost_gold", 0)))
	result["required_gold"] = gold_cost
	if int(PlayerModel.gold) < gold_cost:
		result["reason"] = "no_gold"
		result["material_costs"] = _build_material_costs(recipe)
		return result

	var costs := _build_material_costs(recipe)
	result["material_costs"] = costs
	for row_any in costs:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		if not bool(row.get("enough", false)):
			result["reason"] = "no_material"
			return result

	result["ok"] = true
	result["reason"] = ""
	return result

func build_rank_up_preview(equip_input: Variant) -> Dictionary:
	var inst := _resolve_instance(equip_input)
	if inst.is_empty():
		return {"ok": false, "reason": "invalid"}
	var template := _get_template_for_inst(inst)
	var target := get_next_template_for_equip(inst)
	var recipe := get_rank_up_recipe(inst, target)
	var can_info := can_rank_up(inst)
	var current_stats := _template_stats_for_instance(inst, template)
	var target_stats := _template_stats_for_target(inst, target)
	return {
		"ok": bool(can_info.get("ok", false)),
		"reason": str(can_info.get("reason", "")),
		"current_equip": inst,
		"current_template": template,
		"target_template": target,
		"current_stats": current_stats,
		"target_stats": target_stats,
		"material_costs": _build_material_costs(recipe),
		"gold_cost": maxi(0, int(recipe.get("cost_gold", 0))),
		"required_level": maxi(int(recipe.get("unlock_level", 0)), int(target.get("required_level", 0))),
		"current_rank_level": int(template.get("rank_stage", template.get("required_level", 0))),
		"target_rank_level": int(target.get("rank_stage", target.get("required_level", 0))),
		"current_rank_order": _rank_order_for_level(int(template.get("rank_stage", template.get("required_level", 0)))),
		"target_rank_order": _rank_order_for_level(int(target.get("rank_stage", target.get("required_level", 0)))),
		"inherit_flags": {
			"star": true,
			"gems": true,
			"blue_affixes": true,
			"purple_affixes": true,
			"locked": true,
			"equipped": true,
		},
	}

func perform_rank_up(equip_input: Variant) -> Dictionary:
	var result := {
		"ok": false,
		"reason": "invalid",
		"uid": _resolve_uid(equip_input),
		"from_template_id": "",
		"to_template_id": "",
	}
	var inst := _resolve_instance(equip_input)
	if inst.is_empty():
		return result
	var uid := int(inst.get("uid", 0))
	var can_info := can_rank_up(inst)
	if not bool(can_info.get("ok", false)):
		result["reason"] = str(can_info.get("reason", "invalid"))
		result["required_level"] = int(can_info.get("required_level", 0))
		return result

	var target := get_next_template_for_equip(inst)
	var recipe := get_rank_up_recipe(inst, target)
	if target.is_empty() or recipe.is_empty():
		result["reason"] = "invalid"
		return result

	var gold_cost := maxi(0, int(recipe.get("cost_gold", 0)))
	var spent_items: Array[Dictionary] = []
	if gold_cost > 0 and not PlayerModel.spend_gold(gold_cost):
		result["reason"] = "no_gold"
		return result
	for row_any in _build_material_costs(recipe):
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		var item_id := str(row.get("item_id", "")).strip_edges()
		var need := maxi(0, int(row.get("need", 0)))
		if item_id.is_empty() or need <= 0:
			continue
		if InventoryModel.consume_item(item_id, need, "system"):
			spent_items.append({"item_id": item_id, "count": need})
			continue
		for spent_any in spent_items:
			if not (spent_any is Dictionary):
				continue
			var spent: Dictionary = spent_any
			InventoryModel.add_item(str(spent.get("item_id", "")), int(spent.get("count", 0)), "system")
		if gold_cost > 0:
			PlayerModel.add_gold(gold_cost, false)
		result["reason"] = "no_material"
		return result

	var current_inst := EquipmentModel._get_instance_by_uid(uid)
	if current_inst.is_empty():
		_refund_rank_costs(spent_items, gold_cost)
		result["reason"] = "not_found"
		return result

	var target_template_id := str(target.get("id", "")).strip_edges()
	var updated := _build_ranked_instance(current_inst, target)
	if updated.is_empty():
		_refund_rank_costs(spent_items, gold_cost)
		result["reason"] = "build_failed"
		return result
	if not EquipmentModel._set_instance_by_uid(uid, updated):
		_refund_rank_costs(spent_items, gold_cost)
		result["reason"] = "save_failed"
		return result

	if has_node("/root/EquipDexModel") and not target_template_id.is_empty():
		EquipDexModel.unlock(target_template_id, false)

	EquipmentModel._request_save()
	EventBus.notify_inventory_updated()
	var old_level := int(_get_template_for_inst(inst).get("rank_stage", _get_template_for_inst(inst).get("required_level", 0)))
	var new_level := int(target.get("rank_stage", target.get("required_level", 0)))
	EventBus.add_log("装备升阶成功：%s %d阶 → %d阶" % [
		str(updated.get("name", target_template_id)),
		_rank_order_for_level(old_level),
		_rank_order_for_level(new_level),
	])

	result["ok"] = true
	result["reason"] = ""
	result["uid"] = uid
	result["from_template_id"] = str(inst.get("template_id", ""))
	result["to_template_id"] = target_template_id
	return result

func _build_ranked_instance(inst: Dictionary, target_template: Dictionary) -> Dictionary:
	if inst.is_empty() or target_template.is_empty():
		return {}
	var out := inst.duplicate(true)
	var new_star_level := mini(int(inst.get("star_level", 0)), int(target_template.get("star_cap", 10)))
	var old_sockets := clampi(int(inst.get("sockets", inst.get("socket_count", 0))), 0, EquipmentModel.MAX_SOCKETS)
	var new_sockets := EquipmentModel._template_socket_count_for_star(target_template, new_star_level)
	var old_gems := EquipmentModel._normalize_socket_gems(inst.get("socket_gems", []), old_sockets)
	var keep_gems: Array[String] = []
	keep_gems.resize(new_sockets)
	for i in range(new_sockets):
		keep_gems[i] = old_gems[i]

	out["template_id"] = str(target_template.get("id", ""))
	out["name"] = str(target_template.get("name", out.get("name", "")))
	out["slot"] = str(target_template.get("slot", out.get("slot", "")))
	out["rarity"] = str(target_template.get("rarity", out.get("rarity", "white")))
	out["icon"] = str(target_template.get("icon", out.get("icon", "")))
	out["set_id"] = str(target_template.get("set_id", ""))
	out["identified"] = bool(inst.get("identified", true))
	out["locked"] = bool(inst.get("locked", false))
	out["star_level"] = new_star_level
	out["sockets"] = new_sockets
	out["socket_gems"] = keep_gems
	if out.has("socket_count"):
		out["socket_count"] = new_sockets
	out["effects"] = []
	if not out.has("extra_effects"):
		out["extra_effects"] = []
	for key in INHERIT_KEYS:
		if inst.has(key):
			out[key] = inst.get(key)
	out = EquipmentModel._normalize_loaded_instance(out)
	return out

func _refund_rank_costs(spent_items: Array[Dictionary], gold_cost: int) -> void:
	for spent_any in spent_items:
		if not (spent_any is Dictionary):
			continue
		var spent: Dictionary = spent_any
		var item_id := str(spent.get("item_id", "")).strip_edges()
		var count := maxi(0, int(spent.get("count", 0)))
		if item_id.is_empty() or count <= 0:
			continue
		InventoryModel.add_item(item_id, count, "system")
	if gold_cost > 0:
		PlayerModel.add_gold(gold_cost, false)

func _build_material_costs(recipe: Dictionary) -> Array[Dictionary]:
	var out: Array[Dictionary] = []
	if recipe.is_empty():
		return out
	var cost_items_any = recipe.get("cost_items", [])
	if not (cost_items_any is Array):
		return out
	for row_any in cost_items_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		var item_id := str(row.get("item_id", "")).strip_edges()
		var need := maxi(0, int(row.get("count", 0)))
		if item_id.is_empty() or need <= 0:
			continue
		var item_def := _find_item_def(item_id)
		var owned := maxi(0, int(InventoryModel.get_count(item_id)))
		out.append({
			"item_id": item_id,
			"name": str(item_def.get("name", item_id)),
			"icon": str(item_def.get("icon", "")),
			"need": need,
			"owned": owned,
			"enough": owned >= need,
		})
	return out

func _template_stats_for_instance(inst: Dictionary, template: Dictionary = {}) -> Dictionary:
	var row := inst.duplicate(true)
	if template.is_empty():
		template = _get_template_for_inst(inst)
	if template.is_empty():
		return {}
	row["template_id"] = str(template.get("id", ""))
	row["star_level"] = int(inst.get("star_level", 0))
	return EquipmentModel.get_instance_template_stats(row)

func _template_stats_for_target(inst: Dictionary, target_template: Dictionary) -> Dictionary:
	if target_template.is_empty():
		return {}
	var shadow := inst.duplicate(true)
	shadow["template_id"] = str(target_template.get("id", ""))
	shadow["star_level"] = mini(int(inst.get("star_level", 0)), int(target_template.get("star_cap", 10)))
	return EquipmentModel.get_instance_template_stats(shadow)

func _next_rank_level(current_level: int) -> int:
	match current_level:
		20:
			return 40
		40:
			return 50
		50:
			return 60
		_:
			return 0

func _rank_order_for_level(level: int) -> int:
	return int(RANK_ORDER.get(level, 0))

func _resolve_uid(equip_input: Variant) -> int:
	if equip_input is int:
		return int(equip_input)
	if equip_input is Dictionary:
		return int((equip_input as Dictionary).get("uid", 0))
	return 0

func _resolve_instance(equip_input: Variant) -> Dictionary:
	if equip_input is Dictionary:
		return (equip_input as Dictionary).duplicate(true)
	var uid := _resolve_uid(equip_input)
	if uid <= 0:
		return {}
	return EquipmentModel.get_instance(uid)

func _get_template_for_inst(inst: Dictionary) -> Dictionary:
	var template_id := str(inst.get("template_id", "")).strip_edges()
	if template_id.is_empty():
		return {}
	return EquipmentModel.get_template(template_id)

func _all_templates() -> Array:
	var cfg: Dictionary = ConfigService.get_cfg()
	var db_any = cfg.get("equip_db", {})
	if not (db_any is Dictionary):
		return []
	var rows_any = (db_any as Dictionary).get("equip_templates", [])
	return rows_any if rows_any is Array else []

func _all_rank_up_recipes() -> Array:
	var cfg: Dictionary = ConfigService.get_cfg()
	var db_any = cfg.get("crafting_recipes_db", {})
	if not (db_any is Dictionary):
		return []
	var rows_any = (db_any as Dictionary).get("crafting_recipes", [])
	if not (rows_any is Array):
		return []
	var out: Array = []
	for row_any in rows_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		if str(row.get("recipe_type", "")).strip_edges().to_lower() == "rank_up":
			out.append(row)
	return out

func _find_item_def(item_id: String) -> Dictionary:
	if item_id.is_empty():
		return {}
	var cfg: Dictionary = ConfigService.get_cfg()
	var db_any = cfg.get("items_db", {})
	if not (db_any is Dictionary):
		return {}
	var rows_any = (db_any as Dictionary).get("items", [])
	if not (rows_any is Array):
		return {}
	for row_any in rows_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		if str(row.get("id", "")).strip_edges() == item_id:
			return row
	return {}

func _is_blue_gear_instance(inst: Dictionary) -> bool:
	var template_id := str(inst.get("template_id", "")).strip_edges()
	if template_id.is_empty():
		return false
	var cfg: Dictionary = ConfigService.get_cfg()
	var db_any = cfg.get("blue_gear_templates_db", {})
	if db_any is Dictionary:
		var rows_any = (db_any as Dictionary).get("blue_gear_templates", [])
		if rows_any is Array:
			for row_any in rows_any:
				if not (row_any is Dictionary):
					continue
				if str((row_any as Dictionary).get("id", "")).strip_edges() == template_id:
					return true
	var rules_any = cfg.get("equipment_growth_rules_db", {})
	if rules_any is Dictionary:
		var growth_any = (rules_any as Dictionary).get("equipment_growth_rules", {})
		if growth_any is Dictionary:
			var blue_rules_any = (growth_any as Dictionary).get("blue_gear_rules", {})
			if blue_rules_any is Dictionary and not bool((blue_rules_any as Dictionary).get("can_rank_up", true)):
				var tpl := _get_template_for_inst(inst)
				if str(tpl.get("equip_type", "")).strip_edges().to_lower() == "blue":
					return true
	return false
