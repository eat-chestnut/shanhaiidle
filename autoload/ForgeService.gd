extends Node

const QUALITY_NORMAL := "normal"
const QUALITY_HIGH := "high"
const DEFAULT_SLOT_GROUPS := ["weapon", "armor", "cloak", "accessory"]

func get_normal_forge_candidates() -> Array[Dictionary]:
	var rows: Array[Dictionary] = []
	for tpl_any in _equip_templates():
		if not (tpl_any is Dictionary):
			continue
		var tpl: Dictionary = tpl_any
		if not _is_template_forge_enabled(tpl):
			continue
		if _template_quality_tier(tpl) != QUALITY_NORMAL:
			continue
		var template_id := str(tpl.get("id", "")).strip_edges()
		if template_id.is_empty():
			continue
		var check := can_forge_normal(template_id)
		var row := tpl.duplicate(true)
		row["template_id"] = template_id
		row["can_forge"] = bool(check.get("ok", false))
		row["reason"] = str(check.get("reason", ""))
		row["status"] = _normal_status_text(check)
		rows.append(row)
	rows.sort_custom(_sort_normal_candidate)
	return rows

func get_high_forge_candidates() -> Array[Dictionary]:
	var rows: Array[Dictionary] = []
	for tpl_any in _equip_templates():
		if not (tpl_any is Dictionary):
			continue
		var tpl: Dictionary = tpl_any
		if not _is_template_forge_enabled(tpl):
			continue
		if _template_quality_tier(tpl) != QUALITY_HIGH:
			continue
		var template_id := str(tpl.get("id", "")).strip_edges()
		if template_id.is_empty():
			continue
		var compose_preview := get_blueprint_compose_preview(template_id)
		var base_candidates := get_base_equip_candidates_for_high_forge(template_id)
		var blueprint_id := str(tpl.get("blueprint_item_id", "")).strip_edges()
		var blueprint_have := InventoryModel.get_count(blueprint_id) if not blueprint_id.is_empty() else 0
		var can_with_first := false
		var forge_status := "缺基础装备"
		if not base_candidates.is_empty():
			var first_uid := int(base_candidates[0].get("uid", 0))
			if first_uid > 0:
				var first_check := can_forge_high(template_id, first_uid)
				can_with_first = bool(first_check.get("ok", false))
				forge_status = _high_forge_status_text(first_check)
		var row := tpl.duplicate(true)
		row["template_id"] = template_id
		row["blueprint_item_id"] = blueprint_id
		row["blueprint_have"] = blueprint_have
		row["base_candidate_count"] = base_candidates.size()
		row["can_compose_blueprint"] = bool(compose_preview.get("can_compose", false))
		row["can_forge_with_first_base"] = can_with_first
		row["status"] = _high_status_text(row, compose_preview)
		row["forge_status"] = forge_status
		rows.append(row)
	rows.sort_custom(_sort_high_candidate)
	return rows

func get_normal_forge_recipe(template_id: String) -> Dictionary:
	var tpl := _find_template(template_id)
	if tpl.is_empty():
		return {}
	var tier := str(tpl.get("forge_tier", "")).strip_edges()
	var slot_group := _template_slot_group(tpl)
	var rule := _find_normal_rule(tier, slot_group)
	if rule.is_empty():
		return {}
	return {
		"template_id": template_id,
		"forge_tier": tier,
		"slot_group": slot_group,
		"materials": _normalize_material_rows(rule.get("materials", [])),
		"gold_cost": maxi(0, int(rule.get("gold_cost", 0))),
		"rule": rule,
	}

func get_high_forge_recipe(target_template_id: String) -> Dictionary:
	var tpl := _find_template(target_template_id)
	if tpl.is_empty():
		return {}
	var tier := str(tpl.get("forge_tier", "")).strip_edges()
	var slot_group := _template_slot_group(tpl)
	var theme_key := str(tpl.get("theme_key", "")).strip_edges()
	var rule := _find_high_rule(tier, slot_group, theme_key)
	if rule.is_empty():
		return {}
	var mats := _normalize_material_rows(rule.get("materials", []))
	var extra := _normalize_material_rows(rule.get("extra_materials", []))
	return {
		"template_id": target_template_id,
		"forge_tier": tier,
		"slot_group": slot_group,
		"theme_key": theme_key,
		"materials": mats,
		"extra_materials": extra,
		"all_materials": _combine_material_rows(mats, extra),
		"gold_cost": maxi(0, int(rule.get("gold_cost", 0))),
		"require_blueprint": bool(rule.get("require_blueprint", true)),
		"require_base_equip": bool(rule.get("require_base_equip", true)),
		"blueprint_item_id": str(tpl.get("blueprint_item_id", "")).strip_edges(),
		"rule": rule,
	}

