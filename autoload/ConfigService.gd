extends Node

var cfg: Dictionary = {}

func _ready() -> void:
	load_all()

func load_all() -> void:
	cfg.clear()
	var battle_cfg: Variant = _load_json_file("res://data/battle_config.json")
	if battle_cfg is Dictionary:
		cfg.merge(battle_cfg, true)
	_apply_battle_defaults_override()

	var balance_cfg: Variant = _load_json_file("res://data/balance_v1.json")
	if not (balance_cfg is Dictionary):
		balance_cfg = {}
	cfg["balance"] = balance_cfg

	_load_items_cfg()
	_load_equip_cfg()
	_load_equipment_sets_cfg()

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

	_load_stages_cfg()
	_load_monsters_cfg()
	_load_skills_catalog_cfg()

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

	if has_node("/root/SkillNameService"):
		SkillNameService.rebuild()

func load_cfg() -> void:
	load_all()

func load_stages_only() -> void:
	_load_stages_cfg()

func get_cfg() -> Dictionary:
	return cfg

func _load_stages_cfg() -> void:
	var fallback: Dictionary = {
		"stages": [],
	}
	var source_text := RemoteConfigService.get_active_text("stages_v1.json", "res://data/stages_v1.json")

	var check := RemoteConfigService.validate_stages_json(source_text)
	if bool(check.get("ok", false)):
		var parsed: Variant = JSON.parse_string(source_text)
		if parsed is Dictionary:
			cfg["stages_db"] = parsed
			return

	push_warning("ConfigService: remote stages invalid: %s" % str(check.get("reason", "unknown")))
	var local_text := _read_text_file("res://data/stages_v1.json")
	var local_check := RemoteConfigService.validate_stages_json(local_text)
	if bool(local_check.get("ok", false)):
		var local_parsed: Variant = JSON.parse_string(local_text)
		if local_parsed is Dictionary:
			cfg["stages_db"] = local_parsed
			return

	push_warning("ConfigService: local stages invalid: %s" % str(local_check.get("reason", "unknown")))
	cfg["stages_db"] = fallback

func _load_monsters_cfg() -> void:
	cfg.erase("monsters_db")
	var source_text := RemoteConfigService.get_active_text("monsters.json", "res://data/monsters.json")
	if source_text.strip_edges().is_empty():
		return
	var check := RemoteConfigService.validate_monsters_json(source_text)
	if bool(check.get("ok", false)):
		var parsed: Variant = JSON.parse_string(source_text)
		if parsed is Dictionary:
			cfg["monsters_db"] = parsed
			return

	push_warning("ConfigService: remote monsters invalid: %s" % str(check.get("reason", "unknown")))
	var local_text := _read_text_file("res://data/monsters.json")
	var local_check := RemoteConfigService.validate_monsters_json(local_text)
	if bool(local_check.get("ok", false)):
		var local_parsed: Variant = JSON.parse_string(local_text)
		if local_parsed is Dictionary:
			cfg["monsters_db"] = local_parsed
			return

	if not local_text.strip_edges().is_empty():
		push_warning("ConfigService: local monsters invalid: %s" % str(local_check.get("reason", "unknown")))

func _load_items_cfg() -> void:
	var fallback: Dictionary = {
		"items": [],
		"rarity_colors": {},
	}
	var source_text := RemoteConfigService.get_active_text("items.json", "res://data/items.json")
	var check := RemoteConfigService.validate_items_json(source_text)
	if bool(check.get("ok", false)):
		var parsed: Variant = JSON.parse_string(source_text)
		if parsed is Dictionary:
			cfg["items_db"] = parsed
			return

	push_warning("ConfigService: remote items invalid: %s" % str(check.get("reason", "unknown")))
	var local_text := _read_text_file("res://data/items.json")
	var local_check := RemoteConfigService.validate_items_json(local_text)
	if bool(local_check.get("ok", false)):
		var local_parsed: Variant = JSON.parse_string(local_text)
		if local_parsed is Dictionary:
			cfg["items_db"] = local_parsed
			return

	push_warning("ConfigService: local items invalid: %s" % str(local_check.get("reason", "unknown")))
	cfg["items_db"] = fallback

func _load_equip_cfg() -> void:
	var fallback: Dictionary = {
		"slots": [],
		"equip_templates": [],
	}
	var source_text := RemoteConfigService.get_active_text("equip_templates.json", "res://data/equip_templates.json")
	var check := RemoteConfigService.validate_equip_templates_json(source_text)
	if bool(check.get("ok", false)):
		var parsed: Variant = JSON.parse_string(source_text)
		if parsed is Dictionary:
			cfg["equip_db"] = parsed
			return

	push_warning("ConfigService: remote equip templates invalid: %s" % str(check.get("reason", "unknown")))
	var local_text := _read_text_file("res://data/equip_templates.json")
	var local_check := RemoteConfigService.validate_equip_templates_json(local_text)
	if bool(local_check.get("ok", false)):
		var local_parsed: Variant = JSON.parse_string(local_text)
		if local_parsed is Dictionary:
			cfg["equip_db"] = local_parsed
			return

	push_warning("ConfigService: local equip templates invalid: %s" % str(local_check.get("reason", "unknown")))
	cfg["equip_db"] = fallback

