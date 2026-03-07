extends Node

var cfg: Dictionary = {}

func _ready() -> void:
	load_cfg()

func load_cfg() -> void:
	cfg.clear()
	var battle_cfg: Variant = _load_json_file("res://data/battle_config.json")
	if battle_cfg is Dictionary:
		cfg.merge(battle_cfg, true)

	var balance_cfg: Variant = _load_json_file("res://data/balance_v1.json")
	if not (balance_cfg is Dictionary):
		balance_cfg = {}
	cfg["balance"] = balance_cfg

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

	var leveling_cfg: Variant = _load_json_file("res://data/leveling.json")
	if not (leveling_cfg is Dictionary):
		leveling_cfg = {
			"level_cap": 60,
			"exp_curve": {
				"base": 20,
				"linear": 6,
				"quadratic": 0.2,
			},
			"kill_exp": 2,
		}
	cfg["leveling"] = leveling_cfg

	var stages_db: Variant = _load_json_file("res://data/stages_v1.json")
	if not (stages_db is Dictionary):
		stages_db = {
			"stages": [],
		}
	cfg["stages_db"] = stages_db

	var upgrade_db: Variant = _load_json_file("res://data/upgrade_v1.json")
	if not (upgrade_db is Dictionary):
		upgrade_db = {
			"tier_names": ["凡", "灵", "玄"],
			"tier_bonus": {"0": 0, "1": 1, "2": 2},
			"recipes": {},
		}
	cfg["upgrade_db"] = upgrade_db

	cfg["ui_frames"] = {
		"empty": "res://assets/ui_frames/common_board_quality_mask.png",
		"white": "res://assets/ui_frames/common_board_quality_white.png",
		"blue": "res://assets/ui_frames/common_board_quality_blue.png",
		"gold": "res://assets/ui_frames/common_board_quality_orange.png",
	}

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
