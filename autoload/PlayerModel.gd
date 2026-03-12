extends Node

var gold: int = 0
var spirit_stone: int = 0
var sect_contribution: int = 0
var stamina: int = 120
var stamina_cap: int = 120
var materials: Dictionary = {}
var aoe_stone_count: int = 0

func _ready() -> void:
	var d := SaveService.get_section("player")
	apply_save_data(d)

func add_gold(v: int, emit_update: bool = true) -> void:
	if v == 0:
		return
	gold = maxi(0, gold + v)
	if emit_update:
		EventBus.notify_inventory_updated()
	SaveService.set_section("player", get_save_data())

func add_spirit_stone(v: int, emit_update: bool = true) -> void:
	if v == 0:
		return
	spirit_stone = maxi(0, spirit_stone + v)
	if emit_update:
		EventBus.notify_inventory_updated()
	SaveService.set_section("player", get_save_data())

func spend_spirit_stone(n: int) -> bool:
	if n <= 0:
		return true
	if spirit_stone < n:
		return false
	spirit_stone -= n
	EventBus.notify_inventory_updated()
	SaveService.set_section("player", get_save_data())
	return true

func add_sect_contribution(v: int, emit_update: bool = true) -> void:
	if v == 0:
		return
	sect_contribution = maxi(0, sect_contribution + v)
	if emit_update:
		EventBus.notify_inventory_updated()
	SaveService.set_section("player", get_save_data())

func spend_sect_contribution(n: int) -> bool:
	if n <= 0:
		return true
	if sect_contribution < n:
		return false
	sect_contribution -= n
	EventBus.notify_inventory_updated()
	SaveService.set_section("player", get_save_data())
	return true

func add_stamina(v: int, emit_update: bool = true) -> void:
	if v == 0:
		return
	stamina = clampi(stamina + v, 0, maxi(1, stamina_cap))
	if emit_update:
		EventBus.notify_inventory_updated()
	SaveService.set_section("player", get_save_data())

func can_spend_stamina(n: int) -> bool:
	return n <= 0 or stamina >= n

func spend_stamina(n: int) -> bool:
	if n <= 0:
		return true
	if stamina < n:
		return false
	stamina -= n
	EventBus.notify_inventory_updated()
	SaveService.set_section("player", get_save_data())
	return true

func can_spend_gold(n: int) -> bool:
	return n <= 0 or gold >= n

func spend_gold(n: int) -> bool:
	if n <= 0:
		return true
	if gold < n:
		return false
	gold -= n
	EventBus.notify_inventory_updated()
	SaveService.set_section("player", get_save_data())
	return true

func get_currency_amount(currency_type: String) -> int:
	match currency_type:
		"gold":
			return maxi(0, gold)
		"crystal":
			return maxi(0, spirit_stone)
		"contribution":
			return maxi(0, sect_contribution)
		_:
			return 0

func apply_shop_currency_snapshot(snapshot_any: Variant) -> void:
	if not (snapshot_any is Dictionary):
		return
	var snapshot: Dictionary = snapshot_any
	gold = maxi(0, int(snapshot.get("gold", gold)))
	spirit_stone = maxi(0, int(snapshot.get("crystal", spirit_stone)))
	sect_contribution = maxi(0, int(snapshot.get("contribution", sect_contribution)))
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
		"spirit_stone": spirit_stone,
		"sect_contribution": sect_contribution,
		"stamina": stamina,
		"stamina_cap": stamina_cap,
		"materials": materials.duplicate(true),
		"aoe_stone_count": aoe_stone_count,
	}

func apply_save_data(d: Dictionary) -> void:
	gold = maxi(0, int(d.get("gold", 0)))
	spirit_stone = maxi(0, int(d.get("spirit_stone", 0)))
	sect_contribution = maxi(0, int(d.get("sect_contribution", 0)))
	stamina_cap = maxi(1, int(d.get("stamina_cap", 120)))
	stamina = clampi(maxi(0, int(d.get("stamina", stamina_cap))), 0, stamina_cap)
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
