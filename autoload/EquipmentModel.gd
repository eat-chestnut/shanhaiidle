extends Node

const StatsServiceRef := preload("res://services/StatsService.gd")
const SAVE_PATH := "user://equipment.json"
const MAX_SOCKETS := 4
const REFINE_MAX := 5
const STAR_MAX_DEFAULT := 10

var next_uid: int = 1
var bag: Array = []
var equipped: Dictionary = {
	"weapon": 0,
	"helm": 0,
	"armor": 0,
	"pants": 0,
	"shoes": 0,
	"cloak": 0,
	"ring1": 0,
	"ring2": 0,
	"bracelet1": 0,
	"bracelet2": 0,
}
var equipped_store: Dictionary = {}
var _dirty := false
var _save_timer: Timer

func _ready() -> void:
	_save_timer = Timer.new()
	_save_timer.one_shot = true
	_save_timer.wait_time = 0.6
	add_child(_save_timer)
	_save_timer.timeout.connect(_flush_save)
	_load_save()

func create_instance(template_id: String) -> Dictionary:
	var template := _find_template(template_id)
	if template.is_empty():
		return {}

	var main_stat := str(template.get("main_stat", "")).strip_edges()
	var star_level := 0
	var main_val := _template_main_value_for_star(template, main_stat, star_level)
	var sockets := _template_default_socket_count(template)
	var effects := _normalize_effects(template.get("effects", []))
	var unidentified_chance := clampf(float(template.get("unidentified_chance", 0.0)), 0.0, 1.0)
	var identified := randf() >= unidentified_chance
	var socket_gems: Array[String] = []
	socket_gems.resize(sockets)
	for i in range(sockets):
		socket_gems[i] = ""

	var instance := {
		"uid": next_uid,
		"template_id": template_id,
		"name": str(template.get("name", template_id)),
		"slot": str(template.get("slot", "")),
		"rarity": str(template.get("rarity", "white")),
		"main_stat": main_stat,
		"main_min": main_val,
		"main_max": main_val,
		"main_val": main_val,
		"tier": 0,
		"set_id": str(template.get("set_id", "")),
		"effects": effects,
		"extra_effects": [],
		"sockets": sockets,
		"socket_gems": socket_gems,
		"icon": str(template.get("icon", "")),
		"identified": identified,
		"locked": false,
		"star_level": star_level,
		"refine_lv": 0,
	}
	instance = _sync_legacy_main_fields(instance)
	next_uid += 1
	return instance

func add_equip(template_id: String, source: String = "online") -> void:
	var inst := create_instance(template_id)
	if inst.is_empty():
		return
	bag.append(inst)
	if has_node("/root/EquipDexModel"):
		EquipDexModel.unlock(template_id, false)
	if source == "online":
		PerfTracker.record_equip_gain(template_id, 1)
	EventBus.notify_inventory_updated()
	_request_save()

func get_instance(uid: int) -> Dictionary:
	return _get_instance_by_uid(uid)

func equip_uid(uid: int) -> bool:
	if uid <= 0:
		return false
	var bag_index := _find_bag_index(uid)
	if bag_index < 0:
		return false

	var inst_any = bag[bag_index]
	if not (inst_any is Dictionary):
		return false
	var inst: Dictionary = inst_any
	if not is_identified(inst):
		return false
	var slot: String = str(inst.get("slot", ""))
	var slot_key := _resolve_slot_key_for_equip(slot)
	if slot_key.is_empty() or not equipped.has(slot_key):
		return false

	var old_uid := int(equipped.get(slot_key, 0))
	if old_uid != 0:
		var old_inst_any = equipped_store.get(old_uid, {})
		if old_inst_any is Dictionary:
			bag.append(old_inst_any)
		equipped_store.erase(old_uid)

	bag.remove_at(bag_index)
	equipped[slot_key] = uid
	equipped_store[uid] = inst
	EventBus.notify_inventory_updated()
	_request_save()
	return true

func is_identified(inst: Dictionary) -> bool:
	return bool(inst.get("identified", true))

func is_locked(inst: Dictionary) -> bool:
	return bool(inst.get("locked", false))

func toggle_lock(uid: int) -> bool:
	if uid <= 0:
		return false
	var inst := _get_instance_by_uid(uid)
	if inst.is_empty():
		return false
	inst["locked"] = not is_locked(inst)
	if not _set_instance_by_uid(uid, inst):
		return false
	EventBus.notify_inventory_updated()
	_request_save()
	return true

func identify(uid: int) -> Dictionary:
	var result := {
		"ok": false,
		"reason": "not_found",
		"cost": 0,
	}
	if uid <= 0:
		result["reason"] = "invalid"
		return result
	var inst := _get_instance_by_uid(uid)
	if inst.is_empty():
		return result
	if is_identified(inst):
		result["reason"] = "already"
		return result

	var rarity := str(inst.get("rarity", "white"))
	var cost := _identify_cost_for_rarity(rarity)
	result["cost"] = cost
	if not PlayerModel.spend_gold(cost):
		result["reason"] = "no_gold"
		return result

	inst["identified"] = true
	if not _set_instance_by_uid(uid, inst):
		PlayerModel.add_gold(cost)
		result["reason"] = "not_found"
		return result

	EventBus.notify_inventory_updated()
	_request_save()
	result["ok"] = true
	result["reason"] = ""
	return result

func get_identify_cost_by_rarity(rarity: String) -> int:
	return _identify_cost_for_rarity(rarity)

func get_salvage_reward_for_rarity(rarity: String) -> Dictionary:
	return _salvage_reward_for_rarity(rarity)

func get_salvage_reward_by_rarity(rarity: String) -> Dictionary:
	return _salvage_reward_for_rarity(rarity)

func preview_salvage(uids: Array[int]) -> Dictionary:
	var total_gold := 0
	var total_items: Dictionary = {}
	var count := 0
	var seen: Dictionary = {}
	for uid in uids:
		if uid <= 0:
			continue
		var key := str(uid)
		if seen.has(key):
			continue
		seen[key] = true
		var idx := _find_bag_index(uid)
		if idx < 0:
			continue
		var inst_any = bag[idx]
		if not (inst_any is Dictionary):
			continue
		var inst: Dictionary = inst_any
		if is_locked(inst):
			continue
		var rarity := str(inst.get("rarity", "white"))
		var reward := _salvage_reward_for_rarity(rarity)
		total_gold += maxi(0, int(reward.get("gold", 0)))
		var items_any = reward.get("items", {})
		if items_any is Dictionary:
			for item_id_any in (items_any as Dictionary).keys():
				var item_id := str(item_id_any).strip_edges()
				var cnt := maxi(0, int((items_any as Dictionary).get(item_id_any, 0)))
				if item_id.is_empty() or cnt <= 0:
					continue
				total_items[item_id] = int(total_items.get(item_id, 0)) + cnt
		count += 1
	return {
		"count": count,
		"gold": total_gold,
		"items": total_items,
	}

func salvage_many(uids: Array[int]) -> Dictionary:
	var count := 0
	var total_gold := 0
	var total_items: Dictionary = {}
	var seen: Dictionary = {}
	for uid in uids:
		if uid <= 0:
			continue
		var key := str(uid)
		if seen.has(key):
			continue
		seen[key] = true
		var idx := _find_bag_index(uid)
		if idx < 0:
			continue
		var inst_any = bag[idx]
		if not (inst_any is Dictionary):
			continue
		var inst: Dictionary = inst_any
		if is_locked(inst):
			continue
		var rarity := str(inst.get("rarity", "white"))
		var reward := _salvage_reward_for_rarity(rarity)
		total_gold += maxi(0, int(reward.get("gold", 0)))
		var items_any = reward.get("items", {})
		if items_any is Dictionary:
			for item_id_any in (items_any as Dictionary).keys():
				var item_id := str(item_id_any).strip_edges()
				var cnt := maxi(0, int((items_any as Dictionary).get(item_id_any, 0)))
				if item_id.is_empty() or cnt <= 0:
					continue
				total_items[item_id] = int(total_items.get(item_id, 0)) + cnt
		bag.remove_at(idx)
		count += 1
	if count <= 0:
		return {
			"ok": false,
			"reason": "empty",
			"count": 0,
			"gold": 0,
			"items": {},
		}

	if total_gold > 0:
		PlayerModel.add_gold(total_gold, false)
	InventoryModel.add_items_bulk(total_items, "system", false)

	_request_save()
	EventBus.notify_inventory_updated()
	return {
		"ok": true,
		"reason": "",
		"count": count,
		"gold": total_gold,
		"items": total_items,
	}

func salvage(uid: int) -> Dictionary:
	var result := {
		"ok": false,
		"reason": "not_found",
		"name": "",
		"gold": 0,
		"items": {},
	}
	if uid <= 0:
		result["reason"] = "invalid"
		return result
	if _is_uid_equipped(uid):
		result["reason"] = "equipped"
		return result

	var idx := _find_bag_index(uid)
	if idx < 0:
		return result
	var inst_any = bag[idx]
	if not (inst_any is Dictionary):
		return result
	var inst: Dictionary = inst_any
	if is_locked(inst):
		result["reason"] = "locked"
		return result
	bag.remove_at(idx)

	var rarity := str(inst.get("rarity", "white"))
	var reward := _salvage_reward_for_rarity(rarity)
	var gold := maxi(0, int(reward.get("gold", 0)))
	var items_any = reward.get("items", {})
	var reward_items: Dictionary = {}
	if items_any is Dictionary:
		for item_id_any in (items_any as Dictionary).keys():
			var item_id := str(item_id_any).strip_edges()
			var cnt := maxi(0, int((items_any as Dictionary).get(item_id_any, 0)))
			if item_id.is_empty() or cnt <= 0:
				continue
			reward_items[item_id] = cnt

	if gold > 0:
		PlayerModel.add_gold(gold, false)
	InventoryModel.add_items_bulk(reward_items, "system", false)

	_request_save()
	EventBus.notify_inventory_updated()
	result["ok"] = true
	result["reason"] = ""
	result["name"] = str(inst.get("name", "装备"))
	result["gold"] = gold
	result["items"] = reward_items
	return result

func unequip(slot_key: String) -> void:
	if not equipped.has(slot_key):
		return
	var uid := int(equipped.get(slot_key, 0))
	if uid == 0:
		return
	var inst_any = equipped_store.get(uid, {})
	if inst_any is Dictionary:
		bag.append(inst_any)
	equipped_store.erase(uid)
	equipped[slot_key] = 0
	EventBus.notify_inventory_updated()
	_request_save()