func get_base_equip_candidates_for_high_forge(target_template_id: String) -> Array[Dictionary]:
	var target_tpl := _find_template(target_template_id)
	if target_tpl.is_empty():
		return []
	var family_id := str(target_tpl.get("forge_family_id", "")).strip_edges()
	if family_id.is_empty():
		return []

	var rows: Array[Dictionary] = []
	# 背包实例
	for row_any in EquipmentModel.list_bag_sorted():
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		if _instance_family_id(row) != family_id:
			continue
		if _instance_quality_tier(row) != QUALITY_NORMAL:
			continue
		if int(row.get("uid", 0)) <= 0:
			continue
		var out := row.duplicate(true)
		out["is_equipped"] = false
		out["equipped_slot"] = ""
		rows.append(out)

	# 已穿戴实例（允许作为升品底装）
	var equipped_map: Dictionary = EquipmentModel.equipped
	for slot_key_any in equipped_map.keys():
		var slot_key := str(slot_key_any)
		var uid := int(equipped_map.get(slot_key_any, 0))
		if uid <= 0:
			continue
		var inst_any = EquipmentModel.equipped_store.get(uid, {})
		if not (inst_any is Dictionary):
			continue
		var inst: Dictionary = (inst_any as Dictionary).duplicate(true)
		if _instance_family_id(inst) != family_id:
			continue
		if _instance_quality_tier(inst) != QUALITY_NORMAL:
			continue
		inst["is_equipped"] = true
		inst["equipped_slot"] = slot_key
		rows.append(inst)

	rows.sort_custom(_sort_base_candidate)
	return rows

func can_forge_normal(template_id: String) -> Dictionary:
	var result := {
		"ok": false,
		"reason": "invalid",
		"template_id": template_id,
		"required_gold": 0,
		"required_materials": [],
		"owned_materials": {},
		"missing_materials": [],
	}
	var tpl := _find_template(template_id)
	if tpl.is_empty():
		result["reason"] = "template_not_found"
		return result
	if not _is_template_forge_enabled(tpl):
		result["reason"] = "forge_disabled"
		return result
	if _template_quality_tier(tpl) != QUALITY_NORMAL:
		result["reason"] = "not_normal"
		return result

	var recipe := get_normal_forge_recipe(template_id)
	if recipe.is_empty():
		result["reason"] = "no_rule"
		return result

	var tier := str(recipe.get("forge_tier", "")).strip_edges()
	if not _is_tier_unlocked(tier):
		result["reason"] = "tier_locked"
		return result

	var mats_any = recipe.get("materials", [])
	var mats: Array = mats_any if mats_any is Array else []
	var mat_state := _build_material_state(mats)
	result["required_materials"] = mats
	result["owned_materials"] = mat_state.get("owned", {})
	result["missing_materials"] = mat_state.get("missing", [])

	var gold_cost := maxi(0, int(recipe.get("gold_cost", 0)))
	result["required_gold"] = gold_cost
	if not bool(mat_state.get("ok", false)):
		result["reason"] = "no_material"
		return result
	if not PlayerModel.can_spend_gold(gold_cost):
		result["reason"] = "no_gold"
		return result
	result["ok"] = true
	result["reason"] = ""
	return result

func do_forge_normal(template_id: String) -> Dictionary:
	var check := can_forge_normal(template_id)
	if not bool(check.get("ok", false)):
		check["ok"] = false
		return check

	var gold_cost := maxi(0, int(check.get("required_gold", 0)))
	var mats_any = check.get("required_materials", [])
	var mats: Array = mats_any if mats_any is Array else []
	var consume := _consume_material_rows(mats)
	if not bool(consume.get("ok", false)):
		check["ok"] = false
		check["reason"] = "no_material"
		return check
	if not PlayerModel.spend_gold(gold_cost):
		_rollback_spent_items(consume.get("spent", []))
		check["ok"] = false
		check["reason"] = "no_gold"
		return check

	var before := _collect_bag_uid_set()
	EquipmentModel.add_equip(template_id, "system")
	var new_uid := _find_new_bag_uid(before)
	return {
		"ok": true,
		"reason": "",
		"template_id": template_id,
		"new_uid": new_uid,
		"gold_cost": gold_cost,
		"materials": mats,
	}

