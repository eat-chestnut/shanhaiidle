extends Node

const CORE_REMOTE_LOAD_ORDER := [
	"character_growth_rules",
	"progression_milestones",
	"monsters",
	"stages",
	"blue_affix_pool",
	"blue_gear_templates",
	"material_dungeons",
	"sect_tasks",
	"mountain_god",
	"shop_goods",
]

var cfg: Dictionary = {}

func _ready() -> void:
	load_all()

func load_all() -> void:
	cfg.clear()
	cfg["_config_sources"] = {}
	_log_manifest_status()
	var battle_cfg: Variant = _load_json_file("res://data/battle_config.json")
	if battle_cfg is Dictionary:
		cfg.merge(battle_cfg, true)
	_apply_battle_defaults_override()

	var balance_cfg: Variant = _load_json_file("res://data/balance_v1.json")
	if not (balance_cfg is Dictionary):
		balance_cfg = {}
	cfg["balance"] = balance_cfg
	_load_core_remote_configs()

	_load_items_cfg()
	_load_material_catalog_cfg()
	_load_gem_catalog_cfg()
	_load_equip_cfg()
	_load_equip_slots_cfg()
	_load_equipment_growth_rules_cfg()
	_load_purple_affix_pool_cfg()
	_load_crafting_recipes_cfg()
	_load_star_rules_cfg()
	_load_forge_rules_cfg()
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
	if not cfg.is_empty():
		return
	load_all()

func load_stages_only() -> void:
	_load_stages_cfg()

func get_cfg() -> Dictionary:
	return cfg

func get_config_sources() -> Dictionary:
	var sources_any = cfg.get("_config_sources", {})
	return sources_any if sources_any is Dictionary else {}

func get_character_growth_rules() -> Dictionary:
	var growth_any = cfg.get("character_growth_rules", {})
	return growth_any if growth_any is Dictionary else _default_character_growth_rules()

func get_progression_milestones() -> Dictionary:
	var milestones_any = cfg.get("progression_milestones", {})
	return milestones_any if milestones_any is Dictionary else _default_progression_milestones()

func get_monsters_db() -> Dictionary:
	var db_any = cfg.get("monsters_db", {})
	if db_any is Dictionary:
		return db_any
	return {"monsters": []}

func get_stages_db() -> Dictionary:
	var db_any = cfg.get("stages_db", {})
	if db_any is Dictionary:
		return db_any
	return {"stages": []}

func get_blue_affix_pool_db() -> Dictionary:
	var db_any = cfg.get("blue_affix_pool_db", {})
	if db_any is Dictionary:
		return db_any
	return {"blue_affix_pool": []}

func get_blue_gear_templates_db() -> Dictionary:
	var db_any = cfg.get("blue_gear_templates_db", {})
	if db_any is Dictionary:
		return db_any
	return {"blue_gear_templates": []}

func get_material_dungeons_db() -> Dictionary:
	var db_any = cfg.get("material_dungeons_db", {})
	if db_any is Dictionary:
		return db_any
	return {"material_dungeons": []}

func get_daily_dungeons_db() -> Dictionary:
	return get_material_dungeons_db()

func get_sect_tasks() -> Dictionary:
	var tasks_any = cfg.get("sect_tasks", {})
	if tasks_any is Dictionary:
		return tasks_any
	return {"daily_tasks": [], "milestone_tasks": []}

func get_mountain_god() -> Dictionary:
	var god_any = cfg.get("mountain_god", {})
	if god_any is Dictionary:
		return god_any
	return {"offerings": []}

func get_shop_goods_db() -> Dictionary:
	var db_any = cfg.get("shop_goods_db", {})
	if db_any is Dictionary:
		return db_any
	return {"shop_goods": []}

func _load_core_remote_configs() -> void:
	for key_any in CORE_REMOTE_LOAD_ORDER:
		_load_core_remote_config(str(key_any))

