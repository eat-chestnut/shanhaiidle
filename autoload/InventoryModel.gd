extends Node

const SAVE_PATH := "user://inventory.json"

var items: Dictionary = {}
var _dirty := false
var _save_timer: Timer

func _ready() -> void:
	_save_timer = Timer.new()
	_save_timer.one_shot = true
	_save_timer.wait_time = 0.6
	add_child(_save_timer)
	_save_timer.timeout.connect(_flush_save)
	_load_save()

func add_item(item_id: String, count: int = 1, source: String = "online", emit_update: bool = true) -> void:
	if item_id.is_empty() or count <= 0:
		return
	add_items_bulk({item_id: count}, source, emit_update)

func add_items_bulk(rewards: Dictionary, source: String = "online", emit_update: bool = true) -> void:
	var changed := false
	for item_id_any in rewards.keys():
		var item_id := str(item_id_any).strip_edges()
		var count := int(rewards.get(item_id_any, 0))
		if item_id.is_empty() or count <= 0:
			continue
		items[item_id] = int(items.get(item_id, 0)) + count
		if source == "online":
			PerfTracker.record_item_gain(item_id, count)
		changed = true
	if not changed:
		return
	if emit_update:
		EventBus.notify_inventory_updated()
	_request_save()

func consume_item(item_id: String, count: int = 1, source: String = "system") -> bool:
	return spend_item(item_id, count, source)

func spend_item(item_id: String, count: int = 1, _source: String = "system") -> bool:
	if item_id.is_empty() or count <= 0:
		return false
	var owned := int(items.get(item_id, 0))
	if owned < count:
		return false
	var left := owned - count
	if left <= 0:
		items.erase(item_id)
	else:
		items[item_id] = left
	EventBus.notify_inventory_updated()
	_request_save()
	return true

func get_count(item_id: String) -> int:
	return int(items.get(item_id, 0))

func _load_save() -> void:
	items = {}
	if FileAccess.file_exists(SAVE_PATH):
		var txt := FileAccess.get_file_as_string(SAVE_PATH)
		var parsed: Variant = JSON.parse_string(txt)
		if parsed is Dictionary:
			var it: Variant = (parsed as Dictionary).get("items", {})
			if it is Dictionary:
				for key_any in (it as Dictionary).keys():
					var item_id := str(key_any)
					if item_id.is_empty():
						continue
					var cnt := int((it as Dictionary).get(key_any, 0))
					if cnt > 0:
						items[item_id] = cnt
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
		f.store_string(JSON.stringify({"items": items}))
	_dirty = false

func _notification(what: int) -> void:
	if what == NOTIFICATION_WM_CLOSE_REQUEST \
	or what == NOTIFICATION_APPLICATION_PAUSED \
	or what == NOTIFICATION_APPLICATION_FOCUS_OUT:
		_flush_save()

func list_items_sorted(filter_type: String = "") -> Array[Dictionary]:
	var result: Array[Dictionary] = []
	var defs := _build_item_defs()

	for item_id_any in items.keys():
		var item_id := str(item_id_any)
		var count := int(items.get(item_id, 0))
		if count <= 0:
			continue

		var item_def: Dictionary = defs.get(item_id, {})
		var item_type := str(item_def.get("type", "item"))
		if not filter_type.is_empty() and item_type != filter_type:
			continue
		result.append({
			"id": item_id,
			"name": str(item_def.get("name", item_id)),
			"rarity": str(item_def.get("rarity", "white")),
			"count": count,
			"icon": str(item_def.get("icon", "")),
			"type": item_type,
			"trait": str(item_def.get("trait", "")),
			"gem_effect": item_def.get("gem_effect", {}),
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