func can_forge_high(target_template_id: String, base_uid: int) -> Dictionary:
	var result := {
		"ok": false,
		"reason": "invalid",
		"target_template_id": target_template_id,
		"base_uid": base_uid,
		"required_gold": 0,
		"required_materials": [],
		"owned_materials": {},
		"missing_materials": [],
		"required_blueprint": "",
		"blueprint_have": 0,
	}
	var target_tpl := _find_template(target_template_id)
	if target_tpl.is_empty():
		result["reason"] = "target_not_found"
		return result
	if not _is_template_forge_enabled(target_tpl):
		result["reason"] = "forge_disabled"
		return result
	if _template_quality_tier(target_tpl) != QUALITY_HIGH:
		result["reason"] = "not_high"
		return result

	var recipe := get_high_forge_recipe(target_template_id)
	if recipe.is_empty():
		result["reason"] = "no_rule"
		return result

	var tier := str(recipe.get("forge_tier", "")).strip_edges()
	if not _is_tier_unlocked(tier):
		result["reason"] = "tier_locked"
		return result

	var require_base := bool(recipe.get("require_base_equip", true))
	if require_base:
		if base_uid <= 0:
			result["reason"] = "no_base"
			return result
		var base_inst := _find_instance_by_uid(base_uid)
		if base_inst.is_empty():
			result["reason"] = "base_not_found"
			return result
		if not _is_base_compatible(base_inst, target_tpl):
			result["reason"] = "base_mismatch"
			return result

	var require_blueprint := bool(recipe.get("require_blueprint", true))
	var blueprint_id := str(recipe.get("blueprint_item_id", "")).strip_edges()
	result["required_blueprint"] = blueprint_id
	result["blueprint_have"] = InventoryModel.get_count(blueprint_id) if not blueprint_id.is_empty() else 0
	if require_blueprint:
		if blueprint_id.is_empty():
			result["reason"] = "blueprint_not_set"
			return result
		if int(result.get("blueprint_have", 0)) < 1:
			result["reason"] = "no_blueprint"
			return result

	var all_mats_any = recipe.get("all_materials", [])
	var all_mats: Array = all_mats_any if all_mats_any is Array else []
	var mat_state := _build_material_state(all_mats)
	result["required_materials"] = all_mats
	result["owned_materials"] = mat_state.get("owned", {})
	result["missing_materials"] = mat_state.get("missing", [])
	result["required_gold"] = maxi(0, int(recipe.get("gold_cost", 0)))
	if not bool(mat_state.get("ok", false)):
		result["reason"] = "no_material"
		return result
	if not PlayerModel.can_spend_gold(int(result.get("required_gold", 0))):
		result["reason"] = "no_gold"
		return result

	result["ok"] = true
	result["reason"] = ""
	return result

func do_forge_high(target_template_id: String, base_uid: int) -> Dictionary:
	var check := can_forge_high(target_template_id, base_uid)
	if not bool(check.get("ok", false)):
		check["ok"] = false
		return check

	var spent_rows: Array = []
	var blueprint_id := str(check.get("required_blueprint", "")).strip_edges()
	if not blueprint_id.is_empty():
		if not InventoryModel.consume_item(blueprint_id, 1, "system"):
			check["ok"] = false
			check["reason"] = "no_blueprint"
			return check
		spent_rows.append({"item_id": blueprint_id, "count": 1})

	var mats_any = check.get("required_materials", [])
	var mats: Array = mats_any if mats_any is Array else []
	var consume := _consume_material_rows(mats)
	if not bool(consume.get("ok", false)):
		_rollback_spent_items(spent_rows)
		check["ok"] = false
		check["reason"] = "no_material"
		return check
	var mat_spent_any = consume.get("spent", [])
	if mat_spent_any is Array:
		for row_any in mat_spent_any:
			if row_any is Dictionary:
				spent_rows.append((row_any as Dictionary).duplicate(true))

	var gold_cost := maxi(0, int(check.get("required_gold", 0)))
	if not PlayerModel.spend_gold(gold_cost):
		_rollback_spent_items(spent_rows)
		check["ok"] = false
		check["reason"] = "no_gold"
		return check

	var upgrade := EquipmentModel.upgrade_quality_from_base(base_uid, target_template_id)
	if not bool(upgrade.get("ok", false)):
		_rollback_spent_items(spent_rows)
		if gold_cost > 0:
			PlayerModel.add_gold(gold_cost)
		check["ok"] = false
		check["reason"] = str(upgrade.get("reason", "upgrade_failed"))
		return check

	return {
		"ok": true,
		"reason": "",
		"target_template_id": target_template_id,
		"base_uid": base_uid,
		"new_uid": int(upgrade.get("new_uid", 0)),
		"required_gold": gold_cost,
		"required_blueprint": blueprint_id,
		"required_materials": mats,
	}

