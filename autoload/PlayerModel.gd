extends Node

var gold: int = 0
var materials: Dictionary = {}
var aoe_stone_count: int = 0

func _ready() -> void:
	var d := SaveService.get_section("player")
	apply_save_data(d)

func add_gold(v: int) -> void:
	if v == 0:
		return
	gold = maxi(0, gold + v)
	EventBus.notify_inventory_updated()
	SaveService.set_section("player", get_save_data())

func add_material(item_id: String, count: int = 1) -> void:
	if item_id.is_empty() or count <= 0:
		return
	materials[item_id] = int(materials.get(item_id, 0)) + count
	EventBus.notify_inventory_updated()
	SaveService.set_section("player", get_save_data())

func set_aoe_stone_count(v: int) -> void:
	aoe_stone_count = maxi(0, v)
	EventBus.notify_inventory_updated()
	SaveService.set_section("player", get_save_data())

func get_save_data() -> Dictionary:
	return {
		"gold": gold,
		"materials": materials.duplicate(true),
		"aoe_stone_count": aoe_stone_count,
	}

func apply_save_data(d: Dictionary) -> void:
	gold = maxi(0, int(d.get("gold", 0)))
	materials = {}
	var raw_materials: Variant = d.get("materials", {})
	if raw_materials is Dictionary:
		for key_any in (raw_materials as Dictionary).keys():
			var item_id := str(key_any)
			if item_id.is_empty():
				continue
			var cnt := int((raw_materials as Dictionary).get(key_any, 0))
			if cnt > 0:
				materials[item_id] = cnt
	aoe_stone_count = maxi(0, int(d.get("aoe_stone_count", 0)))
	EventBus.emit_signal("inventory_updated")