func _load_core_remote_config(key: String) -> void:
	match key:
		"character_growth_rules":
			_load_wrapped_managed_config(key, "character_growth_rules", "character_growth_rules", _default_character_growth_rules())
		"progression_milestones":
			_load_wrapped_managed_config(key, "progression_milestones", "progression_milestones", _default_progression_milestones())
		"sect_tasks":
			_load_wrapped_managed_config(key, "sect_tasks", "sect_tasks", {"daily_tasks": [], "milestone_tasks": []})
		"mountain_god":
			_load_wrapped_managed_config(key, "mountain_god", "mountain_god", {"offerings": []}, ["nanshan_mountain_god"])
		"shop_goods":
			_load_db_managed_config(key, "shop_goods_db", {"shop_goods": []}, ["shop_goods"])
		"monsters":
			_load_db_managed_config(key, "monsters_db", {"monsters": []}, ["monsters"])
		"stages":
			_load_db_managed_config(key, "stages_db", {"stages": []}, ["stages"])
		"blue_affix_pool":
			_load_db_managed_config(key, "blue_affix_pool_db", {"blue_affix_pool": []}, ["blue_affix_pool"])
		"blue_gear_templates":
			_load_db_managed_config(key, "blue_gear_templates_db", {"blue_gear_templates": []}, ["blue_gear_templates"])
		"material_dungeons":
			_load_db_managed_config(key, "material_dungeons_db", {"material_dungeons": []}, ["material_dungeons"])

func _load_wrapped_managed_config(key: String, cfg_key: String, root_key: String, fallback_value: Dictionary, alias_keys: Array[String] = []) -> void:
	var safe_fallback: Dictionary = fallback_value.duplicate(true)
	cfg[cfg_key] = safe_fallback
	_set_alias_values(alias_keys, safe_fallback)
	var remote_result := _parse_wrapped_managed_config_text(key, RemoteConfigService.get_remote_text_for_key(key), root_key, "remote")
	if bool(remote_result.get("ok", false)):
		var payload_any = remote_result.get("payload", {})
		if payload_any is Dictionary:
			var payload: Dictionary = (payload_any as Dictionary).duplicate(true)
			cfg[cfg_key] = payload
			_set_alias_values(alias_keys, payload)
			_record_config_source(key, str(remote_result.get("source", "remote")), str(remote_result.get("detail", "")))
			return
	var local_result := _parse_wrapped_managed_config_text(key, _read_text_file(_fallback_path_for_key(key)), root_key, "local")
	if bool(local_result.get("ok", false)):
		var local_payload_any = local_result.get("payload", {})
		if local_payload_any is Dictionary:
			var local_payload: Dictionary = (local_payload_any as Dictionary).duplicate(true)
			cfg[cfg_key] = local_payload
			_set_alias_values(alias_keys, local_payload)
			_record_config_source(key, str(local_result.get("source", "local")), str(local_result.get("detail", "")))
			return
	_record_config_source(key, "default", "fallback")

func _load_db_managed_config(key: String, cfg_key: String, fallback_payload: Dictionary, alias_keys: Array[String] = []) -> void:
	var safe_fallback: Dictionary = fallback_payload.duplicate(true)
	cfg[cfg_key] = safe_fallback
	_set_alias_values_from_payload(alias_keys, safe_fallback)
	var remote_result := _parse_db_managed_config_text(key, RemoteConfigService.get_remote_text_for_key(key), "remote")
	if bool(remote_result.get("ok", false)):
		var payload_any = remote_result.get("payload", {})
		if payload_any is Dictionary:
			var payload: Dictionary = (payload_any as Dictionary).duplicate(true)
			cfg[cfg_key] = payload
			_set_alias_values_from_payload(alias_keys, payload)
			_apply_core_alias_after_load(key)
			_record_config_source(key, str(remote_result.get("source", "remote")), str(remote_result.get("detail", "")))
			return
	var local_result := _parse_db_managed_config_text(key, _read_text_file(_fallback_path_for_key(key)), "local")
	if bool(local_result.get("ok", false)):
		var local_payload_any = local_result.get("payload", {})
		if local_payload_any is Dictionary:
			var local_payload: Dictionary = (local_payload_any as Dictionary).duplicate(true)
			cfg[cfg_key] = local_payload
			_set_alias_values_from_payload(alias_keys, local_payload)
			_apply_core_alias_after_load(key)
			_record_config_source(key, str(local_result.get("source", "local")), str(local_result.get("detail", "")))
			return
	_record_config_source(key, "default", "fallback")
	_apply_core_alias_after_load(key)

