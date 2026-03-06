extends Node

var items: Dictionary = {}

func add_item(item_id: String, count: int = 1) -> void:
	if item_id.is_empty() or count <= 0:
		return
	items[item_id] = int(items.get(item_id, 0)) + count
	EventBus.notify_inventory_updated()

func get_count(item_id: String) -> int:
	return int(items.get(item_id, 0))

func list_items_sorted() -> Array[Dictionary]:
	var result: Array[Dictionary] = []
	var defs := _build_item_defs()

	for item_id_any in items.keys():
		var item_id := str(item_id_any)
		var count := int(items.get(item_id, 0))
		if count <= 0:
			continue

		var item_def: Dictionary = defs.get(item_id, {})
		result.append({
			"id": item_id,
			"name": str(item_def.get("name", item_id)),
			"rarity": str(item_def.get("rarity", "white")),
			"count": count,
		})

	result.sort_custom(_sort_item_rows)
	return result

func _build_item_defs() -> Dictionary:
	var defs: Dictionary = {}
	var cfg: Dictionary = ConfigService.get_cfg()
	var items_db_any = cfg.get("items_db", {})
	if not (items_db_any is Dictionary):
		return defs

	var items_any = items_db_any.get("items", [])
	if not (items_any is Array):
		return defs

	for item_any in items_any:
		if not (item_any is Dictionary):
			continue
		var item_def: Dictionary = item_any
		var item_id := str(item_def.get("id", ""))
		if item_id.is_empty():
			continue
		defs[item_id] = item_def
	return defs

func _sort_item_rows(a: Dictionary, b: Dictionary) -> bool:
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