func get_blueprint_compose_preview(target_template_id: String) -> Dictionary:
	var out := {
		"ok": false,
		"reason": "invalid",
		"target_template_id": target_template_id,
		"theme_key": "",
		"blueprint_item_id": "",
		"fragment_item_id": "",
		"fragment_need": 0,
		"fragment_have": 0,
		"gold_cost": 0,
		"gold_have": int(PlayerModel.gold),
		"can_compose": false,
	}
	var tpl := _find_template(target_template_id)
	if tpl.is_empty():
		out["reason"] = "target_not_found"
		return out
	var theme_key := str(tpl.get("theme_key", "")).strip_edges()
	var blueprint_id := str(tpl.get("blueprint_item_id", "")).strip_edges()
	out["theme_key"] = theme_key
	out["blueprint_item_id"] = blueprint_id
	if theme_key.is_empty() or blueprint_id.is_empty():
		out["reason"] = "blueprint_not_set"
		return out
	var rule := _find_compose_rule(theme_key)
	if rule.is_empty():
		out["reason"] = "no_compose_rule"
		return out
	var fragment_id := str(rule.get("fragment_item_id", "")).strip_edges()
	var fragment_need := maxi(1, int(rule.get("fragment_count", 20)))
	var gold_cost := maxi(0, int(rule.get("gold_cost", 0)))
	out["fragment_item_id"] = fragment_id
	out["fragment_need"] = fragment_need
	out["fragment_have"] = InventoryModel.get_count(fragment_id) if not fragment_id.is_empty() else 0
	out["gold_cost"] = gold_cost
	out["gold_have"] = int(PlayerModel.gold)
	if fragment_id.is_empty():
		out["reason"] = "fragment_not_set"
		return out
	if int(out.get("fragment_have", 0)) < fragment_need:
		out["reason"] = "fragment_not_enough"
		return out
	if not PlayerModel.can_spend_gold(gold_cost):
		out["reason"] = "no_gold"
		return out
	out["ok"] = true
	out["reason"] = ""
	out["can_compose"] = true
	return out

func can_compose_blueprint(target_template_id: String) -> Dictionary:
	return get_blueprint_compose_preview(target_template_id)

func do_compose_blueprint(target_template_id: String) -> Dictionary:
	var preview := get_blueprint_compose_preview(target_template_id)
	if not bool(preview.get("can_compose", false)):
		preview["ok"] = false
		return preview
	var fragment_id := str(preview.get("fragment_item_id", "")).strip_edges()
	var fragment_need := maxi(1, int(preview.get("fragment_need", 0)))
	var gold_cost := maxi(0, int(preview.get("gold_cost", 0)))
	var blueprint_id := str(preview.get("blueprint_item_id", "")).strip_edges()
	if not InventoryModel.consume_item(fragment_id, fragment_need, "system"):
		preview["ok"] = false
		preview["reason"] = "fragment_not_enough"
		return preview
	if not PlayerModel.spend_gold(gold_cost):
		InventoryModel.add_item(fragment_id, fragment_need, "system")
		preview["ok"] = false
		preview["reason"] = "no_gold"
		return preview
	InventoryModel.add_item(blueprint_id, 1, "system")
	return {
		"ok": true,
		"reason": "",
		"target_template_id": target_template_id,
		"blueprint_item_id": blueprint_id,
		"fragment_item_id": fragment_id,
		"fragment_need": fragment_need,
		"gold_cost": gold_cost,
	}

func _equip_templates() -> Array:
	var cfg: Dictionary = ConfigService.get_cfg()
	var db_any = cfg.get("equip_db", {})
	if not (db_any is Dictionary):
		return []
	var rows_any = (db_any as Dictionary).get("equip_templates", [])
	if rows_any is Array:
		return rows_any
	return []