func get_total_stats() -> Dictionary:
	return get_total_stats_with_override({})

func get_total_stats_with_override(override_equipped: Dictionary) -> Dictionary:
	var state := _build_override_equipped_state(override_equipped)
	var sim_equipped_any: Variant = state.get("equipped", {})
	var sim_store_any: Variant = state.get("store", {})
	var sim_equipped: Dictionary = sim_equipped_any if sim_equipped_any is Dictionary else {}
	var sim_store: Dictionary = sim_store_any if sim_store_any is Dictionary else {}
	return _calc_totals_from_equipped(sim_equipped, sim_store)

func get_total_stats_simulate_replace(slot_key: String, candidate_inst: Dictionary) -> Dictionary:
	var override_equipped: Dictionary = {}
	var normalized_slot := slot_key.strip_edges()
	if not normalized_slot.is_empty():
		override_equipped[normalized_slot] = candidate_inst if not candidate_inst.is_empty() else {}
	return get_total_stats_with_override(override_equipped)

func get_skill_level_bonuses(slot_key_override: String = "", candidate_inst: Dictionary = {}) -> Dictionary:
	var state := _build_simulated_equipped_state(slot_key_override, candidate_inst)
	var sim_store_any: Variant = state.get("store", {})
	var sim_store: Dictionary = sim_store_any if sim_store_any is Dictionary else {}
	var skill_bonuses: Dictionary = {}

	for uid_any in sim_store.keys():
		var inst_any = sim_store.get(uid_any, {})
		if not (inst_any is Dictionary):
			continue
		var inst: Dictionary = inst_any
		var effects := get_all_effects(inst)
		for effect_any in effects:
			if not (effect_any is Dictionary):
				continue
			var effect: Dictionary = effect_any
			if str(effect.get("type", "")) != "skill_level":
				continue
			var skill_id := str(effect.get("skill_id", "")).strip_edges()
			var val := int(effect.get("val", 0))
			if skill_id.is_empty() or val == 0:
				continue
			skill_bonuses[skill_id] = int(skill_bonuses.get(skill_id, 0)) + val

	var set_counts := _set_counts_from_store(sim_store)
	var set_bonus := get_active_set_bonuses(set_counts)
	var set_skills_any: Variant = set_bonus.get("skills", {})
	if set_skills_any is Dictionary:
		var set_skills: Dictionary = set_skills_any
		for skill_id_any in set_skills.keys():
			var skill_id := str(skill_id_any).strip_edges()
			if skill_id.is_empty():
				continue
			skill_bonuses[skill_id] = int(skill_bonuses.get(skill_id, 0)) + int(set_skills.get(skill_id_any, 0))

	return skill_bonuses

func _calc_totals_from_equipped(equipped_map: Dictionary, equipped_store_map: Dictionary) -> Dictionary:
	var base_stats: Dictionary = StatsServiceRef.calc_base_stats(ProgressModel.level, ProgressModel.attrs)
	var totals := {
		"HP": int(base_stats.get("HP", 10)),
		"ATK": int(base_stats.get("ATK", 1)),
		"DEF": int(base_stats.get("DEF", 0)),
		"QI": int(base_stats.get("QI", 10)),
		"CRIT_PERCENT": int(base_stats.get("CRIT_PERCENT", 5)),
		"LOOT_BONUS_PERCENT": int(base_stats.get("LOOT_BONUS_PERCENT", 0)),
		"PHYS_MUL_PERMILLE": int(base_stats.get("PHYS_MUL_PERMILLE", 1000)),
		"SPELL_MUL_PERMILLE": int(base_stats.get("SPELL_MUL_PERMILLE", 1000)),
	}

	for slot_key_any in equipped_map.keys():
		var slot_key := str(slot_key_any)
		var uid := int(equipped_map.get(slot_key, 0))
		if uid == 0:
			continue
		var inst_any = equipped_store_map.get(uid, {})
		if not (inst_any is Dictionary):
			continue
		var inst: Dictionary = inst_any
		inst = _ensure_socket_fields(inst)
		var inst_stats := get_instance_template_stats(inst)
		for stat_any in inst_stats.keys():
			var stat := _normalize_stat_key(str(stat_any))
			var val := int(inst_stats.get(stat_any, 0))
			if stat.is_empty() or val == 0:
				continue
			totals[stat] = int(totals.get(stat, 0)) + val
		_apply_effect_stat_bonus(totals, inst)
		_apply_socket_gem_bonus_from_instance(totals, inst)

	var gem_bonus: Dictionary = GemSlotModel.get_bonus()
	for key in ["HP", "ATK", "DEF", "LOOT_BONUS_PERCENT"]:
		totals[key] = int(totals.get(key, 0)) + int(gem_bonus.get(key, 0))

	var set_counts := _set_counts_from_store(equipped_store_map)
	var set_bonus: Dictionary = get_active_set_bonuses(set_counts)
	var set_stats_any: Variant = set_bonus.get("stats", {})
	if set_stats_any is Dictionary:
		var set_stats: Dictionary = set_stats_any
		for key_any in set_stats.keys():
			var key := _normalize_stat_key(str(key_any))
			var val := int(set_stats.get(key_any, 0))
			if key.is_empty() or val == 0:
				continue
			totals[key] = int(totals.get(key, 0)) + val

	totals["CRIT"] = int(totals.get("CRIT_PERCENT", 0))
	totals["DROP"] = int(totals.get("LOOT_BONUS_PERCENT", 0))
	return totals

func get_set_counts(equipped_only: bool = true) -> Dictionary:
	var counts := get_set_counts_with_override({})
	if equipped_only:
		return counts

	for entry_any in bag:
		if not (entry_any is Dictionary):
			continue
		var row: Dictionary = entry_any
		var set_id := str(row.get("set_id", "")).strip_edges()
		if set_id.is_empty():
			continue
		counts[set_id] = int(counts.get(set_id, 0)) + 1
	return counts

func get_set_counts_with_override(override_equipped: Dictionary) -> Dictionary:
	var state := _build_override_equipped_state(override_equipped)
	var sim_store_any: Variant = state.get("store", {})
	var sim_store: Dictionary = sim_store_any if sim_store_any is Dictionary else {}
	return _set_counts_from_store(sim_store)

func get_set_counts_simulate_replace(target_slot: String, candidate_inst: Dictionary) -> Dictionary:
	var override_equipped: Dictionary = {}
	var slot_key := target_slot.strip_edges()
	if not slot_key.is_empty():
		override_equipped[slot_key] = candidate_inst if not candidate_inst.is_empty() else {}
	return get_set_counts_with_override(override_equipped)

func get_active_set_thresholds(set_counts: Dictionary) -> Dictionary:
	var out: Dictionary = {}
	var sets := _equipment_sets_array()
	for set_any in sets:
		if not (set_any is Dictionary):
			continue
		var set_row: Dictionary = set_any
		var set_id := str(set_row.get("id", "")).strip_edges()
		if set_id.is_empty():
			continue
		var pieces := int(set_counts.get(set_id, 0))
		var active_counts: Array[int] = []
		var thresholds := _normalize_set_thresholds(set_row.get("thresholds", []))
		for th_any in thresholds:
			if not (th_any is Dictionary):
				continue
			var th: Dictionary = th_any
			var need := maxi(1, int(th.get("count", 1)))
			if pieces >= need:
				active_counts.append(need)
		if not active_counts.is_empty():
			out[set_id] = active_counts
	return out

func get_new_set_activations(target_slot: String, candidate_inst: Dictionary) -> Array[Dictionary]:
	var new_set := str(candidate_inst.get("set_id", "")).strip_edges()
	if new_set.is_empty():
		return []

	var before_counts := get_set_counts(true)
	var after_counts := get_set_counts_simulate_replace(target_slot, candidate_inst)
	var before_active := get_active_set_thresholds(before_counts)
	var after_active := get_active_set_thresholds(after_counts)
	var activations: Array[Dictionary] = []

	var sets := _equipment_sets_array()
	for set_any in sets:
		if not (set_any is Dictionary):
			continue
		var set_row: Dictionary = set_any
		var set_id := str(set_row.get("id", "")).strip_edges()
		if set_id.is_empty():
			continue

		var before_list_any = before_active.get(set_id, [])
		var after_list_any = after_active.get(set_id, [])
		var before_list: Array = before_list_any if before_list_any is Array else []
		var after_list: Array = after_list_any if after_list_any is Array else []
		if after_list.is_empty():
			continue
		for count_any in after_list:
			var need := int(count_any)
			if before_list.has(need):
				continue
			var bonuses := _bonuses_for_set_threshold(set_row, need)
			var summary := _set_bonus_brief_text_limited(bonuses, 2)
			activations.append({
				"set_id": set_id,
				"set_name": str(set_row.get("name", set_id)),
				"count": need,
				"summary": summary,
			})
	activations.sort_custom(_sort_activation_count_asc)
	return activations

func get_active_set_bonuses(set_counts_override: Dictionary = {}) -> Dictionary:
	var stats := {
		"HP": 0,
		"ATK": 0,
		"DEF": 0,
		"QI": 0,
		"CRIT_PERCENT": 0,
		"LOOT_BONUS_PERCENT": 0,
	}
	var skills: Dictionary = {}
	var lines: Array[String] = []
	var set_counts: Dictionary = set_counts_override if not set_counts_override.is_empty() else get_set_counts(true)
	var sets := _equipment_sets_array()
	for set_any in sets:
		if not (set_any is Dictionary):
			continue
		var set_row: Dictionary = set_any
		var set_id := str(set_row.get("id", "")).strip_edges()
		if set_id.is_empty():
			continue
		var set_name := str(set_row.get("name", set_id))
		var max_pieces := maxi(1, int(set_row.get("max_pieces", 1)))
		var pieces := int(set_counts.get(set_id, 0))
		var thresholds := _normalize_set_thresholds(set_row.get("thresholds", []))
		var next_line := ""
		for th_any in thresholds:
			if not (th_any is Dictionary):
				continue
			var th: Dictionary = th_any
			var need := maxi(1, int(th.get("count", 1)))
			var bonuses_any: Variant = th.get("bonuses", [])
			var bonuses: Array = bonuses_any if bonuses_any is Array else []
			var bonus_text := _set_bonus_brief_text(bonuses)
			if pieces >= need:
				_apply_set_bonus(stats, skills, bonuses)
				if not bonus_text.is_empty():
					lines.append("%s %d/%d：%d件 %s（已激活）" % [set_name, pieces, max_pieces, need, bonus_text])
			elif next_line.is_empty():
				if bonus_text.is_empty():
					next_line = "%s：下一档 %d件" % [set_name, need]
				else:
					next_line = "%s：下一档 %d件 %s" % [set_name, need, bonus_text]
		if not next_line.is_empty():
			lines.append(next_line)

	return {
		"stats": stats,
		"skills": skills,
		"lines": lines,
	}

