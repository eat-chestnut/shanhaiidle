extends Node

var cfg: Dictionary = {}

func _ready() -> void:
	load_cfg()

func load_cfg() -> void:
	cfg.clear()
	var battle_cfg: Variant = _load_json_file("res://data/battle_config.json")
	if battle_cfg is Dictionary:
		cfg.merge(battle_cfg, true)

	var items_db: Variant = _load_json_file("res://data/items.json")
	if not (items_db is Dictionary):
		items_db = {
			"items": [],
			"rarity_colors": {},
		}
	cfg["items_db"] = items_db

	var equip_db: Variant = _load_json_file("res://data/equip_templates.json")
	if not (equip_db is Dictionary):
		equip_db = {
			"slots": [],
			"equip_templates": [],
		}
	cfg["equip_db"] = equip_db

func get_cfg() -> Dictionary:
	return cfg

func _load_json_file(path: String) -> Variant:
	if not FileAccess.file_exists(path):
		return {}
	var text := FileAccess.get_file_as_string(path)
	var parsed: Variant = JSON.parse_string(text)
	if parsed == null:
		push_warning("ConfigService: failed to parse %s" % path)
		return {}
	return parsed