func _find_template(template_id: String) -> Dictionary:
	return EquipmentModel.get_template(template_id)

func _is_template_forge_enabled(tpl: Dictionary) -> bool:
	if tpl.has("forge_enabled"):
		return bool(tpl.get("forge_enabled", false))
	return false

func _template_quality_tier(tpl: Dictionary) -> String:
	return str(tpl.get("quality_tier", QUALITY_NORMAL)).strip_edges().to_lower()

func _template_slot_group(tpl: Dictionary) -> String:
	var slot_group := str(tpl.get("slot_group", "")).strip_edges().to_lower()
	if not slot_group.is_empty():
		return slot_group
	return _slot_group_from_slot(str(tpl.get("slot", "")))

func _slot_group_from_slot(slot: String) -> String:
	var key := slot.strip_edges().to_lower()
	if key == "weapon":
		return "weapon"
	if key == "armor" or key == "pants" or key == "helm" or key == "shoes":
		return "armor"
	if key == "cloak":
		return "cloak"
	return "accessory"

func _forge_rules_root() -> Dictionary:
	var cfg: Dictionary = ConfigService.get_cfg()
	var db_any = cfg.get("forge_rules_db", {})
	if not (db_any is Dictionary):
		return {}
	var db: Dictionary = db_any
	var root_any = db.get("forge_rules", {})
	if root_any is Dictionary:
		return root_any
	return {}

func _find_normal_rule(forge_tier: String, slot_group: String) -> Dictionary:
	var rules_any = _forge_rules_root().get("normal_forge_rules", [])
	if not (rules_any is Array):
		return {}
	for row_any in rules_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		if str(row.get("forge_tier", "")).strip_edges() != forge_tier:
			continue
		if str(row.get("slot_group", "")).strip_edges() != slot_group:
			continue
		return row
	return {}

func _find_high_rule(forge_tier: String, slot_group: String, theme_key: String) -> Dictionary:
	var rules_any = _forge_rules_root().get("high_forge_rules", [])
	if not (rules_any is Array):
		return {}
	for row_any in rules_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		if str(row.get("forge_tier", "")).strip_edges() != forge_tier:
			continue
		if str(row.get("slot_group", "")).strip_edges() != slot_group:
			continue
		if str(row.get("theme_key", "")).strip_edges() != theme_key:
			continue
		return row
	return {}

func _find_compose_rule(theme_key: String) -> Dictionary:
	var rules_any = _forge_rules_root().get("blueprint_compose_rules", [])
	if not (rules_any is Array):
		return {}
	for row_any in rules_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		if str(row.get("theme_key", "")).strip_edges() == theme_key:
			return row
	return {}

func _is_tier_unlocked(forge_tier: String) -> bool:
	if forge_tier.strip_edges().is_empty():
		return true
	var tiers_any = _forge_rules_root().get("tiers", {})
	if not (tiers_any is Dictionary):
		return true
	var tier_any = (tiers_any as Dictionary).get(forge_tier, {})
	if not (tier_any is Dictionary):
		return true
	var level_range_any = (tier_any as Dictionary).get("level_range", [])
	if not (level_range_any is Array):
		return true
	var level_range: Array = level_range_any
	if level_range.size() < 2:
		return true
	var min_lv := int(level_range[0])
	var lv := maxi(1, int(ProgressModel.level))
	# 打造解锁采用“达到该tier下限即可”，避免高等级角色反而无法打造低tier配方。
	return lv >= min_lv

func _normalize_material_rows(materials_any: Variant) -> Array[Dictionary]:
	var out: Array[Dictionary] = []
	if not (materials_any is Array):
		return out
	for row_any in materials_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		var item_id := str(row.get("item_id", "")).strip_edges()
		var cnt := maxi(0, int(row.get("count", 0)))
		if item_id.is_empty() or cnt <= 0:
			continue
		out.append({"item_id": item_id, "count": cnt})
	return _combine_material_rows(out, [])