func get_active_set_bonuses_from_counts(set_counts: Dictionary) -> Dictionary:
	return get_active_set_bonuses(set_counts)

func _set_counts_from_store(store: Dictionary) -> Dictionary:
	var counts: Dictionary = {}
	for uid_any in store.keys():
		var inst_any = store.get(uid_any, {})
		if not (inst_any is Dictionary):
			continue
		var inst: Dictionary = inst_any
		var set_id := str(inst.get("set_id", "")).strip_edges()
		if set_id.is_empty():
			continue
		counts[set_id] = int(counts.get(set_id, 0)) + 1
	return counts

func _build_simulated_equipped_state(slot_key: String, candidate_inst: Dictionary) -> Dictionary:
	var override_equipped: Dictionary = {}
	var normalized_slot := slot_key.strip_edges()
	if not normalized_slot.is_empty() and equipped.has(normalized_slot):
		override_equipped[normalized_slot] = candidate_inst if not candidate_inst.is_empty() else {}
	return _build_override_equipped_state(override_equipped)

func _build_override_equipped_state(override_equipped: Dictionary) -> Dictionary:
	var sim_equipped: Dictionary = {}
	var sim_store: Dictionary = {}
	var synthetic_uid := -1
	for key_any in equipped.keys():
		var slot_key := str(key_any)
		var inst := _get_instance_for_slot_with_override(slot_key, override_equipped)
		if inst.is_empty():
			sim_equipped[slot_key] = 0
			continue
		inst = _ensure_socket_fields(inst)
		var uid := int(inst.get("uid", 0))
		if uid <= 0 or sim_store.has(uid):
			uid = synthetic_uid
			synthetic_uid -= 1
			inst["uid"] = uid
		sim_equipped[slot_key] = uid
		sim_store[uid] = inst
	return {
		"equipped": sim_equipped,
		"store": sim_store,
	}

func _get_instance_for_slot_with_override(slot_key: String, override_equipped: Dictionary) -> Dictionary:
	if override_equipped.has(slot_key):
		var override_any = override_equipped.get(slot_key, {})
		if override_any is Dictionary:
			return (override_any as Dictionary).duplicate(true)
		return {}
	return get_equipped_instance(slot_key)

func get_effective_main_val(inst: Dictionary) -> int:
	var main_stat := _normalize_stat_key(str(inst.get("main_stat", "")).strip_edges())
	if main_stat.is_empty():
		return int(inst.get("main_val", 0))
	var stats := get_instance_template_stats(inst)
	var base_val := int(stats.get(main_stat, 0))
	if base_val != 0:
		return base_val
	return int(inst.get("main_val", 0))

func get_all_effects(inst: Dictionary) -> Array[Dictionary]:
	var out: Array[Dictionary] = []
	var base_effects := _normalize_effects(inst.get("effects", []))
	var extra_effects := _normalize_effects(inst.get("extra_effects", []))
	out.append_array(base_effects)
	out.append_array(extra_effects)
	return out

func calc_score(inst: Dictionary) -> int:
	if not is_identified(inst):
		return -1

	var rarity := str(inst.get("rarity", "white"))
	var score := 0
	match rarity:
		"gold":
			score += 500
		"blue":
			score += 220
		_:
			score += 100

	var main_stat := _normalize_stat_key(str(inst.get("main_stat", "ATK")))
	var main_val := get_effective_main_val(inst)
	var main_w := 18
	if main_stat == "ATK" or main_stat == "DEF":
		main_w = 25
	elif main_stat == "HP":
		main_w = 12
	score += main_val * main_w

	score += get_star_level(inst) * 80

	var sockets := clampi(int(inst.get("sockets", 0)), 0, MAX_SOCKETS)
	score += sockets * 45
	var filled := 0
	var gems_any = inst.get("socket_gems", [])
	if gems_any is Array:
		for gem_any in (gems_any as Array):
			var gem_id := str(gem_any).strip_edges()
			if not gem_id.is_empty() and gem_id != "0":
				filled += 1
	score += filled * 25

	if not str(inst.get("set_id", "")).strip_edges().is_empty():
		score += 60

	for effect_any in get_all_effects(inst):
		if not (effect_any is Dictionary):
			continue
		var effect: Dictionary = effect_any
		var effect_type := str(effect.get("type", ""))
		var val := int(effect.get("val", 0))
		if val <= 0:
			continue
		if effect_type == "stat":
			var stat := _normalize_stat_key(str(effect.get("stat", "")))
			var w := 30
			if stat == "ATK":
				w = 70
			elif stat == "DEF":
				w = 55
			elif stat == "HP":
				w = 35
			elif stat == "CRIT_PERCENT":
				w = 45
			elif stat == "LOOT_BONUS_PERCENT":
				w = 30
			score += val * w
		elif effect_type == "skill_level":
			score += val * 90

	return maxi(0, score)

func get_equipped_total_score() -> int:
	var total := 0
	for uid_any in equipped_store.keys():
		var inst_any = equipped_store.get(uid_any, {})
		if not (inst_any is Dictionary):
			continue
		var inst: Dictionary = inst_any
		var score := calc_score(inst)
		if score > 0:
			total += score
	return total

# 固定模板制：最终属性 = 模板基础 + 星级成长（宝石/套装在总属性汇总时叠加）。
func get_instance_template_stats(inst: Dictionary) -> Dictionary:
	var out: Dictionary = {}
	if inst.is_empty():
		return out
	var template_id := str(inst.get("template_id", "")).strip_edges()
	var template := _find_template(template_id)
	var star_level := get_star_level(inst)
	if not template.is_empty():
		var base_stats_any = template.get("base_stats", {})
		if base_stats_any is Dictionary:
			var base_stats: Dictionary = base_stats_any
			for stat_any in base_stats.keys():
				var stat := _normalize_stat_key(str(stat_any))
				if stat.is_empty():
					continue
				out[stat] = int(base_stats.get(stat_any, 0))
		var growth_any = template.get("star_growth", {})
		if growth_any is Dictionary and star_level > 0:
			var growth: Dictionary = growth_any
			for stat_any in growth.keys():
				var stat := _normalize_stat_key(str(stat_any))
				if stat.is_empty():
					continue
				var add := int(growth.get(stat_any, 0)) * star_level
				if add == 0:
					continue
				out[stat] = int(out.get(stat, 0)) + add

	if out.is_empty():
		# 兼容旧实例：没有模板基础字段时退回旧主属性字段。
		var main_stat := _normalize_stat_key(str(inst.get("main_stat", "")).strip_edges())
		var legacy_main := int(inst.get("main_val", 0))
		if not main_stat.is_empty() and legacy_main != 0:
			out[main_stat] = legacy_main

	return out

func get_star_level(inst: Dictionary) -> int:
	var star_max := get_instance_star_max(inst)
	return clampi(int(inst.get("star_level", 0)), 0, star_max)

func get_instance_star_max(inst: Dictionary) -> int:
	var template_id := str(inst.get("template_id", "")).strip_edges()
	var template := _find_template(template_id)
	return _template_star_max(template)

func is_star_enabled(inst: Dictionary) -> bool:
	var template_id := str(inst.get("template_id", "")).strip_edges()
	var template := _find_template(template_id)
	return _template_star_enabled(template)

func can_star_up(uid: int) -> Dictionary:
	var result := {
		"can_star_up": false,
		"reason": "not_found",
		"current_star": 0,
		"target_star": 0,
		"star_max": STAR_MAX_DEFAULT,
		"required_gold": 0,
		"material_options": [],
		"owned_materials": {},
		"selected_option": {},
	}
	if uid <= 0:
		result["reason"] = "invalid"
		return result

	var inst := _get_instance_by_uid(uid)
	if inst.is_empty():
		return result
	var template_id := str(inst.get("template_id", "")).strip_edges()
	var template := _find_template(template_id)
	if template.is_empty():
		result["reason"] = "template_not_found"
		return result
	if not _template_star_enabled(template):
		result["reason"] = "star_disabled"
		return result

	var star_max := _template_star_max(template)
	var current_star := clampi(int(inst.get("star_level", 0)), 0, star_max)
	result["current_star"] = current_star
	result["star_max"] = star_max
	if current_star >= star_max:
		result["reason"] = "max"
		result["target_star"] = current_star
		return result
	result["target_star"] = current_star + 1

	var rule := _resolve_star_rule_for_instance(inst, template, current_star)
	if rule.is_empty():
		result["reason"] = "no_rule"
		return result
	var gold_cost := maxi(0, int(rule.get("gold_cost", 0)))
	result["required_gold"] = gold_cost
	var material_options := _normalize_material_options(rule.get("material_options", []))
	result["material_options"] = material_options
	if material_options.is_empty():
		result["reason"] = "no_material_option"
		return result

	var owned: Dictionary = {}
	for opt_any in material_options:
		if not (opt_any is Dictionary):
			continue
		var opt: Dictionary = opt_any
		var item_id := str(opt.get("item_id", "")).strip_edges()
		if item_id.is_empty():
			continue
		if not owned.has(item_id):
			owned[item_id] = InventoryModel.get_count(item_id)
	result["owned_materials"] = owned

	var selected := _pick_affordable_material_option(material_options, owned)
	if selected.is_empty():
		selected = material_options[0]
	result["selected_option"] = selected

	var has_gold := PlayerModel.can_spend_gold(gold_cost)
	var has_material := not _pick_affordable_material_option(material_options, owned).is_empty()
	if not has_gold:
		result["reason"] = "no_gold"
		return result
	if not has_material:
		result["reason"] = "no_material"
		return result
	result["can_star_up"] = true
	result["reason"] = ""
	return result

func get_star_up_preview(uid: int) -> Dictionary:
	var info := can_star_up(uid)
	var preview := info.duplicate(true)
	preview["ok"] = bool(info.get("can_star_up", false))
	preview["current_stats"] = {}
	preview["next_stats"] = {}
	var inst := _get_instance_by_uid(uid)
	if inst.is_empty():
		return preview
	var now_stats := get_instance_template_stats(inst)
	var next_inst := inst.duplicate(true)
	next_inst["star_level"] = clampi(get_star_level(inst) + 1, 0, get_instance_star_max(inst))
	var next_stats := get_instance_template_stats(next_inst)
	preview["current_stats"] = now_stats
	preview["next_stats"] = next_stats
	return preview

