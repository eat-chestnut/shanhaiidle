extends Node

const StatsServiceRef := preload("res://services/StatsService.gd")
const SAVE_PATH := "user://equipment.json"
const MAX_SOCKETS := 4

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

	var stat_min: int = int(template.get("main_min", 0))
	var stat_max: int = int(template.get("main_max", stat_min))
	if stat_max < stat_min:
		stat_max = stat_min
	var main_val := randi_range(stat_min, stat_max)
	var sockets := _roll_socket_count()
	var effects := _normalize_effects(template.get("effects", []))
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
		"main_stat": str(template.get("main_stat", "")),
		"main_min": stat_min,
		"main_max": stat_max,
		"main_val": main_val,
		"tier": 0,
		"effects": effects,
		"sockets": sockets,
		"socket_gems": socket_gems,
		"icon": str(template.get("icon", "")),
	}
	next_uid += 1
	return instance

func add_equip(template_id: String, source: String = "online") -> void:
	var inst := create_instance(template_id)
	if inst.is_empty():
		return
	bag.append(inst)
	if source == "online":
		PerfTracker.record_equip_gain(template_id, 1)
	EventBus.notify_inventory_updated()
	_request_save()

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

	for slot_key_any in equipped.keys():
		var slot_key := str(slot_key_any)
		var uid := int(equipped.get(slot_key, 0))
		if uid == 0:
			continue
		var inst_any = equipped_store.get(uid, {})
		if not (inst_any is Dictionary):
			continue
		var inst: Dictionary = inst_any
		inst = _ensure_socket_fields(inst)
		var main_stat := str(inst.get("main_stat", ""))
		var main_val := get_effective_main_val(inst)
		if main_stat.is_empty() or main_val == 0:
			pass
		else:
			var normalized := _normalize_stat_key(main_stat)
			totals[normalized] = int(totals.get(normalized, 0)) + main_val
		_apply_effect_stat_bonus(totals, inst)
		_apply_socket_gem_bonus_from_instance(totals, inst)

	var gem_bonus: Dictionary = GemSlotModel.get_bonus()
	for key in ["HP", "ATK", "DEF", "LOOT_BONUS_PERCENT"]:
		totals[key] = int(totals.get(key, 0)) + int(gem_bonus.get(key, 0))

	totals["CRIT"] = int(totals.get("CRIT_PERCENT", 0))
	totals["DROP"] = int(totals.get("LOOT_BONUS_PERCENT", 0))
	return totals

func get_effective_main_val(inst: Dictionary) -> int:
	var base := int(inst.get("main_val", 0))
	var tier := clampi(int(inst.get("tier", 0)), 0, 2)
	var upgrade_db := _upgrade_db()
	var tier_bonus_any: Variant = upgrade_db.get("tier_bonus", {})
	if not (tier_bonus_any is Dictionary):
		return base
	var tier_bonus: Dictionary = tier_bonus_any
	var bonus := int(tier_bonus.get(str(tier), tier_bonus.get(tier, 0)))
	return base + bonus

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
			var row := (entry_any as Dictionary).duplicate(true)
			if str(row.get("icon", "")).is_empty():
				var tpl: Dictionary = _find_template(str(row.get("template_id", "")))
				row["icon"] = str(tpl.get("icon", ""))
			rows.append(row)
	rows.sort_custom(_sort_bag_rows)
	return rows

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
						bag.append((e_any as Dictionary).duplicate(true))

			var eq_any: Variant = (parsed as Dictionary).get("equipped_items", {})
			if eq_any is Dictionary:
				for slot_key_any in (eq_any as Dictionary).keys():
					var slot_key := str(slot_key_any)
					if not equipped.has(slot_key):
						continue
					var inst_any = (eq_any as Dictionary).get(slot_key_any, {})
					if not (inst_any is Dictionary):
						continue
					var inst: Dictionary = (inst_any as Dictionary).duplicate(true)
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
	var bag_idx := _find_bag_index(uid)
	if bag_idx >= 0:
		bag[bag_idx] = inst
		return true
	if equipped_store.has(uid):
		equipped_store[uid] = inst
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
	var effects := _normalize_effects(inst.get("effects", []))
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