func _combine_material_rows(a: Array, b: Array) -> Array[Dictionary]:
	var merged: Dictionary = {}
	for src_any in [a, b]:
		if not (src_any is Array):
			continue
		for row_any in (src_any as Array):
			if not (row_any is Dictionary):
				continue
			var row: Dictionary = row_any
			var item_id := str(row.get("item_id", "")).strip_edges()
			var cnt := maxi(0, int(row.get("count", 0)))
			if item_id.is_empty() or cnt <= 0:
				continue
			merged[item_id] = int(merged.get(item_id, 0)) + cnt
	var out: Array[Dictionary] = []
	for item_id_any in merged.keys():
		var item_id := str(item_id_any)
		var cnt := int(merged.get(item_id_any, 0))
		if cnt <= 0:
			continue
		out.append({"item_id": item_id, "count": cnt})
	out.sort_custom(func(x_any: Variant, y_any: Variant) -> bool:
		if not (x_any is Dictionary) or not (y_any is Dictionary):
			return false
		var x: Dictionary = x_any
		var y: Dictionary = y_any
		return str(x.get("item_id", "")) < str(y.get("item_id", ""))
	)
	return out

func _build_material_state(materials: Array) -> Dictionary:
	var owned: Dictionary = {}
	var missing: Array[Dictionary] = []
	var ok := true
	for row_any in materials:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		var item_id := str(row.get("item_id", "")).strip_edges()
		var need := maxi(0, int(row.get("count", 0)))
		if item_id.is_empty() or need <= 0:
			continue
		var have := InventoryModel.get_count(item_id)
		owned[item_id] = have
		if have < need:
			ok = false
			missing.append({"item_id": item_id, "need": need, "have": have})
	return {
		"ok": ok,
		"owned": owned,
		"missing": missing,
	}

func _consume_material_rows(materials: Array) -> Dictionary:
	var spent: Array[Dictionary] = []
	for row_any in materials:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		var item_id := str(row.get("item_id", "")).strip_edges()
		var cnt := maxi(0, int(row.get("count", 0)))
		if item_id.is_empty() or cnt <= 0:
			continue
		if InventoryModel.consume_item(item_id, cnt, "system"):
			spent.append({"item_id": item_id, "count": cnt})
			continue
		_rollback_spent_items(spent)
		return {"ok": false, "spent": []}
	return {"ok": true, "spent": spent}

func _rollback_spent_items(spent_any: Variant) -> void:
	if not (spent_any is Array):
		return
	for row_any in (spent_any as Array):
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		var item_id := str(row.get("item_id", "")).strip_edges()
		var cnt := maxi(0, int(row.get("count", 0)))
		if item_id.is_empty() or cnt <= 0:
			continue
		InventoryModel.add_item(item_id, cnt, "system")

func _collect_bag_uid_set() -> Dictionary:
	var out: Dictionary = {}
	for row_any in EquipmentModel.list_bag_sorted():
		if not (row_any is Dictionary):
			continue
		var uid := int((row_any as Dictionary).get("uid", 0))
		if uid > 0:
			out[uid] = true
	return out

func _find_new_bag_uid(before: Dictionary) -> int:
	for row_any in EquipmentModel.list_bag_sorted():
		if not (row_any is Dictionary):
			continue
		var uid := int((row_any as Dictionary).get("uid", 0))
		if uid <= 0:
			continue
		if before.has(uid):
			continue
		return uid
	return 0

func _find_instance_by_uid(uid: int) -> Dictionary:
	if uid <= 0:
		return {}
	var inst := EquipmentModel.get_instance(uid)
	if not inst.is_empty():
		return inst
	return {}

func _instance_family_id(inst: Dictionary) -> String:
	var tpl := _find_template(str(inst.get("template_id", "")).strip_edges())
	return str(tpl.get("forge_family_id", "")).strip_edges()

func _instance_quality_tier(inst: Dictionary) -> String:
	var tpl := _find_template(str(inst.get("template_id", "")).strip_edges())
	return _template_quality_tier(tpl)

func _is_base_compatible(base_inst: Dictionary, target_tpl: Dictionary) -> bool:
	if base_inst.is_empty() or target_tpl.is_empty():
		return false
	if _instance_quality_tier(base_inst) != QUALITY_NORMAL:
		return false
	var base_family := _instance_family_id(base_inst)
	var target_family := str(target_tpl.get("forge_family_id", "")).strip_edges()
	if base_family.is_empty() or target_family.is_empty():
		return false
	return base_family == target_family

func _slot_group_sort_value(slot_group: String) -> int:
	match slot_group:
		"weapon":
			return 0
		"armor":
			return 1
		"cloak":
			return 2
		_:
			return 3