func do_star_up(uid: int) -> Dictionary:
	var result := {
		"ok": false,
		"reason": "invalid",
		"uid": uid,
		"current_star": 0,
		"target_star": 0,
		"required_gold": 0,
		"selected_option": {},
	}
	var info := can_star_up(uid)
	result["reason"] = str(info.get("reason", "invalid"))
	result["current_star"] = int(info.get("current_star", 0))
	result["target_star"] = int(info.get("target_star", 0))
	result["required_gold"] = int(info.get("required_gold", 0))
	result["selected_option"] = info.get("selected_option", {})
	if not bool(info.get("can_star_up", false)):
		return result

	var selected_any = info.get("selected_option", {})
	if not (selected_any is Dictionary):
		result["reason"] = "no_material"
		return result
	var selected: Dictionary = selected_any
	var item_id := str(selected.get("item_id", "")).strip_edges()
	var need_count := maxi(0, int(selected.get("count", 0)))
	if item_id.is_empty() or need_count <= 0:
		result["reason"] = "no_material"
		return result

	var gold_cost := maxi(0, int(info.get("required_gold", 0)))
	if not PlayerModel.spend_gold(gold_cost):
		result["reason"] = "no_gold"
		return result
	if not InventoryModel.consume_item(item_id, need_count, "system"):
		if gold_cost > 0:
			PlayerModel.add_gold(gold_cost)
		result["reason"] = "no_material"
		return result

	var inst := _get_instance_by_uid(uid)
	if inst.is_empty():
		InventoryModel.add_item(item_id, need_count, "system")
		if gold_cost > 0:
			PlayerModel.add_gold(gold_cost)
		result["reason"] = "not_found"
		return result
	inst["star_level"] = clampi(int(inst.get("star_level", 0)) + 1, 0, get_instance_star_max(inst))
	inst = _sync_legacy_main_fields(inst)
	if not _set_instance_by_uid(uid, inst):
		InventoryModel.add_item(item_id, need_count, "system")
		if gold_cost > 0:
			PlayerModel.add_gold(gold_cost)
		result["reason"] = "not_found"
		return result

	result["ok"] = true
	result["reason"] = ""
	result["current_star"] = clampi(int(inst.get("star_level", 0)) - 1, 0, get_instance_star_max(inst))
	result["target_star"] = int(inst.get("star_level", 0))
	EventBus.notify_inventory_updated()
	_request_save()
	return result

func upgrade_quality_from_base(base_uid: int, target_template_id: String) -> Dictionary:
	var result := {
		"ok": false,
		"reason": "invalid",
		"new_uid": 0,
	}
	if base_uid <= 0 or target_template_id.strip_edges().is_empty():
		return result
	var target_tpl := _find_template(target_template_id)
	if target_tpl.is_empty():
		result["reason"] = "target_template_not_found"
		return result

	var location := _find_instance_location(base_uid)
	var base_inst_any = location.get("inst", {})
	if not (base_inst_any is Dictionary):
		result["reason"] = "base_not_found"
		return result
	var base_inst: Dictionary = base_inst_any
	var from_bag := bool(location.get("in_bag", false))
	var bag_idx := int(location.get("bag_index", -1))
	var equipped_slot := str(location.get("equipped_slot", "")).strip_edges()

	var new_inst := create_instance(target_template_id)
	if new_inst.is_empty():
		result["reason"] = "create_failed"
		return result
	new_inst["star_level"] = mini(get_star_level(base_inst), _template_star_max(target_tpl))
	var target_max_sockets := _template_max_sockets(target_tpl)
	var base_sockets := clampi(int(base_inst.get("sockets", 0)), 0, MAX_SOCKETS)
	var sockets := mini(base_sockets, target_max_sockets)
	new_inst["sockets"] = sockets
	var base_gems := _normalize_socket_gems(base_inst.get("socket_gems", []), base_sockets)
	var inherited_gems: Array[String] = []
	inherited_gems.resize(sockets)
	for i in range(sockets):
		inherited_gems[i] = base_gems[i]
	new_inst["socket_gems"] = inherited_gems
	new_inst["locked"] = bool(base_inst.get("locked", false))
	# 升品继承只保留成长状态；鉴定状态不再沿用旧随机链路，目标装备默认已鉴定。
	new_inst["identified"] = true
	new_inst = _sync_legacy_main_fields(new_inst)

	if from_bag and bag_idx >= 0 and bag_idx < bag.size():
		bag.remove_at(bag_idx)
	elif not equipped_slot.is_empty():
		equipped_store.erase(base_uid)
		if equipped.has(equipped_slot):
			equipped[equipped_slot] = 0
	else:
		result["reason"] = "base_not_found"
		return result

	if not equipped_slot.is_empty() and equipped.has(equipped_slot):
		var new_uid := int(new_inst.get("uid", 0))
		equipped[equipped_slot] = new_uid
		equipped_store[new_uid] = new_inst
	else:
		bag.append(new_inst)

	result["ok"] = true
	result["reason"] = ""
	result["new_uid"] = int(new_inst.get("uid", 0))
	EventBus.notify_inventory_updated()
	_request_save()
	return result

func can_refine(inst: Dictionary) -> bool:
	return int(inst.get("refine_lv", 0)) < REFINE_MAX

func get_refine_cost(rarity: String) -> Dictionary:
	var defaults := {
		"white": {"gold": 15, "items": {"玉屑": 2}},
		"blue": {"gold": 40, "items": {"白玉碎": 2}},
		"gold": {"gold": 100, "items": {"白玉碎": 5, "妖核": 1}},
	}
	var fallback_any = defaults.get(rarity, defaults.get("white", {}))
	var fallback: Dictionary = fallback_any if fallback_any is Dictionary else {}
	var out := {
		"gold": maxi(0, int(fallback.get("gold", 0))),
		"items": {},
	}
	var fallback_items_any = fallback.get("items", {})
	if fallback_items_any is Dictionary:
		out["items"] = (fallback_items_any as Dictionary).duplicate(true)

	var cfg: Dictionary = ConfigService.get_cfg()
	var battle_any = cfg.get("battle", {})
	if not (battle_any is Dictionary):
		return out
	var economy_any = (battle_any as Dictionary).get("economy", {})
	if not (economy_any is Dictionary):
		return out
	var refine_any = (economy_any as Dictionary).get("refine_cost_by_rarity", {})
	if not (refine_any is Dictionary):
		return out
	var row_any = (refine_any as Dictionary).get(rarity, {})
	if not (row_any is Dictionary):
		return out
	var row: Dictionary = row_any
	out["gold"] = maxi(0, int(row.get("gold", out.get("gold", 0))))

	var cfg_items_any = row.get("items", {})
	var cfg_items: Dictionary = {}
	if cfg_items_any is Dictionary:
		for item_id_any in (cfg_items_any as Dictionary).keys():
			var item_id := str(item_id_any).strip_edges()
			var cnt := maxi(0, int((cfg_items_any as Dictionary).get(item_id_any, 0)))
			if item_id.is_empty() or cnt <= 0:
				continue
			cfg_items[item_id] = cnt
	out["items"] = cfg_items
	return out

func refine(uid: int) -> Dictionary:
	var result := {
		"ok": false,
		"reason": "not_found",
		"lv": 0,
		"cost": {"gold": 0, "items": {}},
		"new_effect": {},
	}
	if uid <= 0:
		result["reason"] = "invalid"
		return result

	var inst := _get_instance_by_uid(uid)
	if inst.is_empty():
		return result

	var lv := clampi(int(inst.get("refine_lv", 0)), 0, REFINE_MAX)
	if lv >= REFINE_MAX:
		result["reason"] = "max"
		result["lv"] = lv
		return result

	var rarity := str(inst.get("rarity", "white"))
	var cost := get_refine_cost(rarity)
	var gold_cost := maxi(0, int(cost.get("gold", 0)))
	result["cost"] = cost

	if not PlayerModel.can_spend_gold(gold_cost):
		result["reason"] = "no_gold"
		return result

	var cost_items_any = cost.get("items", {})
	var cost_items: Dictionary = cost_items_any if cost_items_any is Dictionary else {}
	for item_id_any in cost_items.keys():
		var item_id := str(item_id_any).strip_edges()
		var need := maxi(0, int(cost_items.get(item_id_any, 0)))
		if item_id.is_empty() or need <= 0:
			continue
		if InventoryModel.get_count(item_id) < need:
			result["reason"] = "no_items"
			result["need_items"] = cost_items.duplicate(true)
			return result

	if not PlayerModel.spend_gold(gold_cost):
		result["reason"] = "no_gold"
		return result

	var spent_items: Array[Dictionary] = []
	for item_id_any in cost_items.keys():
		var item_id := str(item_id_any).strip_edges()
		var need := maxi(0, int(cost_items.get(item_id_any, 0)))
		if item_id.is_empty() or need <= 0:
			continue
		if InventoryModel.consume_item(item_id, need, "system"):
			spent_items.append({"id": item_id, "count": need})
			continue
		for row_any in spent_items:
			if not (row_any is Dictionary):
				continue
			var row: Dictionary = row_any
			var rollback_id := str(row.get("id", "")).strip_edges()
			var rollback_cnt := maxi(0, int(row.get("count", 0)))
			if rollback_id.is_empty() or rollback_cnt <= 0:
				continue
			InventoryModel.add_item(rollback_id, rollback_cnt, "system")
		if gold_cost > 0:
			PlayerModel.add_gold(gold_cost)
		result["reason"] = "no_items"
		result["need_items"] = cost_items.duplicate(true)
		return result

	lv += 1
	inst["refine_lv"] = lv
	inst["main_min"] = int(inst.get("main_min", 0)) + 1
	inst["main_max"] = int(inst.get("main_max", inst.get("main_min", 0))) + 1
	inst["main_val"] = int(inst.get("main_val", 0)) + 1

	var new_effect: Dictionary = {}
	if lv == 3:
		new_effect = _add_one_extra_effect(inst)
	elif lv == 5:
		new_effect = _add_one_extra_effect(inst)

	if not _set_instance_by_uid(uid, inst):
		for row_any in spent_items:
			if not (row_any is Dictionary):
				continue
			var row: Dictionary = row_any
			var rollback_id := str(row.get("id", "")).strip_edges()
			var rollback_cnt := maxi(0, int(row.get("count", 0)))
			if rollback_id.is_empty() or rollback_cnt <= 0:
				continue
			InventoryModel.add_item(rollback_id, rollback_cnt, "system")
		if gold_cost > 0:
			PlayerModel.add_gold(gold_cost)
		result["reason"] = "not_found"
		return result

	result["ok"] = true
	result["reason"] = ""
	result["lv"] = lv
	result["new_effect"] = new_effect
	EventBus.notify_inventory_updated()
	_request_save()
	return result