func _parse_wrapped_managed_config_text(key: String, text: String, root_key: String, source: String) -> Dictionary:
	if text.strip_edges().is_empty():
		_config_log("%s 配置%s缺失，准备回退" % [key, _source_name(source)])
		return {"ok": false, "reason": "empty", "source": source}
	var check: Dictionary = RemoteConfigService.validate_text_for_key(key, text)
	if not bool(check.get("ok", false)):
		_config_warn("%s 配置%s校验失败：%s" % [key, _source_name(source), str(check.get("reason", "unknown"))])
		return {"ok": false, "reason": str(check.get("reason", "unknown")), "source": source}
	var parsed: Variant = JSON.parse_string(text)
	if not (parsed is Dictionary):
		_config_warn("%s 配置%s解析失败：根节点必须是对象" % [key, _source_name(source)])
		return {"ok": false, "reason": "parse_failed", "source": source}
	var root_any = (parsed as Dictionary).get(root_key, {})
	if not (root_any is Dictionary):
		_config_warn("%s 配置%s缺少 %s 对象" % [key, _source_name(source), root_key])
		return {"ok": false, "reason": "missing_root", "source": source}
	return {
		"ok": true,
		"payload": root_any,
		"source": source,
		"detail": RemoteConfigService.get_filename_for_key(key),
	}

func _parse_db_managed_config_text(key: String, text: String, source: String) -> Dictionary:
	if text.strip_edges().is_empty():
		_config_log("%s 配置%s缺失，准备回退" % [key, _source_name(source)])
		return {"ok": false, "reason": "empty", "source": source}
	var check: Dictionary = RemoteConfigService.validate_text_for_key(key, text)
	if not bool(check.get("ok", false)):
		_config_warn("%s 配置%s校验失败：%s" % [key, _source_name(source), str(check.get("reason", "unknown"))])
		return {"ok": false, "reason": str(check.get("reason", "unknown")), "source": source}
	var parsed: Variant = JSON.parse_string(text)
	if not (parsed is Dictionary):
		_config_warn("%s 配置%s解析失败：根节点必须是对象" % [key, _source_name(source)])
		return {"ok": false, "reason": "parse_failed", "source": source}
	return {
		"ok": true,
		"payload": parsed,
		"source": source,
		"detail": RemoteConfigService.get_filename_for_key(key),
	}

func _set_alias_values(alias_keys: Array[String], value: Dictionary) -> void:
	for alias_any in alias_keys:
		cfg[str(alias_any)] = value.duplicate(true)

func _set_alias_values_from_payload(alias_keys: Array[String], payload: Dictionary) -> void:
	for alias_any in alias_keys:
		var alias := str(alias_any)
		var alias_value: Variant = payload.get(alias, [])
		if alias_value is Dictionary:
			cfg[alias] = (alias_value as Dictionary).duplicate(true)
		elif alias_value is Array:
			cfg[alias] = (alias_value as Array).duplicate(true)
		else:
			cfg[alias] = alias_value

func _fallback_path_for_key(key: String) -> String:
	match key:
		"character_growth_rules":
			return "res://data/character_growth_rules_v1.json"
		"progression_milestones":
			return "res://data/progression_milestones_v1.json"
		"monsters":
			return "res://data/monsters.json"
		"stages":
			return "res://data/stages_v1.json"
		"blue_affix_pool":
			return "res://data/blue_affix_pool_v1.json"
		"blue_gear_templates":
			return "res://data/blue_gear_templates_v1.json"
		"material_dungeons":
			return "res://data/material_dungeons_v1.json"
		"sect_tasks":
			return "res://data/sect_tasks_v1.json"
		"mountain_god":
			return "res://data/mountain_god_v1.json"
		"shop_goods":
			return "res://data/shop_goods_v1.json"
		_:
			return ""

func _record_config_source(key: String, source: String, detail: String = "") -> void:
	var sources := get_config_sources().duplicate(true)
	sources[key] = {
		"source": source,
		"detail": detail,
	}
	cfg["_config_sources"] = sources
	var extra := ""
	if not detail.is_empty():
		extra = "（%s）" % detail
	_config_log("%s <- %s%s" % [key, source, extra])

func _apply_core_alias_after_load(key: String) -> void:
	if key == "material_dungeons":
		var rows_any = cfg.get("material_dungeons", [])
		if rows_any is Array:
			cfg["daily_dungeons"] = (rows_any as Array).duplicate(true)

func _log_manifest_status() -> void:
	var manifest_info: Dictionary = RemoteConfigService.load_active_manifest_info()
	if bool(manifest_info.get("ok", false)):
		_config_log("统一版本文件加载成功：bundle=%s" % str(manifest_info.get("bundle_id", "内置")))
		return
	_config_log("统一版本文件不可用，启动时将使用本地 fallback（%s）" % str(manifest_info.get("reason", "unknown")))

