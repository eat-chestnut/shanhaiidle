extends Node

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

func create_instance(template_id: String) -> Dictionary:
	var template := _find_template(template_id)
	if template.is_empty():
		return {}

	var stat_min: int = int(template.get("main_min", 0))
	var stat_max: int = int(template.get("main_max", stat_min))
	if stat_max < stat_min:
		stat_max = stat_min
	var main_val := randi_range(stat_min, stat_max)

	var instance := {
		"uid": next_uid,
		"template_id": template_id,
		"name": str(template.get("name", template_id)),
		"slot": str(template.get("slot", "")),
		"rarity": str(template.get("rarity", "white")),
		"main_stat": str(template.get("main_stat", "")),
		"main_val": main_val,
	}
	next_uid += 1
	return instance

func add_equip(template_id: String) -> void:
	var inst := create_instance(template_id)
	if inst.is_empty():
		return
	bag.append(inst)
	EventBus.notify_inventory_updated()

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

func get_total_stats() -> Dictionary:
	var totals := {
		"HP": 200,
		"ATK": 12,
		"DEF": 4,
		"CRIT": 0,
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
		var main_stat := str(inst.get("main_stat", ""))
		var main_val := int(inst.get("main_val", 0))
		if main_stat.is_empty() or main_val == 0:
			continue
		totals[main_stat] = int(totals.get(main_stat, 0)) + main_val

	return totals

func list_bag_sorted() -> Array[Dictionary]:
	var rows: Array[Dictionary] = []
	for entry_any in bag:
		if entry_any is Dictionary:
			rows.append((entry_any as Dictionary).duplicate(true))
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

func _find_bag_index(uid: int) -> int:
	for i in bag.size():
		var entry_any = bag[i]
		if not (entry_any is Dictionary):
			continue
		var entry: Dictionary = entry_any
		if int(entry.get("uid", 0)) == uid:
			return i
	return -1

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