func add_socket(uid: int) -> bool:
	if uid <= 0:
		return false
	var inst := _get_instance_by_uid(uid)
	if inst.is_empty():
		return false
	inst = _ensure_socket_fields(inst)
	var sockets := clampi(int(inst.get("sockets", 0)), 0, MAX_SOCKETS)
	if sockets >= MAX_SOCKETS:
		return false
	if InventoryModel.get_count("打孔石") < 1:
		return false
	if not InventoryModel.spend_item("打孔石", 1, "system"):
		return false
	var socket_gems := _normalize_socket_gems(inst.get("socket_gems", []), sockets)
	sockets += 1
	socket_gems.append("")
	inst["sockets"] = sockets
	inst["socket_gems"] = socket_gems
	if not _set_instance_by_uid(uid, inst):
		return false
	EventBus.notify_inventory_updated()
	_request_save()
	return true

func set_socket_gem(uid: int, socket_idx: int, gem_id: String) -> bool:
	if uid <= 0 or gem_id.is_empty():
		return false
	var inst := _get_instance_by_uid(uid)
	if inst.is_empty():
		return false
	inst = _ensure_socket_fields(inst)
	var sockets := clampi(int(inst.get("sockets", 0)), 0, MAX_SOCKETS)
	if socket_idx < 0 or socket_idx >= sockets:
		return false
	var socket_gems := _normalize_socket_gems(inst.get("socket_gems", []), sockets)
	if socket_gems[socket_idx] != "":
		return false
	if InventoryModel.get_count(gem_id) < 1:
		return false
	var item_def := _find_item_def(gem_id)
	if item_def.is_empty() or str(item_def.get("type", "")) != "gem":
		return false
	if not InventoryModel.spend_item(gem_id, 1, "system"):
		return false
	socket_gems[socket_idx] = gem_id
	inst["socket_gems"] = socket_gems
	if not _set_instance_by_uid(uid, inst):
		return false
	EventBus.notify_inventory_updated()
	_request_save()
	return true

func remove_socket_gem(uid: int, socket_idx: int) -> bool:
	if uid <= 0:
		return false
	var inst := _get_instance_by_uid(uid)
	if inst.is_empty():
		return false
	inst = _ensure_socket_fields(inst)
	var sockets := clampi(int(inst.get("sockets", 0)), 0, MAX_SOCKETS)
	if socket_idx < 0 or socket_idx >= sockets:
		return false
	var socket_gems := _normalize_socket_gems(inst.get("socket_gems", []), sockets)
	var gem_id := str(socket_gems[socket_idx])
	if gem_id.is_empty():
		return false
	socket_gems[socket_idx] = ""
	InventoryModel.add_item(gem_id, 1, "system")
	inst["socket_gems"] = socket_gems
	if not _set_instance_by_uid(uid, inst):
		return false
	EventBus.notify_inventory_updated()
	_request_save()
	return true

func can_upgrade(uid: int) -> Dictionary:
	var result := {
		"ok": false,
		"reason": "invalid",
		"cost": {},
		"next_tier": 0,
	}
	if uid <= 0:
		return result

	var inst := _get_instance_by_uid(uid)
	if inst.is_empty():
		result["reason"] = "not_found"
		return result

	var tier := clampi(int(inst.get("tier", 0)), 0, 2)
	if tier >= 2:
		result["reason"] = "max"
		return result

	var next_tier := tier + 1
	var slot_key := str(inst.get("slot", ""))
	var cost := _resolve_upgrade_recipe(slot_key, next_tier)
	result["next_tier"] = next_tier
	result["cost"] = cost
	if cost.is_empty():
		result["reason"] = "no_recipe"
		return result

	if not _has_materials(cost):
		result["reason"] = "lack"
		return result

	result["ok"] = true
	result["reason"] = ""
	return result

func upgrade_equipment(uid: int) -> bool:
	var info: Dictionary = can_upgrade(uid)
	if not bool(info.get("ok", false)):
		return false

	var inst := _get_instance_by_uid(uid)
	if inst.is_empty():
		return false

	var cost_any: Variant = info.get("cost", {})
	if not (cost_any is Dictionary):
		return false
	var cost: Dictionary = cost_any
	if not _has_materials(cost):
		return false

	var spent: Array[Dictionary] = []
	for mat_key_any in cost.keys():
		var mat_id := str(mat_key_any)
		var cnt := int(cost.get(mat_key_any, 0))
		if mat_id.is_empty() or cnt <= 0:
			continue
		if InventoryModel.spend_item(mat_id, cnt, "system"):
			spent.append({
				"id": mat_id,
				"count": cnt,
			})
		else:
			for entry_any in spent:
				if not (entry_any is Dictionary):
					continue
				var entry: Dictionary = entry_any
				var rollback_id := str(entry.get("id", ""))
				var rollback_cnt := int(entry.get("count", 0))
				if rollback_id.is_empty() or rollback_cnt <= 0:
					continue
				InventoryModel.add_item(rollback_id, rollback_cnt, "system")
			return false

	var old_tier := clampi(int(inst.get("tier", 0)), 0, 2)
	var new_tier := mini(2, old_tier + 1)
	inst["tier"] = new_tier
	if not _set_instance_by_uid(uid, inst):
		for entry_any in spent:
			if not (entry_any is Dictionary):
				continue
			var entry: Dictionary = entry_any
			var rollback_id := str(entry.get("id", ""))
			var rollback_cnt := int(entry.get("count", 0))
			if rollback_id.is_empty() or rollback_cnt <= 0:
				continue
			InventoryModel.add_item(rollback_id, rollback_cnt, "system")
		return false

	EventBus.notify_inventory_updated()
	_request_save()
	EventBus.add_log("装备进阶成功：%s -> %s" % [_tier_name(old_tier), _tier_name(new_tier)])
	return true

func list_bag_sorted() -> Array[Dictionary]:
	var rows: Array[Dictionary] = []
	for entry_any in bag:
			if entry_any is Dictionary:
				var row := _normalize_loaded_instance((entry_any as Dictionary).duplicate(true))
				if str(row.get("icon", "")).is_empty():
					var tpl: Dictionary = _find_template(str(row.get("template_id", "")))
					row["icon"] = str(tpl.get("icon", ""))
				rows.append(row)
	rows.sort_custom(_sort_bag_rows)
	return rows

func remove_from_bag(uid: int) -> bool:
	if uid <= 0:
		return false
	var idx := _find_bag_index(uid)
	if idx < 0:
		return false
	bag.remove_at(idx)
	_request_save()
	EventBus.notify_inventory_updated()
	return true

func get_equipped_instance(slot_key: String) -> Dictionary:
	if not equipped.has(slot_key):
		return {}
	var uid := int(equipped.get(slot_key, 0))
	if uid == 0:
		return {}
	var inst_any = equipped_store.get(uid, {})
	if inst_any is Dictionary:
		return (inst_any as Dictionary).duplicate(true)
	return {}

func get_equipped_uid(slot_key: String) -> int:
	return int(equipped.get(slot_key, 0))

func _default_equipped() -> Dictionary:
	return {
		"weapon": 0,
		"helm": 0,
		"armor": 0,
		"pants": 0,
		"shoes": 0,
		"cloak": 0,
		"ring1": 0,
		"ring2": 0,
		"bracelet1": 0,
		"bracelet2": 0,
	}

func _get_save_data() -> Dictionary:
	var equipped_items: Dictionary = {}
	for slot_key_any in equipped.keys():
		var slot_key := str(slot_key_any)
		var uid := int(equipped.get(slot_key, 0))
		if uid == 0:
			continue
		var inst_any = equipped_store.get(uid, {})
		if inst_any is Dictionary:
			equipped_items[slot_key] = (inst_any as Dictionary).duplicate(true)
	return {
		"next_uid": next_uid,
		"bag": bag.duplicate(true),
		"equipped_items": equipped_items,
	}

func _load_save() -> void:
	next_uid = 1
	bag = []
	equipped = _default_equipped()
	equipped_store = {}

	if FileAccess.file_exists(SAVE_PATH):
		var txt := FileAccess.get_file_as_string(SAVE_PATH)
		var parsed: Variant = JSON.parse_string(txt)
		if parsed is Dictionary:
			next_uid = int((parsed as Dictionary).get("next_uid", 1))
			var bag_any: Variant = (parsed as Dictionary).get("bag", [])
			if bag_any is Array:
				for e_any in bag_any:
					if e_any is Dictionary:
						bag.append(_normalize_loaded_instance((e_any as Dictionary).duplicate(true)))

			var eq_any: Variant = (parsed as Dictionary).get("equipped_items", {})
			if eq_any is Dictionary:
				for slot_key_any in (eq_any as Dictionary).keys():
					var slot_key := str(slot_key_any)
					if not equipped.has(slot_key):
						continue
					var inst_any = (eq_any as Dictionary).get(slot_key_any, {})
					if not (inst_any is Dictionary):
						continue
					var inst: Dictionary = _normalize_loaded_instance((inst_any as Dictionary).duplicate(true))
					var uid := int(inst.get("uid", 0))
					if uid <= 0:
						continue
					equipped[slot_key] = uid
					equipped_store[uid] = inst

	var max_uid := 0
	for e_any in bag:
		if e_any is Dictionary:
			max_uid = maxi(max_uid, int((e_any as Dictionary).get("uid", 0)))
	for uid_k in equipped_store.keys():
		max_uid = maxi(max_uid, int(uid_k))
	if next_uid <= max_uid:
		next_uid = max_uid + 1

	if has_node("/root/EquipDexModel"):
		var template_ids: Array[String] = []
		var seen: Dictionary = {}
		for e_any in bag:
			if not (e_any is Dictionary):
				continue
			var inst: Dictionary = e_any
			var tpl_id := str(inst.get("template_id", "")).strip_edges()
			if tpl_id.is_empty() or seen.has(tpl_id):
				continue
			seen[tpl_id] = true
			template_ids.append(tpl_id)
		for uid_any in equipped_store.keys():
			var inst_any = equipped_store.get(uid_any, {})
			if not (inst_any is Dictionary):
				continue
			var inst: Dictionary = inst_any
			var tpl_id := str(inst.get("template_id", "")).strip_edges()
			if tpl_id.is_empty() or seen.has(tpl_id):
				continue
			seen[tpl_id] = true
			template_ids.append(tpl_id)
		EquipDexModel.unlock_many(template_ids, false)

	EventBus.notify_inventory_updated()