func _normal_status_text(check: Dictionary) -> String:
	if bool(check.get("ok", false)):
		return "可打造"
	var reason := str(check.get("reason", ""))
	match reason:
		"tier_locked":
			return "未解锁"
		"no_material":
			return "材料不足"
		"no_gold":
			return "金币不足"
		_:
			return "不可打造"

func _high_status_text(row: Dictionary, compose_preview: Dictionary) -> String:
	var blueprint_have := maxi(0, int(row.get("blueprint_have", 0)))
	if blueprint_have > 0:
		return "已拥有图纸"
	if bool(compose_preview.get("can_compose", false)):
		return "碎片可合成"
	var frag_have := maxi(0, int(compose_preview.get("fragment_have", 0)))
	var frag_need := maxi(0, int(compose_preview.get("fragment_need", 0)))
	if frag_need > 0 and frag_have > 0:
		return "碎片 %d/%d" % [frag_have, frag_need]
	return "无图纸"

func _high_forge_status_text(check: Dictionary) -> String:
	if bool(check.get("ok", false)):
		return "可升品"
	var reason := str(check.get("reason", ""))
	match reason:
		"no_base", "base_not_found":
			return "缺基础装备"
		"base_mismatch":
			return "底装不匹配"
		"no_blueprint":
			return "缺图纸"
		"no_material":
			return "材料不足"
		"no_gold":
			return "金币不足"
		"tier_locked":
			return "未解锁"
		_:
			return "不可升品"

func _sort_normal_candidate(a_any: Variant, b_any: Variant) -> bool:
	if not (a_any is Dictionary) or not (b_any is Dictionary):
		return false
	var a: Dictionary = a_any
	var b: Dictionary = b_any
	var a_can := bool(a.get("can_forge", false))
	var b_can := bool(b.get("can_forge", false))
	if a_can != b_can:
		return a_can
	var a_tier := str(a.get("forge_tier", "T1"))
	var b_tier := str(b.get("forge_tier", "T1"))
	if a_tier != b_tier:
		return a_tier < b_tier
	var a_slot := _slot_group_sort_value(_template_slot_group(a))
	var b_slot := _slot_group_sort_value(_template_slot_group(b))
	if a_slot != b_slot:
		return a_slot < b_slot
	return str(a.get("name", "")) < str(b.get("name", ""))

func _sort_high_candidate(a_any: Variant, b_any: Variant) -> bool:
	if not (a_any is Dictionary) or not (b_any is Dictionary):
		return false
	var a: Dictionary = a_any
	var b: Dictionary = b_any
	var a_bp := int(a.get("blueprint_have", 0))
	var b_bp := int(b.get("blueprint_have", 0))
	if (a_bp > 0) != (b_bp > 0):
		return a_bp > 0
	var a_comp := bool(a.get("can_compose_blueprint", false))
	var b_comp := bool(b.get("can_compose_blueprint", false))
	if a_comp != b_comp:
		return a_comp
	var a_base := int(a.get("base_candidate_count", 0))
	var b_base := int(b.get("base_candidate_count", 0))
	if (a_base > 0) != (b_base > 0):
		return a_base > 0
	var a_tier := str(a.get("forge_tier", "T1"))
	var b_tier := str(b.get("forge_tier", "T1"))
	if a_tier != b_tier:
		return a_tier < b_tier
	var a_slot := _slot_group_sort_value(_template_slot_group(a))
	var b_slot := _slot_group_sort_value(_template_slot_group(b))
	if a_slot != b_slot:
		return a_slot < b_slot
	return str(a.get("name", "")) < str(b.get("name", ""))

func _sort_base_candidate(a_any: Variant, b_any: Variant) -> bool:
	if not (a_any is Dictionary) or not (b_any is Dictionary):
		return false
	var a: Dictionary = a_any
	var b: Dictionary = b_any
	var a_equipped := bool(a.get("is_equipped", false))
	var b_equipped := bool(b.get("is_equipped", false))
	if a_equipped != b_equipped:
		return a_equipped
	var a_star := int(a.get("star_level", 0))
	var b_star := int(b.get("star_level", 0))
	if a_star != b_star:
		return a_star > b_star
	var a_socket := int(a.get("sockets", 0))
	var b_socket := int(b.get("sockets", 0))
	if a_socket != b_socket:
		return a_socket > b_socket
	return int(a.get("uid", 0)) < int(b.get("uid", 0))