func _source_name(source: String) -> String:
	match source:
		"remote":
			return "远程"
		"local":
			return "本地"
		_:
			return source

func _is_debug_logging_enabled() -> bool:
	return OS.is_debug_build()

func _config_log(message: String) -> void:
	if not _is_debug_logging_enabled():
		return
	print("[ConfigService] %s" % message)

func _config_warn(message: String) -> void:
	push_warning("ConfigService: %s" % message)

func _load_stages_cfg() -> void:
	_load_core_remote_config("stages")

func _load_monsters_cfg() -> void:
	_load_core_remote_config("monsters")

func _load_items_cfg() -> void:
	var fallback: Dictionary = {
		"items": [],
		"rarity_colors": {},
	}
	var source_text = RemoteConfigService.get_active_text("items.json", "res://data/items.json")
	var check: Dictionary = RemoteConfigService.validate_items_json(source_text)
	if bool(check.get("ok", false)):
		var parsed: Variant = JSON.parse_string(source_text)
		if parsed is Dictionary:
			cfg["items_db"] = parsed
			return

	push_warning("ConfigService: remote items invalid: %s" % str(check.get("reason", "unknown")))
	var local_text := _read_text_file("res://data/items.json")
	var local_check: Dictionary = RemoteConfigService.validate_items_json(local_text)
	if bool(local_check.get("ok", false)):
		var local_parsed: Variant = JSON.parse_string(local_text)
		if local_parsed is Dictionary:
			cfg["items_db"] = local_parsed
			return

	push_warning("ConfigService: local items invalid: %s" % str(local_check.get("reason", "unknown")))
	cfg["items_db"] = fallback

func _load_material_catalog_cfg() -> void:
	var fallback: Dictionary = {
		"material_catalog": [],
	}
	cfg["material_catalog_db"] = fallback
	var source_text = RemoteConfigService.get_material_catalog_text()
	if source_text.strip_edges().is_empty():
		return
	var check: Dictionary = RemoteConfigService.validate_material_catalog_json(source_text)
	if bool(check.get("ok", false)):
		var parsed: Variant = JSON.parse_string(source_text)
		if parsed is Dictionary:
			cfg["material_catalog_db"] = parsed
			return
	push_warning("ConfigService: remote material catalog invalid: %s" % str(check.get("reason", "unknown")))
	var local_text := _read_text_file("res://data/material_catalog_v1.json")
	if local_text.strip_edges().is_empty():
		return
	var local_check: Dictionary = RemoteConfigService.validate_material_catalog_json(local_text)
	if bool(local_check.get("ok", false)):
		var local_parsed: Variant = JSON.parse_string(local_text)
		if local_parsed is Dictionary:
			cfg["material_catalog_db"] = local_parsed
			return
	push_warning("ConfigService: local material catalog invalid: %s" % str(local_check.get("reason", "unknown")))

func _load_gem_catalog_cfg() -> void:
	var fallback: Dictionary = {
		"gem_catalog": [],
	}
	cfg["gem_catalog_db"] = fallback
	var source_text = RemoteConfigService.get_gem_catalog_text()
	if source_text.strip_edges().is_empty():
		return
	var check: Dictionary = RemoteConfigService.validate_gem_catalog_json(source_text)
	if bool(check.get("ok", false)):
		var parsed: Variant = JSON.parse_string(source_text)
		if parsed is Dictionary:
			cfg["gem_catalog_db"] = parsed
			return
	push_warning("ConfigService: remote gem catalog invalid: %s" % str(check.get("reason", "unknown")))
	var local_text := _read_text_file("res://data/gem_catalog_v1.json")
	if local_text.strip_edges().is_empty():
		return
	var local_check: Dictionary = RemoteConfigService.validate_gem_catalog_json(local_text)
	if bool(local_check.get("ok", false)):
		var local_parsed: Variant = JSON.parse_string(local_text)
		if local_parsed is Dictionary:
			cfg["gem_catalog_db"] = local_parsed
			return
	push_warning("ConfigService: local gem catalog invalid: %s" % str(local_check.get("reason", "unknown")))