func _request_save() -> void:
	_dirty = true
	if _save_timer != null:
		_save_timer.start()

func _flush_save() -> void:
	if not _dirty:
		return
	var f := FileAccess.open(SAVE_PATH, FileAccess.WRITE)
	if f:
		f.store_string(JSON.stringify(_get_save_data()))
	_dirty = false

func _notification(what: int) -> void:
	if what == NOTIFICATION_WM_CLOSE_REQUEST \
	or what == NOTIFICATION_APPLICATION_PAUSED \
	or what == NOTIFICATION_APPLICATION_FOCUS_OUT:
		_flush_save()

func _find_template(template_id: String) -> Dictionary:
	var cfg: Dictionary = ConfigService.get_cfg()
	var equip_db_any = cfg.get("equip_db", {})
	if not (equip_db_any is Dictionary):
		return {}
	var equip_db: Dictionary = equip_db_any
	var templates_any = equip_db.get("equip_templates", [])
	if not (templates_any is Array):
		return {}

	for t_any in templates_any:
		if not (t_any is Dictionary):
			continue
		var tpl: Dictionary = t_any
		if str(tpl.get("id", "")) == template_id:
			return tpl
	return {}

func get_template(template_id: String) -> Dictionary:
	return _find_template(template_id)

func _template_star_enabled(template: Dictionary) -> bool:
	if template.is_empty():
		return false
	if template.has("star_enabled"):
		return bool(template.get("star_enabled", false))
	return true

func _template_star_max(template: Dictionary) -> int:
	if template.is_empty():
		return STAR_MAX_DEFAULT
	var max_star := int(template.get("star_max", STAR_MAX_DEFAULT))
	return clampi(max_star, 0, STAR_MAX_DEFAULT)

func _template_max_sockets(template: Dictionary) -> int:
	if template.is_empty():
		return MAX_SOCKETS
	return clampi(int(template.get("max_sockets", MAX_SOCKETS)), 0, MAX_SOCKETS)

func _template_default_socket_count(template: Dictionary) -> int:
	if template.is_empty():
		return 0
	var default_count := int(template.get("default_socket_count", 0))
	return clampi(default_count, 0, _template_max_sockets(template))

func _template_main_value_for_star(template: Dictionary, main_stat: String, star_level: int) -> int:
	var stat := _normalize_stat_key(main_stat)
	if template.is_empty() or stat.is_empty():
		return 0
	var base := 0
	var base_stats_any = template.get("base_stats", {})
	if base_stats_any is Dictionary:
		base = int((base_stats_any as Dictionary).get(stat, 0))
	if base == 0:
		base = int(template.get("main_min", 0))
	var growth := 0
	var growth_any = template.get("star_growth", {})
	if growth_any is Dictionary:
		growth = int((growth_any as Dictionary).get(stat, 0))
	return base + maxi(0, star_level) * growth

func _sync_legacy_main_fields(inst: Dictionary) -> Dictionary:
	var out := inst.duplicate(true)
	var template_id := str(out.get("template_id", "")).strip_edges()
	var template := _find_template(template_id)
	var main_stat := str(out.get("main_stat", "")).strip_edges()
	if main_stat.is_empty():
		main_stat = str(template.get("main_stat", "")).strip_edges()
	out["main_stat"] = main_stat
	var star_level := clampi(int(out.get("star_level", 0)), 0, _template_star_max(template))
	out["star_level"] = star_level

	if not template.is_empty():
		out["name"] = str(template.get("name", out.get("name", template_id)))
		out["slot"] = str(template.get("slot", out.get("slot", "")))
		out["rarity"] = str(template.get("rarity", out.get("rarity", "white")))
		out["icon"] = str(template.get("icon", out.get("icon", "")))
		if str(out.get("set_id", "")).strip_edges().is_empty():
			out["set_id"] = str(template.get("set_id", ""))
		if not out.has("effects") or _normalize_effects(out.get("effects", [])).is_empty():
			out["effects"] = _normalize_effects(template.get("effects", []))

	var legacy_main := _template_main_value_for_star(template, main_stat, star_level)
	if legacy_main == 0:
		legacy_main = int(out.get("main_val", 0))
	out["main_val"] = legacy_main
	out["main_min"] = legacy_main
	out["main_max"] = legacy_main
	out = _ensure_socket_fields(out)
	return out

func _find_instance_location(uid: int) -> Dictionary:
	var bag_idx := _find_bag_index(uid)
	if bag_idx >= 0:
		var bag_any = bag[bag_idx]
		if bag_any is Dictionary:
			return {
				"in_bag": true,
				"bag_index": bag_idx,
				"equipped_slot": "",
				"inst": (bag_any as Dictionary).duplicate(true),
			}
	for slot_key_any in equipped.keys():
		var slot_key := str(slot_key_any)
		if int(equipped.get(slot_key, 0)) != uid:
			continue
		var inst_any = equipped_store.get(uid, {})
		if inst_any is Dictionary:
			return {
				"in_bag": false,
				"bag_index": -1,
				"equipped_slot": slot_key,
				"inst": (inst_any as Dictionary).duplicate(true),
			}
	return {}

func _star_rules_root() -> Dictionary:
	var cfg: Dictionary = ConfigService.get_cfg()
	var db_any = cfg.get("star_rules_db", {})
	if not (db_any is Dictionary):
		return {}
	var db: Dictionary = db_any
	var rules_any = db.get("star_rules", {})
	if rules_any is Dictionary:
		return rules_any
	return {}

func _resolve_star_tier_for_template(template: Dictionary) -> String:
	var explicit := str(template.get("star_rule_tier", "")).strip_edges()
	if not explicit.is_empty():
		return explicit
	var level := maxi(1, int(ProgressModel.level))
	var tiers_any = _star_rules_root().get("tiers", {})
	if not (tiers_any is Dictionary):
		return "T1"
	var tiers: Dictionary = tiers_any
	for tier_key_any in tiers.keys():
		var tier_key := str(tier_key_any)
		var tier_any = tiers.get(tier_key_any, {})
		if not (tier_any is Dictionary):
			continue
		var tier: Dictionary = tier_any
		var range_any = tier.get("level_range", [])
		if not (range_any is Array):
			continue
		var range: Array = range_any
		if range.size() < 2:
			continue
		var min_lv := int(range[0])
		var max_lv := int(range[1])
		if level >= min_lv and level <= max_lv:
			return tier_key
	return "T1"

func _resolve_star_rule_for_instance(inst: Dictionary, template: Dictionary, current_star: int) -> Dictionary:
	var rules := _star_rules_root()
	var tiers_any = rules.get("tiers", {})
	if not (tiers_any is Dictionary):
		return {}
	var tier_id := _resolve_star_tier_for_template(template)
	var tier_any = (tiers_any as Dictionary).get(tier_id, {})
	if not (tier_any is Dictionary):
		return {}
	var tier: Dictionary = tier_any
	var stages_any = tier.get("stages", {})
	if not (stages_any is Dictionary):
		return {}
	var stages: Dictionary = stages_any
	var slot_group := str(template.get("slot_group", _slot_group_from_slot(str(template.get("slot", ""))))).strip_edges()
	var stage_rows: Array[Dictionary] = []
	for stage_key_any in stages.keys():
		var stage_any = stages.get(stage_key_any, {})
		if not (stage_any is Dictionary):
			continue
		var stage: Dictionary = stage_any
		var row := stage.duplicate(true)
		row["_stage_key"] = str(stage_key_any)
		stage_rows.append(row)
	stage_rows.sort_custom(func(a: Dictionary, b: Dictionary) -> bool:
		var amin := int(a.get("min_star", 0))
		var bmin := int(b.get("min_star", 0))
		if amin != bmin:
			return amin < bmin
		var amax := int(a.get("max_star", -1))
		var bmax := int(b.get("max_star", -1))
		if amax != bmax:
			return amax < bmax
		return str(a.get("_stage_key", "")) < str(b.get("_stage_key", ""))
	)
	for stage in stage_rows:
		var min_star := int(stage.get("min_star", 0))
		var max_star := int(stage.get("max_star", -1))
		if current_star < min_star or (max_star >= 0 and current_star > max_star):
			continue
		var target_cap := int(stage.get("target_star_max", STAR_MAX_DEFAULT))
		if current_star + 1 > target_cap:
			continue
		var groups_any = stage.get("applicable_slot_groups", [])
		if groups_any is Array and not (groups_any as Array).is_empty():
			var groups: Array = groups_any
			if slot_group.is_empty() or groups.find(slot_group) == -1:
				continue
		return stage.duplicate(true)
	return {}

func _normalize_material_options(options_any: Variant) -> Array[Dictionary]:
	var out: Array[Dictionary] = []
	if not (options_any is Array):
		return out
	for option_any in (options_any as Array):
		if not (option_any is Dictionary):
			continue
		var option: Dictionary = option_any
		var item_id := str(option.get("item_id", "")).strip_edges()
		var count := maxi(0, int(option.get("count", 0)))
		if item_id.is_empty() or count <= 0:
			continue
		out.append({
			"item_id": item_id,
			"count": count,
		})
	return out

func _pick_affordable_material_option(options: Array[Dictionary], owned: Dictionary) -> Dictionary:
	for option in options:
		var item_id := str(option.get("item_id", "")).strip_edges()
		var need := maxi(0, int(option.get("count", 0)))
		if item_id.is_empty() or need <= 0:
			continue
		var have := maxi(0, int(owned.get(item_id, InventoryModel.get_count(item_id))))
		if have >= need:
			return option.duplicate(true)
	return {}

func _slot_group_from_slot(slot: String) -> String:
	var key := slot.strip_edges().to_lower()
	if key == "weapon":
		return "weapon"
	if key == "armor" or key == "pants" or key == "helm" or key == "shoes":
		return "armor"
	if key == "cloak":
		return "cloak"
	return "accessory"

func _equipment_sets_array() -> Array:
	var cfg: Dictionary = ConfigService.get_cfg()
	var db_any = cfg.get("equipment_sets_db", {})
	if not (db_any is Dictionary):
		return []
	var rows_any = (db_any as Dictionary).get("equipment_sets", [])
	if rows_any is Array:
		return rows_any
	return []