func _load_equipment_sets_cfg() -> void:
	var fallback: Dictionary = {
		"equipment_sets": [],
	}
	cfg["equipment_sets_db"] = fallback
	var source_text := RemoteConfigService.get_active_text("equipment_sets.json", "res://data/equipment_sets.json")
	if source_text.strip_edges().is_empty():
		return

	var check := RemoteConfigService.validate_equipment_sets_json(source_text)
	if bool(check.get("ok", false)):
		var parsed: Variant = JSON.parse_string(source_text)
		if parsed is Dictionary:
			cfg["equipment_sets_db"] = parsed
			return

	push_warning("ConfigService: remote equipment sets invalid: %s" % str(check.get("reason", "unknown")))
	var local_text := _read_text_file("res://data/equipment_sets.json")
	if local_text.strip_edges().is_empty():
		return
	var local_check := RemoteConfigService.validate_equipment_sets_json(local_text)
	if bool(local_check.get("ok", false)):
		var local_parsed: Variant = JSON.parse_string(local_text)
		if local_parsed is Dictionary:
			cfg["equipment_sets_db"] = local_parsed
			return

	push_warning("ConfigService: local equipment sets invalid: %s" % str(local_check.get("reason", "unknown")))

func _load_skills_catalog_cfg() -> void:
	cfg.erase("skills_catalog_db")
	var source_text := RemoteConfigService.get_active_text("skills_catalog.json", "res://data/skills_catalog.json")
	if source_text.strip_edges().is_empty():
		return

	var check := RemoteConfigService.validate_skills_catalog(source_text)
	if bool(check.get("ok", false)):
		var parsed: Variant = JSON.parse_string(source_text)
		if parsed is Dictionary:
			cfg["skills_catalog_db"] = parsed
			return

	push_warning("ConfigService: remote skills catalog invalid: %s" % str(check.get("reason", "unknown")))
	var local_text := _read_text_file("res://data/skills_catalog.json")
	var local_check := RemoteConfigService.validate_skills_catalog(local_text)
	if bool(local_check.get("ok", false)):
		var local_parsed: Variant = JSON.parse_string(local_text)
		if local_parsed is Dictionary:
			cfg["skills_catalog_db"] = local_parsed
			return

	if not local_text.strip_edges().is_empty():
		push_warning("ConfigService: local skills catalog invalid: %s" % str(local_check.get("reason", "unknown")))

func _apply_battle_defaults_override() -> void:
	var current_battle_any: Variant = cfg.get("battle", {})
	var current_battle: Dictionary = current_battle_any if current_battle_any is Dictionary else {}

	var source_text := RemoteConfigService.get_active_text("battle_defaults.json", "res://data/battle_defaults.json")
	if source_text.strip_edges().is_empty():
		cfg["battle"] = current_battle
		return

	var check := RemoteConfigService.validate_battle_defaults(source_text)
	if not bool(check.get("ok", false)):
		push_warning("ConfigService: battle defaults invalid: %s" % str(check.get("reason", "unknown")))
		cfg["battle"] = current_battle
		return

	var parsed_any: Variant = JSON.parse_string(source_text)
	if not (parsed_any is Dictionary):
		cfg["battle"] = current_battle
		return
	var parsed: Dictionary = parsed_any
	var battle_override_any: Variant = parsed.get("battle", {})
	if not (battle_override_any is Dictionary):
		cfg["battle"] = current_battle
		return
	var battle_override: Dictionary = (battle_override_any as Dictionary).duplicate(true)
	if battle_override.has("refine_effect_pool"):
		if not RemoteConfigService.is_valid_refine_effect_pool(battle_override.get("refine_effect_pool", [])):
			battle_override.erase("refine_effect_pool")
			push_warning("ConfigService: ignored invalid battle.refine_effect_pool")

	cfg["battle"] = _deep_merge_dict(current_battle, battle_override)

func _deep_merge_dict(base: Dictionary, override: Dictionary) -> Dictionary:
	var out: Dictionary = base.duplicate(true)
	for key_any in override.keys():
		var key = key_any
		var over_val: Variant = override.get(key)
		if out.has(key) and out[key] is Dictionary and over_val is Dictionary:
			var base_child: Dictionary = out[key]
			var over_child: Dictionary = over_val
			out[key] = _deep_merge_dict(base_child, over_child)
		else:
			out[key] = over_val
	return out

func _load_json_file(path: String) -> Variant:
	if not FileAccess.file_exists(path):
		return {}
	var text := FileAccess.get_file_as_string(path)
	var parsed: Variant = JSON.parse_string(text)
	if parsed == null:
		push_warning("ConfigService: failed to parse %s" % path)
		return {}
	return parsed

func _read_text_file(path: String) -> String:
	if not FileAccess.file_exists(path):
		return ""
	return FileAccess.get_file_as_string(path)