func _load_equip_cfg() -> void:
	var fallback: Dictionary = {
		"slots": [],
		"equip_templates": [],
	}
	var source_text = RemoteConfigService.get_active_text("equip_templates.json", "res://data/equip_templates.json")
	var check: Dictionary = RemoteConfigService.validate_equip_templates_json(source_text)
	if bool(check.get("ok", false)):
		var parsed: Variant = JSON.parse_string(source_text)
		if parsed is Dictionary:
			cfg["equip_db"] = parsed
			return

	push_warning("ConfigService: remote equip templates invalid: %s" % str(check.get("reason", "unknown")))
	var local_text := _read_text_file("res://data/equip_templates.json")
	var local_check: Dictionary = RemoteConfigService.validate_equip_templates_json(local_text)
	if bool(local_check.get("ok", false)):
		var local_parsed: Variant = JSON.parse_string(local_text)
		if local_parsed is Dictionary:
			cfg["equip_db"] = local_parsed
			return

	push_warning("ConfigService: local equip templates invalid: %s" % str(local_check.get("reason", "unknown")))
	cfg["equip_db"] = fallback

func _load_equip_slots_cfg() -> void:
	var fallback: Dictionary = {
		"equip_slots": [],
	}
	cfg["equip_slots_db"] = fallback
	var source_text = RemoteConfigService.get_equip_slots_text()
	if source_text.strip_edges().is_empty():
		return
	var check: Dictionary = RemoteConfigService.validate_equip_slots_json(source_text)
	if bool(check.get("ok", false)):
		var parsed: Variant = JSON.parse_string(source_text)
		if parsed is Dictionary:
			cfg["equip_slots_db"] = parsed
			return
	push_warning("ConfigService: remote equip slots invalid: %s" % str(check.get("reason", "unknown")))
	var local_text := _read_text_file("res://data/equip_slots_v1.json")
	if local_text.strip_edges().is_empty():
		return
	var local_check: Dictionary = RemoteConfigService.validate_equip_slots_json(local_text)
	if bool(local_check.get("ok", false)):
		var local_parsed: Variant = JSON.parse_string(local_text)
		if local_parsed is Dictionary:
			cfg["equip_slots_db"] = local_parsed
			return
	push_warning("ConfigService: local equip slots invalid: %s" % str(local_check.get("reason", "unknown")))

func _load_equipment_growth_rules_cfg() -> void:
	var fallback: Dictionary = {
		"equipment_growth_rules": {},
	}
	cfg["equipment_growth_rules_db"] = fallback
	var source_text = RemoteConfigService.get_equipment_growth_rules_text()
	if source_text.strip_edges().is_empty():
		return
	var check: Dictionary = RemoteConfigService.validate_equipment_growth_rules_json(source_text)
	if bool(check.get("ok", false)):
		var parsed: Variant = JSON.parse_string(source_text)
		if parsed is Dictionary:
			cfg["equipment_growth_rules_db"] = parsed
			return
	push_warning("ConfigService: remote equipment growth rules invalid: %s" % str(check.get("reason", "unknown")))
	var local_text := _read_text_file("res://data/equipment_growth_rules_v1.json")
	if local_text.strip_edges().is_empty():
		return
	var local_check: Dictionary = RemoteConfigService.validate_equipment_growth_rules_json(local_text)
	if bool(local_check.get("ok", false)):
		var local_parsed: Variant = JSON.parse_string(local_text)
		if local_parsed is Dictionary:
			cfg["equipment_growth_rules_db"] = local_parsed
			return
	push_warning("ConfigService: local equipment growth rules invalid: %s" % str(local_check.get("reason", "unknown")))

func _load_character_growth_rules_cfg() -> void:
	_load_core_remote_config("character_growth_rules")

func _load_progression_milestones_cfg() -> void:
	_load_core_remote_config("progression_milestones")

func _load_sect_tasks_cfg() -> void:
	_load_core_remote_config("sect_tasks")

func _load_mountain_god_cfg() -> void:
	_load_core_remote_config("mountain_god")

func _load_blue_gear_templates_cfg() -> void:
	_load_core_remote_config("blue_gear_templates")

func _load_blue_affix_pool_cfg() -> void:
	_load_core_remote_config("blue_affix_pool")