func _normalize_set_thresholds(thresholds_any: Variant) -> Array:
	var rows: Array[Dictionary] = []
	if not (thresholds_any is Array):
		return rows
	for th_any in (thresholds_any as Array):
		if not (th_any is Dictionary):
			continue
		var th_raw: Dictionary = th_any
		var count := int(th_raw.get("count", 0))
		if count < 1:
			continue
		var bonuses_any: Variant = th_raw.get("bonuses", [])
		var bonuses: Array = bonuses_any if bonuses_any is Array else []
		rows.append({
			"count": count,
			"bonuses": bonuses,
		})
	rows.sort_custom(_sort_threshold_count_asc)
	return rows

func _sort_threshold_count_asc(a: Dictionary, b: Dictionary) -> bool:
	return int(a.get("count", 0)) < int(b.get("count", 0))

func _sort_activation_count_asc(a: Dictionary, b: Dictionary) -> bool:
	var ca := int(a.get("count", 0))
	var cb := int(b.get("count", 0))
	if ca != cb:
		return ca < cb
	return str(a.get("set_id", "")) < str(b.get("set_id", ""))

func _bonuses_for_set_threshold(set_row: Dictionary, threshold_count: int) -> Array:
	var thresholds := _normalize_set_thresholds(set_row.get("thresholds", []))
	for th_any in thresholds:
		if not (th_any is Dictionary):
			continue
		var th: Dictionary = th_any
		if int(th.get("count", 0)) != threshold_count:
			continue
		var bonuses_any: Variant = th.get("bonuses", [])
		if bonuses_any is Array:
			return bonuses_any
		return []
	return []

func _apply_set_bonus(stats: Dictionary, skills: Dictionary, bonuses: Array) -> void:
	for bonus_any in bonuses:
		if not (bonus_any is Dictionary):
			continue
		var bonus: Dictionary = bonus_any
		var btype := str(bonus.get("type", ""))
		if btype == "stat":
			var stat := _normalize_stat_key(str(bonus.get("stat", "")))
			var val := int(bonus.get("val", 0))
			if stat.is_empty() or val == 0:
				continue
			stats[stat] = int(stats.get(stat, 0)) + val
		elif btype == "skill_level":
			var skill_id := str(bonus.get("skill_id", "")).strip_edges()
			var sval := int(bonus.get("val", 0))
			if skill_id.is_empty() or sval == 0:
				continue
			skills[skill_id] = int(skills.get(skill_id, 0)) + sval

func _set_bonus_brief_text(bonuses: Array) -> String:
	return _set_bonus_brief_text_limited(bonuses, -1)

func _set_bonus_brief_text_limited(bonuses: Array, max_entries: int = -1) -> String:
	var parts: Array[String] = []
	var rendered := 0
	var has_more := false
	for bonus_any in bonuses:
		if not (bonus_any is Dictionary):
			continue
		var bonus: Dictionary = bonus_any
		var btype := str(bonus.get("type", ""))
		var text := ""
		if btype == "stat":
			var stat := _normalize_stat_key(str(bonus.get("stat", "")))
			var val := int(bonus.get("val", 0))
			if stat.is_empty() or val == 0:
				continue
			var need_percent := stat == "CRIT_PERCENT" or stat == "LOOT_BONUS_PERCENT"
			text = "%s+%d%s" % [I18nService.stat(stat), val, "%" if need_percent else ""]
		elif btype == "skill_level":
			var skill_id := str(bonus.get("skill_id", "")).strip_edges()
			var sval := int(bonus.get("val", 0))
			if skill_id.is_empty() or sval == 0:
				continue
			text = "%s+%d级" % [_skill_display_name(skill_id), sval]
		else:
			continue

		if max_entries > 0 and rendered >= max_entries:
			has_more = true
			continue
		parts.append(text)
		rendered += 1

	if max_entries > 0 and has_more:
		parts.append("…")
	return "，".join(parts)

func _skill_display_name(skill_id: String) -> String:
	if skill_id.is_empty():
		return skill_id
	if has_node("/root/SkillNameService"):
		return SkillNameService.name(skill_id)
	return skill_id

func _upgrade_db() -> Dictionary:
	var cfg: Dictionary = ConfigService.get_cfg()
	var db_any = cfg.get("upgrade_db", {})
	if db_any is Dictionary:
		return db_any
	return {}

func _tier_name(tier: int) -> String:
	var names_any: Variant = _upgrade_db().get("tier_names", [])
	if names_any is Array:
		var names: Array = names_any
		if tier >= 0 and tier < names.size():
			return str(names[tier])
	match tier:
		1:
			return "灵"
		2:
			return "玄"
		_:
			return "凡"

func _resolve_upgrade_recipe(slot_key: String, next_tier: int) -> Dictionary:
	if slot_key.is_empty():
		return {}
	var db := _upgrade_db()
	var recipes_any: Variant = db.get("recipes", {})
	if not (recipes_any is Dictionary):
		return {}
	var recipes: Dictionary = recipes_any
	var slot_recipe_any: Variant = recipes.get(slot_key, {})
	if not (slot_recipe_any is Dictionary):
		return {}
	var slot_recipe: Dictionary = slot_recipe_any
	var tier_key := "to%d" % next_tier
	var cost_any: Variant = slot_recipe.get(tier_key, {})
	if not (cost_any is Dictionary):
		return {}
	var cost_raw: Dictionary = cost_any
	var cost: Dictionary = {}
	for key_any in cost_raw.keys():
		var item_id := str(key_any)
		var cnt := int(cost_raw.get(key_any, 0))
		if item_id.is_empty() or cnt <= 0:
			continue
		cost[item_id] = cnt
	return cost

func _has_materials(cost: Dictionary) -> bool:
	for key_any in cost.keys():
		var item_id := str(key_any)
		var need := int(cost.get(key_any, 0))
		if item_id.is_empty() or need <= 0:
			continue
		if InventoryModel.get_count(item_id) < need:
			return false
	return true

func _find_bag_index(uid: int) -> int:
	for i in bag.size():
		var entry_any = bag[i]
		if not (entry_any is Dictionary):
			continue
		var entry: Dictionary = entry_any
		if int(entry.get("uid", 0)) == uid:
			return i
	return -1

func _get_instance_by_uid(uid: int) -> Dictionary:
	var bag_idx := _find_bag_index(uid)
	if bag_idx >= 0:
		var bag_any = bag[bag_idx]
		if bag_any is Dictionary:
			return (bag_any as Dictionary).duplicate(true)
	if equipped_store.has(uid):
		var inst_any = equipped_store.get(uid, {})
		if inst_any is Dictionary:
			return (inst_any as Dictionary).duplicate(true)
	return {}

func _set_instance_by_uid(uid: int, inst: Dictionary) -> bool:
	var normalized := _normalize_loaded_instance(inst)
	var bag_idx := _find_bag_index(uid)
	if bag_idx >= 0:
		bag[bag_idx] = normalized
		return true
	if equipped_store.has(uid):
		equipped_store[uid] = normalized
		return true
	return false

func _resolve_slot_key_for_equip(slot: String) -> String:
	match slot:
		"ring":
			if int(equipped.get("ring1", 0)) == 0:
				return "ring1"
			if int(equipped.get("ring2", 0)) == 0:
				return "ring2"
			return "ring1"
		"bracelet":
			if int(equipped.get("bracelet1", 0)) == 0:
				return "bracelet1"
			if int(equipped.get("bracelet2", 0)) == 0:
				return "bracelet2"
			return "bracelet1"
		_:
			return slot if equipped.has(slot) else ""

func _normalize_stat_key(stat: String) -> String:
	match stat:
		"CRIT":
			return "CRIT_PERCENT"
		"DROP":
			return "LOOT_BONUS_PERCENT"
		_:
			return stat

func _roll_socket_count() -> int:
	var weights := _socket_weights_cfg()
	var total := 0
	for i in range(MAX_SOCKETS + 1):
		total += maxi(0, int(weights.get(i, 0)))
	if total <= 0:
		return 0
	var roll := randi() % total
	var acc := 0
	for i in range(MAX_SOCKETS + 1):
		acc += maxi(0, int(weights.get(i, 0)))
		if roll < acc:
			return i
	return 0

func _socket_weights_cfg() -> Dictionary:
	var out := {
		0: 60,
		1: 25,
		2: 10,
		3: 4,
		4: 1,
	}
	var cfg: Dictionary = ConfigService.get_cfg()
	var equip_db_any = cfg.get("equip_db", {})
	if not (equip_db_any is Dictionary):
		return out
	var socket_weights_any = (equip_db_any as Dictionary).get("socket_weights", {})
	if not (socket_weights_any is Dictionary):
		return out
	var socket_weights: Dictionary = socket_weights_any
	for key_any in socket_weights.keys():
		var k_str := str(key_any)
		var k := int(k_str)
		if k < 0 or k > MAX_SOCKETS:
			continue
		out[k] = maxi(0, int(socket_weights.get(key_any, out.get(k, 0))))
	return out

func _ensure_socket_fields(inst: Dictionary) -> Dictionary:
	var out := inst.duplicate(true)
	var sockets := clampi(int(out.get("sockets", 0)), 0, MAX_SOCKETS)
	out["sockets"] = sockets
	out["socket_gems"] = _normalize_socket_gems(out.get("socket_gems", []), sockets)
	return out

func _normalize_socket_gems(gems_any: Variant, sockets: int) -> Array[String]:
	var arr: Array[String] = []
	if gems_any is Array:
		for gem_any in (gems_any as Array):
			arr.append(str(gem_any))
	if arr.size() < sockets:
		for _i in range(sockets - arr.size()):
			arr.append("")
	elif arr.size() > sockets:
		arr.resize(sockets)
	return arr

func _apply_socket_gem_bonus_from_instance(totals: Dictionary, inst: Dictionary) -> void:
	var sockets := clampi(int(inst.get("sockets", 0)), 0, MAX_SOCKETS)
	if sockets <= 0:
		return
	var socket_gems := _normalize_socket_gems(inst.get("socket_gems", []), sockets)
	for gem_id in socket_gems:
		if gem_id.is_empty():
			continue
		var item_def := _find_item_def(gem_id)
		if item_def.is_empty():
			continue
		var effect_any: Variant = item_def.get("gem_effect", {})
		if not (effect_any is Dictionary):
			continue
		var effect: Dictionary = effect_any
		var stat := _normalize_stat_key(str(effect.get("stat", "")))
		var val := int(effect.get("val", 0))
		if val == 0:
			continue
		if stat != "HP" and stat != "ATK" and stat != "DEF" and stat != "LOOT_BONUS_PERCENT":
			continue
		totals[stat] = int(totals.get(stat, 0)) + val

