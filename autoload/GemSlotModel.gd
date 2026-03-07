extends Node

const SAVE_PATH := "user://gems.json"
const SLOT_COUNT := 4

var slots: Array[String] = ["", "", "", ""]

func _ready() -> void:
	self.load()

func load() -> void:
	slots = []
	slots.resize(SLOT_COUNT)
	for i in range(SLOT_COUNT):
		slots[i] = ""
	if not FileAccess.file_exists(SAVE_PATH):
		return
	var txt := FileAccess.get_file_as_string(SAVE_PATH)
	var parsed: Variant = JSON.parse_string(txt)
	if not (parsed is Dictionary):
		return
	var arr_any: Variant = (parsed as Dictionary).get("slots", [])
	if not (arr_any is Array):
		return
	var arr: Array = arr_any
	for i in range(mini(SLOT_COUNT, arr.size())):
		slots[i] = str(arr[i])

func save() -> void:
	var f := FileAccess.open(SAVE_PATH, FileAccess.WRITE)
	if f == null:
		push_warning("GemSlotModel: failed to open save file")
		return
	f.store_string(JSON.stringify({"slots": slots}))

func get_slots() -> Array[String]:
	return slots.duplicate()

func insert(slot_idx: int, gem_id: String) -> bool:
	if slot_idx < 0 or slot_idx >= SLOT_COUNT:
		return false
	if gem_id.is_empty():
		return false
	if slots[slot_idx] != "":
		return false
	if InventoryModel.get_count(gem_id) < 1:
		return false
	var item_def := _find_item_def(gem_id)
	if item_def.is_empty():
		return false
	if str(item_def.get("type", "")) != "gem":
		return false
	if not InventoryModel.spend_item(gem_id, 1, "system"):
		return false
	slots[slot_idx] = gem_id
	save()
	EventBus.notify_inventory_updated()
	return true

func remove(slot_idx: int) -> bool:
	if slot_idx < 0 or slot_idx >= SLOT_COUNT:
		return false
	var gem_id := slots[slot_idx]
	if gem_id.is_empty():
		return false
	slots[slot_idx] = ""
	InventoryModel.add_item(gem_id, 1, "system")
	save()
	EventBus.notify_inventory_updated()
	return true

func get_bonus() -> Dictionary:
	var totals := {
		"HP": 0,
		"ATK": 0,
		"DEF": 0,
		"LOOT_BONUS_PERCENT": 0,
	}
	for gem_id in slots:
		if gem_id.is_empty():
			continue
		var item_def := _find_item_def(gem_id)
		if item_def.is_empty():
			continue
		var effect_any: Variant = item_def.get("gem_effect", {})
		if not (effect_any is Dictionary):
			continue
		var effect: Dictionary = effect_any
		var stat := str(effect.get("stat", ""))
		if stat.is_empty():
			continue
		var val := int(effect.get("val", 0))
		if val == 0:
			continue
		totals[stat] = int(totals.get(stat, 0)) + val
	return totals

func _find_item_def(item_id: String) -> Dictionary:
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