func _load_purple_affix_pool_cfg() -> void:
	var fallback: Dictionary = {
		"purple_affix_pool": [],
	}
	cfg["purple_affix_pool_db"] = fallback
	var source_text = RemoteConfigService.get_purple_affix_pool_text()
	if source_text.strip_edges().is_empty():
		return
	var check: Dictionary = RemoteConfigService.validate_purple_affix_pool_json(source_text)
	if bool(check.get("ok", false)):
		var parsed: Variant = JSON.parse_string(source_text)
		if parsed is Dictionary:
			cfg["purple_affix_pool_db"] = parsed
			return
	push_warning("ConfigService: remote purple affix pool invalid: %s" % str(check.get("reason", "unknown")))
	var local_text := _read_text_file("res://data/purple_affix_pool_v1.json")
	if local_text.strip_edges().is_empty():
		return
	var local_check: Dictionary = RemoteConfigService.validate_purple_affix_pool_json(local_text)
	if bool(local_check.get("ok", false)):
		var local_parsed: Variant = JSON.parse_string(local_text)
		if local_parsed is Dictionary:
			cfg["purple_affix_pool_db"] = local_parsed
			return
	push_warning("ConfigService: local purple affix pool invalid: %s" % str(local_check.get("reason", "unknown")))

func _load_material_dungeons_cfg() -> void:
	_load_core_remote_config("material_dungeons")

func _load_crafting_recipes_cfg() -> void:
	var fallback: Dictionary = {
		"crafting_recipes": [],
	}
	cfg["crafting_recipes_db"] = fallback
	var source_text = RemoteConfigService.get_crafting_recipes_text()
	if source_text.strip_edges().is_empty():
		return
	var check: Dictionary = RemoteConfigService.validate_crafting_recipes_json(source_text)
	if bool(check.get("ok", false)):
		var parsed: Variant = JSON.parse_string(source_text)
		if parsed is Dictionary:
			cfg["crafting_recipes_db"] = parsed
			return
	push_warning("ConfigService: remote crafting recipes invalid: %s" % str(check.get("reason", "unknown")))
	var local_text := _read_text_file("res://data/crafting_recipes_v1.json")
	if local_text.strip_edges().is_empty():
		return
	var local_check: Dictionary = RemoteConfigService.validate_crafting_recipes_json(local_text)
	if bool(local_check.get("ok", false)):
		var local_parsed: Variant = JSON.parse_string(local_text)
		if local_parsed is Dictionary:
			cfg["crafting_recipes_db"] = local_parsed
			return
	push_warning("ConfigService: local crafting recipes invalid: %s" % str(local_check.get("reason", "unknown")))

func _load_equipment_sets_cfg() -> void:
	var fallback: Dictionary = {
		"equipment_sets": [],
	}
	cfg["equipment_sets_db"] = fallback
	var source_text = RemoteConfigService.get_active_text("equipment_sets.json", "res://data/equipment_sets.json")
	if source_text.strip_edges().is_empty():
		return

	var check: Dictionary = RemoteConfigService.validate_equipment_sets_json(source_text)
	if bool(check.get("ok", false)):
		var parsed: Variant = JSON.parse_string(source_text)
		if parsed is Dictionary:
			cfg["equipment_sets_db"] = parsed
			return

	push_warning("ConfigService: remote equipment sets invalid: %s" % str(check.get("reason", "unknown")))
	var local_text := _read_text_file("res://data/equipment_sets.json")
	if local_text.strip_edges().is_empty():
		return
	var local_check: Dictionary = RemoteConfigService.validate_equipment_sets_json(local_text)
	if bool(local_check.get("ok", false)):
		var local_parsed: Variant = JSON.parse_string(local_text)
		if local_parsed is Dictionary:
			cfg["equipment_sets_db"] = local_parsed
			return

	push_warning("ConfigService: local equipment sets invalid: %s" % str(local_check.get("reason", "unknown")))

func _load_star_rules_cfg() -> void:
	var fallback: Dictionary = {
		"star_rules": {
			"tiers": {}
		}
	}
	cfg["star_rules_db"] = fallback
	var source_text = RemoteConfigService.get_active_text("star_rules_v1.json", "res://data/star_rules_v1.json")
	if source_text.strip_edges().is_empty():
		return
	var check: Dictionary = RemoteConfigService.validate_star_rules_json(source_text)
	if bool(check.get("ok", false)):
		var parsed_any: Variant = JSON.parse_string(source_text)
		if parsed_any is Dictionary:
			cfg["star_rules_db"] = parsed_any
			return

	push_warning("ConfigService: remote star rules invalid: %s" % str(check.get("reason", "unknown")))
	var local_text := _read_text_file("res://data/star_rules_v1.json")
	if local_text.strip_edges().is_empty():
		return
	var local_check: Dictionary = RemoteConfigService.validate_star_rules_json(local_text)
	if bool(local_check.get("ok", false)):
		var local_parsed_any: Variant = JSON.parse_string(local_text)
		if local_parsed_any is Dictionary:
			cfg["star_rules_db"] = local_parsed_any
			return

	push_warning("ConfigService: local star rules invalid: %s" % str(local_check.get("reason", "unknown")))