func _apply_effect_stat_bonus(totals: Dictionary, inst: Dictionary) -> void:
	var effects := get_all_effects(inst)
	for effect in effects:
		if not (effect is Dictionary):
			continue
		var row: Dictionary = effect
		if str(row.get("type", "")) != "stat":
			continue
		var stat := _normalize_stat_key(str(row.get("stat", "")))
		var val := int(row.get("val", 0))
		if val == 0:
			continue
		if stat != "HP" and stat != "ATK" and stat != "DEF" and stat != "QI" and stat != "CRIT_PERCENT" and stat != "LOOT_BONUS_PERCENT":
			continue
		totals[stat] = int(totals.get(stat, 0)) + val

func _normalize_effects(effects_any: Variant) -> Array[Dictionary]:
	var out: Array[Dictionary] = []
	if not (effects_any is Array):
		return out
	for effect_any in effects_any:
		if not (effect_any is Dictionary):
			continue
		var effect: Dictionary = (effect_any as Dictionary).duplicate(true)
		var effect_type := str(effect.get("type", ""))
		if effect_type == "stat":
			var stat := _normalize_stat_key(str(effect.get("stat", "")))
			var val := int(effect.get("val", 0))
			if stat.is_empty() or val == 0:
				continue
			effect["stat"] = stat
			effect["val"] = val
			out.append(effect)
		elif effect_type == "skill_level":
			var skill_id := str(effect.get("skill_id", ""))
			var bonus := int(effect.get("val", 0))
			if skill_id.is_empty() or bonus == 0:
				continue
			effect["skill_id"] = skill_id
			effect["val"] = bonus
			out.append(effect)
	return out

func _identify_cost_for_rarity(rarity: String) -> int:
	var defaults := {
		"white": 20,
		"blue": 60,
		"gold": 160,
	}
	var cfg: Dictionary = ConfigService.get_cfg()
	var battle_any = cfg.get("battle", {})
	if battle_any is Dictionary:
		var economy_any = (battle_any as Dictionary).get("economy", {})
		if economy_any is Dictionary:
				var map_any = (economy_any as Dictionary).get("identify_cost_by_rarity", {})
				if map_any is Dictionary:
					return maxi(0, int((map_any as Dictionary).get(rarity, defaults.get(rarity, 20))))
	return int(defaults.get(rarity, 20))

func _salvage_reward_for_rarity(rarity: String) -> Dictionary:
	var defaults := {
		"white": {"gold": 8, "items": {"玉屑": 1}},
		"blue": {"gold": 20, "items": {"白玉碎": 1}},
		"gold": {"gold": 50, "items": {"白玉碎": 2, "妖核": 1}},
	}
	var fallback_any = defaults.get(rarity, defaults.get("white", {}))
	var fallback: Dictionary = fallback_any if fallback_any is Dictionary else {}
	var out := {
		"gold": maxi(0, int(fallback.get("gold", 0))),
		"items": {},
	}
	var fallback_items_any = fallback.get("items", {})
	if fallback_items_any is Dictionary:
		out["items"] = (fallback_items_any as Dictionary).duplicate(true)

	var cfg: Dictionary = ConfigService.get_cfg()
	var battle_any = cfg.get("battle", {})
	if not (battle_any is Dictionary):
		return out
	var economy_any = (battle_any as Dictionary).get("economy", {})
	if not (economy_any is Dictionary):
		return out
	var salvage_any = (economy_any as Dictionary).get("salvage_reward_by_rarity", {})
	if not (salvage_any is Dictionary):
		return out
	var row_any = (salvage_any as Dictionary).get(rarity, {})
	if not (row_any is Dictionary):
		return out
	var row: Dictionary = row_any
	out["gold"] = maxi(0, int(row.get("gold", out.get("gold", 0))))

	var cfg_items_any = row.get("items", {})
	var cfg_items: Dictionary = {}
	if cfg_items_any is Dictionary:
		for item_id_any in (cfg_items_any as Dictionary).keys():
			var item_id := str(item_id_any).strip_edges()
			var cnt := maxi(0, int((cfg_items_any as Dictionary).get(item_id_any, 0)))
			if item_id.is_empty() or cnt <= 0:
				continue
			cfg_items[item_id] = cnt
	out["items"] = cfg_items
	return out

func _is_uid_equipped(uid: int) -> bool:
	if uid <= 0:
		return false
	for slot_key_any in equipped.keys():
		if int(equipped.get(slot_key_any, 0)) == uid:
			return true
	return false

func _pop_bag_instance(uid: int) -> Dictionary:
	if uid <= 0:
		return {}
	var idx := _find_bag_index(uid)
	if idx < 0:
		return {}
	var inst_any = bag[idx]
	bag.remove_at(idx)
	if inst_any is Dictionary:
		return inst_any
	return {}

func _normalize_loaded_instance(inst: Dictionary) -> Dictionary:
	var out := _ensure_socket_fields(inst)
	var template_id := str(out.get("template_id", "")).strip_edges()
	var tpl := _find_template(template_id)
	var current_set := str(out.get("set_id", "")).strip_edges()
	if current_set.is_empty():
		out["set_id"] = str(tpl.get("set_id", ""))
	out["identified"] = bool(out.get("identified", true))
	out["locked"] = bool(out.get("locked", false))
	out["star_level"] = clampi(int(out.get("star_level", 0)), 0, _template_star_max(tpl))
	out["refine_lv"] = clampi(int(out.get("refine_lv", 0)), 0, REFINE_MAX)
	out["effects"] = _normalize_effects(out.get("effects", []))
	out["extra_effects"] = _normalize_effects(out.get("extra_effects", []))
	if str(out.get("icon", "")).is_empty():
		out["icon"] = str(tpl.get("icon", ""))
	out = _sync_legacy_main_fields(out)
	return out

func _add_one_extra_effect(inst: Dictionary) -> Dictionary:
	var existing: Dictionary = {}
	for old_any in get_all_effects(inst):
		if not (old_any is Dictionary):
			continue
		var old_row: Dictionary = old_any
		existing[_effect_key(old_row)] = true

	var pool := _get_refine_effect_pool()
	var picked: Dictionary = {}
	for _i in range(5):
		var row := _pick_weighted_effect(pool)
		if row.is_empty():
			continue
		var key := _effect_key(row)
		picked = row
		if not existing.has(key):
			break
	if picked.is_empty():
		return {}

	var effect_type := str(picked.get("type", ""))
	var clean := {
		"type": effect_type,
		"val": int(picked.get("val", 0)),
	}
	if effect_type == "stat":
		clean["stat"] = str(picked.get("stat", ""))
	elif effect_type == "skill_level":
		clean["skill_id"] = str(picked.get("skill_id", ""))

	var normalized_pick := _normalize_effects([clean])
	if normalized_pick.is_empty():
		return {}
	var extras_any = inst.get("extra_effects", [])
	var extras: Array[Dictionary] = []
	if extras_any is Array:
		extras = _normalize_effects(extras_any)
	extras.append(normalized_pick[0])
	inst["extra_effects"] = extras
	return normalized_pick[0]

func _effect_key(e: Dictionary) -> String:
	var effect_type := str(e.get("type", ""))
	if effect_type == "stat":
		return "stat:%s" % str(e.get("stat", ""))
	if effect_type == "skill_level":
		return "skill:%s" % str(e.get("skill_id", ""))
	return effect_type

func _get_refine_effect_pool() -> Array[Dictionary]:
	var out: Array[Dictionary] = []
	var cfg: Dictionary = ConfigService.get_cfg()
	var battle_any = cfg.get("battle", {})
	if battle_any is Dictionary:
		var battle: Dictionary = battle_any
		var pool_any = battle.get("refine_effect_pool", [])
		var normalized := _normalize_weighted_refine_pool(pool_any)
		if not normalized.is_empty():
			return normalized
	return _default_refine_effect_pool()

func _default_refine_effect_pool() -> Array[Dictionary]:
	return _normalize_weighted_refine_pool([
		{"w": 40, "type": "stat", "stat": "ATK", "val": 1},
		{"w": 30, "type": "stat", "stat": "DEF", "val": 1},
		{"w": 20, "type": "stat", "stat": "HP", "val": 2},
		{"w": 7, "type": "stat", "stat": "LOOT_BONUS_PERCENT", "val": 1},
		{"w": 3, "type": "skill_level", "skill_id": "BING_01", "val": 1},
	])

func _normalize_weighted_refine_pool(pool_any: Variant) -> Array[Dictionary]:
	var out: Array[Dictionary] = []
	if not (pool_any is Array):
		return out
	var total_w := 0
	for row_any in (pool_any as Array):
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		var w := maxi(0, int(row.get("w", 0)))
		if w <= 0:
			continue
		var normalized := _normalize_effects([row])
		if normalized.is_empty():
			continue
		var effect: Dictionary = normalized[0].duplicate(true)
		effect["w"] = w
		out.append(effect)
		total_w += w
	if total_w <= 0:
		return []
	return out

func _pick_weighted_effect(pool: Array[Dictionary]) -> Dictionary:
	if pool.is_empty():
		return {}
	var total_w := 0
	for row in pool:
		total_w += maxi(0, int(row.get("w", 0)))
	if total_w <= 0:
		return {}
	var r := randi() % total_w
	var acc := 0
	for row in pool:
		var w := maxi(0, int(row.get("w", 0)))
		if w <= 0:
			continue
		acc += w
		if r < acc:
			var picked := row.duplicate(true)
			picked.erase("w")
			return picked
	var fallback := pool[0].duplicate(true)
	fallback.erase("w")
	return fallback

func _find_item_def(item_id: String) -> Dictionary:
	if item_id.is_empty():
		return {}
	var cfg: Dictionary = ConfigService.get_cfg()
	var items_db_any = cfg.get("items_db", {})
	if not (items_db_any is Dictionary):
		return {}
	var items_any = (items_db_any as Dictionary).get("items", [])
	if not (items_any is Array):
		return {}
	for item_any in items_any:
		if not (item_any is Dictionary):
			continue
		var item_def: Dictionary = item_any
		if str(item_def.get("id", "")) == item_id:
			return item_def
	return {}

func _sort_bag_rows(a: Dictionary, b: Dictionary) -> bool:
	var rank_a := _rarity_rank(str(a.get("rarity", "white")))
	var rank_b := _rarity_rank(str(b.get("rarity", "white")))
	if rank_a != rank_b:
		return rank_a > rank_b
	return str(a.get("name", "")) < str(b.get("name", ""))

func _rarity_rank(rarity: String) -> int:
	match rarity:
		"gold":
			return 3
		"blue":
			return 2
		_:
			return 1