func _load_forge_rules_cfg() -> void:
	var fallback: Dictionary = {
		"forge_rules": {
			"tiers": {},
			"normal_forge_rules": [],
			"high_forge_rules": [],
			"blueprint_compose_rules": [],
		}
	}
	cfg["forge_rules_db"] = fallback
	var source_text = RemoteConfigService.get_active_text("forge_rules_v1.json", "res://data/forge_rules_v1.json")
	if source_text.strip_edges().is_empty():
		return
	var check: Dictionary = RemoteConfigService.validate_forge_rules_json(source_text)
	if bool(check.get("ok", false)):
		var parsed_any: Variant = JSON.parse_string(source_text)
		if parsed_any is Dictionary:
			cfg["forge_rules_db"] = parsed_any
			return

	push_warning("ConfigService: remote forge rules invalid: %s" % str(check.get("reason", "unknown")))
	var local_text := _read_text_file("res://data/forge_rules_v1.json")
	if local_text.strip_edges().is_empty():
		return
	var local_check: Dictionary = RemoteConfigService.validate_forge_rules_json(local_text)
	if bool(local_check.get("ok", false)):
		var local_parsed_any: Variant = JSON.parse_string(local_text)
		if local_parsed_any is Dictionary:
			cfg["forge_rules_db"] = local_parsed_any
			return

	push_warning("ConfigService: local forge rules invalid: %s" % str(local_check.get("reason", "unknown")))

func _load_skills_catalog_cfg() -> void:
	cfg.erase("skills_catalog_db")
	var source_text = RemoteConfigService.get_active_text("skills_catalog.json", "res://data/skills_catalog.json")
	if source_text.strip_edges().is_empty():
		return

	var check: Dictionary = RemoteConfigService.validate_skills_catalog(source_text)
	if bool(check.get("ok", false)):
		var parsed: Variant = JSON.parse_string(source_text)
		if parsed is Dictionary:
			cfg["skills_catalog_db"] = parsed
			return

	push_warning("ConfigService: remote skills catalog invalid: %s" % str(check.get("reason", "unknown")))
	var local_text := _read_text_file("res://data/skills_catalog.json")
	var local_check: Dictionary = RemoteConfigService.validate_skills_catalog(local_text)
	if bool(local_check.get("ok", false)):
		var local_parsed: Variant = JSON.parse_string(local_text)
		if local_parsed is Dictionary:
			cfg["skills_catalog_db"] = local_parsed
			return

	if not local_text.strip_edges().is_empty():
		push_warning("ConfigService: local skills catalog invalid: %s" % str(local_check.get("reason", "unknown")))

func _default_character_growth_rules() -> Dictionary:
	var exp_values := [
		12, 16, 20, 24, 28, 32, 36, 40, 45, 50,
		55, 60, 66, 72, 78, 84, 90, 97, 104, 112,
		120, 128, 136, 145, 154, 163, 172, 182, 192, 202,
		213, 224, 235, 247, 259, 271, 284, 297, 310, 324,
		338, 352, 367, 382, 398, 414, 430, 447, 464, 482,
		500, 519, 538, 557, 577, 598, 619, 640, 662,
	]
	var level_exp_table: Array[Dictionary] = []
	for i in range(exp_values.size()):
		level_exp_table.append({
			"level": i + 1,
			"exp_to_next": int(exp_values[i]),
			"attr_points_gain": 1,
		})

	return {
		"level_cap": 60,
		"initial": {
			"level": 1,
			"free_attr_points": 0,
			"skill_points": 1,
			"current_class": "bing",
			"base_attributes": {
				"strength": 0,
				"physique": 0,
				"agility": 0,
				"spirit": 0,
				"true_energy": 0,
				"fortune": 0,
			},
		},
		"level_exp_table": level_exp_table,
		"base_growth": {
			"hp": {
				"base": 10,
				"per_level_every": 4,
				"per_level_gain": 1,
			},
			"qi": {
				"base": 10,
				"per_level_every": 5,
				"per_level_gain": 1,
			},
			"atk": {
				"base": 1,
				"per_level_gain": 0,
			},
			"def": {
				"base": 0,
				"per_level_gain": 0,
			},
			"crit_percent": {
				"base": 5,
			},
			"loot_bonus_percent": {
				"base": 0,
			},
		},
		"attribute_formulas": {
			"physique": {
				"hp_per_point": 1.0,
				"hp_extra_every_10": 1,
				"def_per_point": 0.0,
			},
			"true_energy": {
				"qi_per_point": 1.0,
			},
			"agility": {
				"crit_percent_per_point": 0.25,
				"dodge_per_point": 0.0,
				"attack_speed_per_point": 0.0,
			},
			"strength": {
				"phys_mul_permille_per_point": 12,
			},
			"spirit": {
				"spell_mul_permille_per_point": 12,
			},
			"fortune": {
				"loot_bonus_percent_per_point": 0.6,
			},
		},
	}

func _default_progression_milestones() -> Dictionary:
	return {
		"version": 1,
		"range": {
			"min_level": 1,
			"max_level": 20,
		},
		"milestones": [
			{
				"level": 1,
				"milestone_key": "lv1_start",
				"title": "初入宗门",
				"summary": "完成新手引导，开始巡山。",
				"image": "",
				"unlock_contents": [
					{"type": "main_stage", "content": "开放主线第1关"},
					{"type": "feature_unlock", "content": "开放宗门任务"},
					{"type": "blue_gear", "content": "开放1级蓝装阶段"},
				],
				"reward_item_id": "milestone_pack_lv1",
				"reward_count": 1,
				"claim_once": true,
				"is_enabled": true,
				"sort": 10,
			},
			{
				"level": 5,
				"milestone_key": "lv5_early_growth",
				"title": "初步成型",
				"summary": "蓝装进入5级档，主线进入稳定推进。",
				"image": "",
				"unlock_contents": [
					{"type": "main_stage", "content": "开放主线第2关"},
					{"type": "daily_dungeon", "content": "开放金币副本"},
					{"type": "blue_gear", "content": "蓝装进入5级档"},
				],
				"reward_item_id": "milestone_pack_lv5",
				"reward_count": 1,
				"claim_once": true,
				"is_enabled": true,
				"sort": 20,
			},
			{
				"level": 10,
				"milestone_key": "lv10_stable_push",
				"title": "稳定推进",
				"summary": "主线进入第一轮稳定期，开始为后续双词条阶段做准备。",
				"image": "",
				"unlock_contents": [
					{"type": "main_stage", "content": "开放主线第3关"},
					{"type": "daily_dungeon", "content": "开放经验副本"},
					{"type": "blue_gear", "content": "继续使用10级蓝装"},
				],
				"reward_item_id": "milestone_pack_lv10",
				"reward_count": 1,
				"claim_once": true,
				"is_enabled": true,
				"sort": 30,
			},
			{
				"level": 15,
				"milestone_key": "lv15_affix_upgrade",
				"title": "蓝装强化",
				"summary": "蓝装进入双词条阶段，开始形成明显构筑差异。",
				"image": "",
				"unlock_contents": [
					{"type": "main_stage", "content": "开放主线第4关"},
					{"type": "daily_dungeon", "content": "开放材料副本"},
					{"type": "blue_gear", "content": "蓝装进入15级双词条阶段"},
				],
				"reward_item_id": "milestone_pack_lv15",
				"reward_count": 1,
				"claim_once": true,
				"is_enabled": true,
				"sort": 40,
			},
			{
				"level": 20,
				"milestone_key": "lv20_mid_ready",
				"title": "迈入中期",
				"summary": "20级蓝装完整成型，准备进入20-40阶段。",
				"image": "",
				"unlock_contents": [
					{"type": "main_stage", "content": "开放主线第5关"},
					{"type": "daily_dungeon", "content": "开放宝石副本"},
					{"type": "blue_gear", "content": "蓝装进入20级双词条阶段"},
				],
				"reward_item_id": "milestone_pack_lv20",
				"reward_count": 1,
				"claim_once": true,
				"is_enabled": true,
				"sort": 50,
			},
		],
	}

func _apply_battle_defaults_override() -> void:
	var current_battle_any: Variant = cfg.get("battle", {})
	var current_battle: Dictionary = current_battle_any if current_battle_any is Dictionary else {}

	var source_text = RemoteConfigService.get_active_text("battle_defaults.json", "res://data/battle_defaults.json")
	if source_text.strip_edges().is_empty():
		cfg["battle"] = current_battle
		return

	var check: Dictionary = RemoteConfigService.validate_battle_defaults(source_text)
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
