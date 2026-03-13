extends Node

const BASE_URL := "http://127.0.0.1:8001/bundles/latest"
const ACTIVE_ROOT := "user://remote"
const ACTIVE_DIR := "user://remote/active"
const TMP_DIR := "user://remote_tmp"
const ACTIVE_MANIFEST := "user://remote/active_manifest.json"
const LATEST_MANIFEST_NAME := "manifest.json"
const BUNDLE_FILE_NAME := "config_bundle_v1.json"

const FILE_KEY_ORDER := [
	"stages",
	"items",
	"equip_templates",
	"equipment_sets",
	"equip_slots",
	"equipment_growth_rules",
	"character_growth_rules",
	"progression_milestones",
	"blue_gear_templates",
	"blue_affix_pool",
	"purple_affix_pool",
	"gem_catalog",
	"material_catalog",
	"material_dungeons",
	"sect_tasks",
	"mountain_god",
	"shop_goods",
	"crafting_recipes",
	"monsters",
	"skills_catalog",
	"battle_defaults",
]

const OPTIONAL_FILE_KEYS := [
	"star_rules",
	"forge_rules",
]

const CORE_RUNTIME_KEYS := [
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

const FILE_KEY_TO_NAME := {
	"stages": "stages_v1.json",
	"items": "items.json",
	"equip_templates": "equip_templates.json",
	"equipment_sets": "equipment_sets.json",
	"equip_slots": "equip_slots_v1.json",
	"equipment_growth_rules": "equipment_growth_rules_v1.json",
	"character_growth_rules": "character_growth_rules_v1.json",
	"progression_milestones": "progression_milestones_v1.json",
	"blue_gear_templates": "blue_gear_templates_v1.json",
	"blue_affix_pool": "blue_affix_pool_v1.json",
	"purple_affix_pool": "purple_affix_pool_v1.json",
	"gem_catalog": "gem_catalog_v1.json",
	"material_catalog": "material_catalog_v1.json",
	"material_dungeons": "material_dungeons_v1.json",
	"sect_tasks": "sect_tasks_v1.json",
	"mountain_god": "mountain_god_v1.json",
	"shop_goods": "shop_goods_v1.json",
	"crafting_recipes": "crafting_recipes_v1.json",
	"monsters": "monsters.json",
	"skills_catalog": "skills_catalog.json",
	"battle_defaults": "battle_defaults.json",
	"star_rules": "star_rules_v1.json",
	"forge_rules": "forge_rules_v1.json",
}

func get_managed_file_keys() -> Array[String]:
	var keys: Array[String] = []
	for key_any in FILE_KEY_ORDER:
		keys.append(str(key_any))
	for key_any in OPTIONAL_FILE_KEYS:
		var key := str(key_any)
		if keys.has(key):
			continue
		keys.append(key)
	return keys

func get_core_runtime_keys() -> Array[String]:
	var keys: Array[String] = []
	for key_any in CORE_RUNTIME_KEYS:
		keys.append(str(key_any))
	return keys

func has_managed_key(key: String) -> bool:
	return FILE_KEY_TO_NAME.has(key)

func get_filename_for_key(key: String) -> String:
	return str(FILE_KEY_TO_NAME.get(key, ""))

func get_bundle_manifest_url() -> String:
	return "%s/%s" % [BASE_URL, LATEST_MANIFEST_NAME]

func get_bundle_filename() -> String:
	return BUNDLE_FILE_NAME

func get_bundle_file_url(filename: String) -> String:
	var clean := filename.strip_edges()
	if clean.is_empty():
		return ""
	return "%s/%s" % [BASE_URL, clean]

func get_active_file_text(filename: String) -> String:
	var clean := filename.strip_edges()
	if clean.is_empty():
		return ""
	var active_path := "%s/%s" % [ACTIVE_DIR, clean]
	if not FileAccess.file_exists(active_path):
		return ""
	return FileAccess.get_file_as_string(active_path)

func get_local_fallback_text(path: String) -> String:
	var clean := path.strip_edges()
	if clean.is_empty():
		return ""
	if not FileAccess.file_exists(clean):
		return ""
	return FileAccess.get_file_as_string(clean)

func get_remote_text_for_key(key: String) -> String:
	return get_active_file_text(get_filename_for_key(key))

func get_text_for_key(key: String, fallback_res_path: String = "") -> String:
	return get_active_text(get_filename_for_key(key), fallback_res_path)

func validate_text_for_key(key: String, text: String) -> Dictionary:
	return _validate_payload_by_key(key, text)

func get_stages_json_text() -> String:
	return get_active_text("stages_v1.json", "res://data/stages_v1.json")

func get_items_json_text() -> String:
	return get_active_text("items.json", "res://data/items.json")

func get_equip_templates_text() -> String:
	return get_active_text("equip_templates.json", "res://data/equip_templates.json")

func get_equipment_sets_text() -> String:
	return get_active_text("equipment_sets.json", "res://data/equipment_sets.json")

func get_equip_slots_text() -> String:
	return get_active_text("equip_slots_v1.json", "res://data/equip_slots_v1.json")

func get_equipment_growth_rules_text() -> String:
	return get_active_text("equipment_growth_rules_v1.json", "res://data/equipment_growth_rules_v1.json")

func get_character_growth_rules_text() -> String:
	return get_active_text("character_growth_rules_v1.json", "res://data/character_growth_rules_v1.json")

func get_progression_milestones_text() -> String:
	return get_active_text("progression_milestones_v1.json", "res://data/progression_milestones_v1.json")

func get_blue_gear_templates_text() -> String:
	return get_active_text("blue_gear_templates_v1.json", "res://data/blue_gear_templates_v1.json")

func get_blue_affix_pool_text() -> String:
	return get_active_text("blue_affix_pool_v1.json", "res://data/blue_affix_pool_v1.json")

func get_purple_affix_pool_text() -> String:
	return get_active_text("purple_affix_pool_v1.json", "res://data/purple_affix_pool_v1.json")

func get_gem_catalog_text() -> String:
	return get_active_text("gem_catalog_v1.json", "res://data/gem_catalog_v1.json")

func get_material_catalog_text() -> String:
	return get_active_text("material_catalog_v1.json", "res://data/material_catalog_v1.json")

func get_material_dungeons_text() -> String:
	return get_active_text("material_dungeons_v1.json", "res://data/material_dungeons_v1.json")

func get_sect_tasks_text() -> String:
	return get_active_text("sect_tasks_v1.json", "res://data/sect_tasks_v1.json")

func get_mountain_god_text() -> String:
	return get_active_text("mountain_god_v1.json", "res://data/mountain_god_v1.json")

func get_shop_goods_text() -> String:
	return get_active_text("shop_goods_v1.json", "res://data/shop_goods_v1.json")

func get_crafting_recipes_text() -> String:
	return get_active_text("crafting_recipes_v1.json", "res://data/crafting_recipes_v1.json")

func get_monsters_json_text() -> String:
	return get_active_text("monsters.json", "res://data/monsters.json")

func get_skills_catalog_text() -> String:
	return get_active_text("skills_catalog.json", "res://data/skills_catalog.json")

func get_battle_defaults_text() -> String:
	return get_active_text("battle_defaults.json", "res://data/battle_defaults.json")

func get_star_rules_text() -> String:
	return get_active_text("star_rules_v1.json", "res://data/star_rules_v1.json")

func get_forge_rules_text() -> String:
	return get_active_text("forge_rules_v1.json", "res://data/forge_rules_v1.json")

func get_active_text(filename: String, fallback_res_path: String) -> String:
	var active_path := "%s/%s" % [ACTIVE_DIR, filename]
	if FileAccess.file_exists(active_path):
		return FileAccess.get_file_as_string(active_path)
	if not fallback_res_path.is_empty() and FileAccess.file_exists(fallback_res_path):
		return FileAccess.get_file_as_string(fallback_res_path)
	return ""

func get_active_versions() -> Dictionary:
	var out := {
		"stages": 0,
		"items": 0,
		"equip_templates": 0,
		"equipment_sets": 0,
		"equip_slots": 0,
		"equipment_growth_rules": 0,
		"character_growth_rules": 0,
		"progression_milestones": 0,
		"blue_gear_templates": 0,
		"blue_affix_pool": 0,
		"purple_affix_pool": 0,
		"gem_catalog": 0,
		"material_catalog": 0,
		"material_dungeons": 0,
		"sect_tasks": 0,
		"mountain_god": 0,
		"shop_goods": 0,
		"crafting_recipes": 0,
		"monsters": 0,
		"skills_catalog": 0,
		"battle_defaults": 0,
		"star_rules": 0,
		"forge_rules": 0,
	}
	if not FileAccess.file_exists(ACTIVE_MANIFEST):
		return out
	var manifest_text := FileAccess.get_file_as_string(ACTIVE_MANIFEST)
	var parsed_any: Variant = JSON.parse_string(manifest_text)
	if not (parsed_any is Dictionary):
		return out
	var parsed: Dictionary = parsed_any
	var files_any = parsed.get("files", [])
	if not (files_any is Array):
		return out
	for row_any in files_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		var key := str(row.get("key", ""))
		if not out.has(key):
			continue
		out[key] = maxi(0, int(row.get("version", 0)))
	return out

func get_active_bundle_id() -> String:
	if not FileAccess.file_exists(ACTIVE_MANIFEST):
		return "内置"
	var manifest_text := FileAccess.get_file_as_string(ACTIVE_MANIFEST)
	var parsed_any: Variant = JSON.parse_string(manifest_text)
	if not (parsed_any is Dictionary):
		return "内置"
	var parsed: Dictionary = parsed_any
	var meta_any: Variant = parsed.get("meta", {})
	if not (meta_any is Dictionary):
		return "内置"
	var meta: Dictionary = meta_any
	var bundle_id := str(meta.get("bundle_id", "")).strip_edges()
	if bundle_id.is_empty():
		return "内置"
	return bundle_id

func load_active_manifest_info() -> Dictionary:
	if not FileAccess.file_exists(ACTIVE_MANIFEST):
		return {"ok": false, "reason": "active manifest 不存在"}
	var manifest_text := FileAccess.get_file_as_string(ACTIVE_MANIFEST)
	return _parse_manifest_info(manifest_text)

func fetch_latest_manifest(on_done: Callable) -> void:
	_download_text(get_bundle_manifest_url(), func(ok: bool, text: String, msg: String) -> void:
		if not ok:
			_call_manifest_done(on_done, false, {}, msg)
			return
		var parsed := _parse_manifest_info(text)
		if not bool(parsed.get("ok", false)):
			_call_manifest_done(on_done, false, {}, str(parsed.get("reason", "manifest 校验失败")))
			return
		if _is_debug_logging_enabled():
			_debug_log("统一版本文件加载成功：bundle=%s" % str(parsed.get("bundle_id", "")))
		_call_manifest_done(on_done, true, parsed, "ok")
	)

func get_versions_from_manifest(manifest: Dictionary) -> Dictionary:
	var out: Dictionary = {}
	var files_any = manifest.get("files", [])
	if not (files_any is Array):
		return out
	for row_any in files_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		var key := str(row.get("key", "")).strip_edges()
		if key.is_empty():
			continue
		out[key] = maxi(0, int(row.get("version", 0)))
	return out

func get_pending_updates_from_manifest(manifest: Dictionary) -> Array[Dictionary]:
	var current := get_active_versions()
	var updates: Array[Dictionary] = []
	var files_any = manifest.get("files", [])
	if not (files_any is Array):
		return updates
	for row_any in files_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		var key := str(row.get("key", "")).strip_edges()
		if key.is_empty():
			continue
		var current_version := maxi(0, int(current.get(key, 0)))
		var latest_version := maxi(0, int(row.get("version", 0)))
		if latest_version > current_version:
			updates.append(row.duplicate(true))
	return updates

func download_config_file(key: String, on_done: Callable) -> void:
	var clean_key := key.strip_edges()
	if not has_managed_key(clean_key):
		_call_file_done(on_done, false, clean_key, "", {}, "未知配置 key")
		return
	fetch_latest_manifest(func(ok: bool, manifest_info: Dictionary, msg: String) -> void:
		if not ok:
			_call_file_done(on_done, false, clean_key, "", {}, msg)
			return
		var row := _find_manifest_row(manifest_info, clean_key)
		if row.is_empty():
			_call_file_done(on_done, false, clean_key, "", {}, "manifest 中缺少配置项")
			return
		var filename := str(row.get("filename", "")).strip_edges()
		var expected_sha := str(row.get("sha256", "")).strip_edges().to_lower()
		if filename.is_empty() or expected_sha.is_empty():
			_call_file_done(on_done, false, clean_key, "", {}, "manifest 文件项不完整")
			return
		_download_text(get_bundle_file_url(filename), func(file_ok: bool, text: String, file_msg: String) -> void:
			if not file_ok:
				_call_file_done(on_done, false, clean_key, "", row, file_msg)
				return
			var check := validate_text_for_key(clean_key, text)
			if not bool(check.get("ok", false)):
				_call_file_done(on_done, false, clean_key, "", row, str(check.get("reason", "配置校验失败")))
				return
			var actual_sha := _sha256_text(text)
			if actual_sha != expected_sha:
				_call_file_done(on_done, false, clean_key, "", row, "文件哈希不匹配")
				return
			if _is_debug_logging_enabled():
				_debug_log("单文件拉取成功：%s <- %s" % [clean_key, filename])
			_call_file_done(on_done, true, clean_key, text, row, "ok")
		)
	)

func validate_stages_json(text: String) -> Dictionary:
	if text.strip_edges().is_empty():
		return {"ok": false, "reason": "配置内容为空"}

	var parsed: Variant = JSON.parse_string(text)
	if not (parsed is Dictionary):
		return {"ok": false, "reason": "JSON根节点必须是对象"}

	var root: Dictionary = parsed
	var meta_check := _validate_optional_meta(root, "stages")
	if not bool(meta_check.get("ok", false)):
		return meta_check
	var stages_any: Variant = root.get("stages", null)
	if not (stages_any is Array):
		return {"ok": false, "reason": "缺少 stages 数组"}
	var stages: Array = stages_any
	if stages.is_empty():
		return {"ok": false, "reason": "stages 不能为空"}

	for i in range(stages.size()):
		var stage_any: Variant = stages[i]
		if not (stage_any is Dictionary):
			return {"ok": false, "reason": "stage[%d] 不是对象" % i}
		var stage: Dictionary = stage_any
		for key in ["id", "name", "unlock_min_level", "difficulties"]:
			if not stage.has(key):
				return {"ok": false, "reason": "stage[%d] 缺少字段 %s" % [i, key]}

		if str(stage.get("id", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "stage[%d].id 不能为空" % i}
		if str(stage.get("name", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "stage[%d].name 不能为空" % i}
		if int(stage.get("unlock_min_level", 0)) < 1:
			return {"ok": false, "reason": "stage[%d].unlock_min_level 必须 >= 1" % i}

		var diff_check := _validate_stage_difficulties(stage.get("difficulties", []), i)
		if not bool(diff_check.get("ok", false)):
			return diff_check

	return {"ok": true}

func _validate_stage_difficulties(difficulties_any: Variant, stage_index: int) -> Dictionary:
	if not (difficulties_any is Array):
		return {"ok": false, "reason": "stage[%d].difficulties 必须是数组" % stage_index}
	var difficulties: Array = difficulties_any
	if difficulties.is_empty():
		return {"ok": false, "reason": "stage[%d].difficulties 不能为空" % stage_index}
	for j in range(difficulties.size()):
		var diff_any: Variant = difficulties[j]
		if not (diff_any is Dictionary):
			return {"ok": false, "reason": "stage[%d].difficulties[%d] 必须是对象" % [stage_index, j]}
		var diff: Dictionary = diff_any
		if str(diff.get("difficulty_name", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "stage[%d].difficulties[%d].difficulty_name 不能为空" % [stage_index, j]}
		if int(diff.get("recommended_power", -1)) < 0:
			return {"ok": false, "reason": "stage[%d].difficulties[%d].recommended_power 不能为负数" % [stage_index, j]}
		if float(diff.get("spawn_interval", 0.0)) <= 0.0:
			return {"ok": false, "reason": "stage[%d].difficulties[%d].spawn_interval 必须 > 0" % [stage_index, j]}
		if int(diff.get("onscreen_limit", 0)) < 1:
			return {"ok": false, "reason": "stage[%d].difficulties[%d].onscreen_limit 必须 >= 1" % [stage_index, j]}
		if float(diff.get("spawn_radius", 0.0)) <= 0.0:
			return {"ok": false, "reason": "stage[%d].difficulties[%d].spawn_radius 必须 > 0" % [stage_index, j]}

		for pool_key in ["normal_monsters", "elite_monsters", "boss_monsters"]:
			var pool_check := _validate_stage_monster_pool(diff.get(pool_key, []), stage_index, j, pool_key)
			if not bool(pool_check.get("ok", false)):
				return pool_check

		for rule_key in ["elite_spawn_rule", "boss_spawn_rule"]:
			if diff.has(rule_key) and diff.get(rule_key, null) != null and not (diff.get(rule_key, null) is Dictionary):
				return {"ok": false, "reason": "stage[%d].difficulties[%d].%s 必须是对象或 null" % [stage_index, j, rule_key]}
			if diff.get(rule_key, null) is Dictionary:
				var rule: Dictionary = diff.get(rule_key, {})
				if rule.has("every_kills") and int(rule.get("every_kills", 0)) < 1:
					return {"ok": false, "reason": "stage[%d].difficulties[%d].%s.every_kills 必须 >= 1" % [stage_index, j, rule_key]}
	return {"ok": true}

func _validate_stage_monster_pool(pool_any: Variant, stage_index: int, diff_index: int, pool_key: String) -> Dictionary:
	if not (pool_any is Array):
		return {"ok": false, "reason": "stage[%d].difficulties[%d].%s 必须是数组" % [stage_index, diff_index, pool_key]}
	var pool: Array = pool_any
	if pool.is_empty():
		return {"ok": false, "reason": "stage[%d].difficulties[%d].%s 不能为空" % [stage_index, diff_index, pool_key]}
	var total_weight := 0
	for row_index in range(pool.size()):
		var row_any: Variant = pool[row_index]
		if not (row_any is Dictionary):
			return {"ok": false, "reason": "stage[%d].difficulties[%d].%s[%d] 必须是对象" % [stage_index, diff_index, pool_key, row_index]}
		var row: Dictionary = row_any
		if str(row.get("monster_id", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "stage[%d].difficulties[%d].%s[%d].monster_id 不能为空" % [stage_index, diff_index, pool_key, row_index]}
		var weight := int(row.get("weight", 0))
		if weight <= 0:
			return {"ok": false, "reason": "stage[%d].difficulties[%d].%s[%d].weight 必须 > 0" % [stage_index, diff_index, pool_key, row_index]}
		total_weight += weight
	if total_weight <= 0:
		return {"ok": false, "reason": "stage[%d].difficulties[%d].%s 权重总和必须 > 0" % [stage_index, diff_index, pool_key]}
	return {"ok": true}

func validate_monsters_json(text: String) -> Dictionary:
	if text.strip_edges().is_empty():
		return {"ok": false, "reason": "怪物配置内容为空"}

	var parsed: Variant = JSON.parse_string(text)
	if not (parsed is Dictionary):
		return {"ok": false, "reason": "怪物JSON根节点必须是对象"}
	var root: Dictionary = parsed
	var meta_check := _validate_optional_meta(root, "monsters")
	if not bool(meta_check.get("ok", false)):
		return meta_check

	var monsters_any: Variant = root.get("monsters", null)
	if not (monsters_any is Array):
		return {"ok": false, "reason": "缺少 monsters 数组"}
	var monsters: Array = monsters_any
	if monsters.is_empty():
		return {"ok": false, "reason": "monsters 不能为空"}

	var required := [
		"id",
		"name",
		"kind",
		"hp",
		"atk",
		"def",
		"speed",
		"radius",
		"aggro_range",
		"attack_interval",
		"attack_range",
		"exp",
		"drop_bonus_percent",
		"dex_gold",
	]
	for i in range(monsters.size()):
		var mon_any: Variant = monsters[i]
		if not (mon_any is Dictionary):
			return {"ok": false, "reason": "monster[%d] 不是对象" % i}
		var mon: Dictionary = mon_any
		for key in required:
			if not mon.has(key):
				return {"ok": false, "reason": "monster[%d] 缺少字段 %s" % [i, key]}

		var mon_id := str(mon.get("id", "")).strip_edges()
		var mon_name := str(mon.get("name", "")).strip_edges()
		if mon_id.is_empty() or mon_name.is_empty():
			return {"ok": false, "reason": "monster[%d] id/name 不能为空" % i}

		var kind := str(mon.get("kind", "")).strip_edges().to_lower()
		if kind != "normal" and kind != "elite" and kind != "boss":
			return {"ok": false, "reason": "monster[%d].kind 非法" % i}

		if int(mon.get("hp", 0)) < 1:
			return {"ok": false, "reason": "monster[%d].hp 必须 >= 1" % i}
		var attack_interval := float(mon.get("attack_interval", 0.0))
		if attack_interval < 0.2 or attack_interval > 10.0:
			return {"ok": false, "reason": "monster[%d].attack_interval 需在0.2~10" % i}
		var drops_any = mon.get("drops", [])
		if not (drops_any is Array):
			return {"ok": false, "reason": "monster[%d].drops 必须是数组" % i}
		var drops: Array = drops_any
		for j in range(drops.size()):
			var drop_any: Variant = drops[j]
			if not (drop_any is Dictionary):
				return {"ok": false, "reason": "monster[%d].drops[%d] 必须是对象" % [i, j]}
			var drop: Dictionary = drop_any
			if str(drop.get("item_id", "")).strip_edges().is_empty():
				return {"ok": false, "reason": "monster[%d].drops[%d].item_id 不能为空" % [i, j]}
			var count_min := int(drop.get("count_min", 0))
			var count_max := int(drop.get("count_max", 0))
			if count_min < 1:
				return {"ok": false, "reason": "monster[%d].drops[%d].count_min 必须 >= 1" % [i, j]}
			if count_max < count_min:
				return {"ok": false, "reason": "monster[%d].drops[%d].count_max 不能小于 count_min" % [i, j]}
			if drop.has("drop_rate") and drop.get("drop_rate", null) != null:
				var drop_rate := float(drop.get("drop_rate", -1.0))
				if drop_rate < 0.0 or drop_rate > 1.0:
					return {"ok": false, "reason": "monster[%d].drops[%d].drop_rate 需在0~1" % [i, j]}

	return {"ok": true}

func validate_items_json(text: String) -> Dictionary:
	if text.strip_edges().is_empty():
		return {"ok": false, "reason": "物品配置内容为空"}

	var parsed: Variant = JSON.parse_string(text)
	if not (parsed is Dictionary):
		return {"ok": false, "reason": "物品JSON根节点必须是对象"}
	var root: Dictionary = parsed
	var meta_check := _validate_optional_meta(root, "items")
	if not bool(meta_check.get("ok", false)):
		return meta_check

	var rows_any: Variant = root.get("items", null)
	if not (rows_any is Array):
		return {"ok": false, "reason": "缺少 items 数组"}
	var rows: Array = rows_any
	if rows.is_empty():
		return {"ok": false, "reason": "items 不能为空"}

	for i in range(rows.size()):
		var row_any: Variant = rows[i]
		if not (row_any is Dictionary):
			return {"ok": false, "reason": "items[%d] 不是对象" % i}
		var row: Dictionary = row_any
		var item_id := str(row.get("id", "")).strip_edges()
		var item_name := str(row.get("name", "")).strip_edges()
		var rarity := str(row.get("rarity", "")).strip_edges().to_lower()
		if item_id.is_empty() or item_name.is_empty():
			return {"ok": false, "reason": "items[%d] id/name 不能为空" % i}
		if rarity != "white" and rarity != "blue" and rarity != "gold" and rarity != "purple" and rarity != "orange":
			return {"ok": false, "reason": "items[%d].rarity 非法" % i}
		if str(row.get("type", "")).strip_edges() == "gem":
			var gem_check := _validate_gem_row(row, "items", i)
			if not bool(gem_check.get("ok", false)):
				return gem_check
		if str(row.get("type", "")).strip_edges() == "item" and str(row.get("effect_type", "")).strip_edges() == "use_effect":
			var use_effect_check := _validate_item_use_effect_payload(row, i)
			if not bool(use_effect_check.get("ok", false)):
				return use_effect_check

	return {"ok": true}

func validate_equip_templates_json(text: String) -> Dictionary:
	if text.strip_edges().is_empty():
		return {"ok": false, "reason": "装备模板内容为空"}

	var parsed: Variant = JSON.parse_string(text)
	if not (parsed is Dictionary):
		return {"ok": false, "reason": "装备模板JSON根节点必须是对象"}
	var root: Dictionary = parsed
	var meta_check := _validate_optional_meta(root, "equip_templates")
	if not bool(meta_check.get("ok", false)):
		return meta_check

	var rows_any: Variant = root.get("equip_templates", null)
	if not (rows_any is Array):
		return {"ok": false, "reason": "缺少 equip_templates 数组"}
	var rows: Array = rows_any
	if rows.is_empty():
		return {"ok": false, "reason": "equip_templates 不能为空"}

	for i in range(rows.size()):
		var row_any: Variant = rows[i]
		if not (row_any is Dictionary):
			return {"ok": false, "reason": "equip_templates[%d] 不是对象" % i}
		var row: Dictionary = row_any
		var template_id := str(row.get("id", "")).strip_edges()
		var name := str(row.get("name", "")).strip_edges()
		var slot := str(row.get("slot", "")).strip_edges()
		var rarity := str(row.get("rarity", "")).strip_edges()
		if template_id.is_empty() or name.is_empty():
			return {"ok": false, "reason": "equip_templates[%d] id/name 不能为空" % i}
		if slot.is_empty() or rarity.is_empty():
			return {"ok": false, "reason": "equip_templates[%d] 缺少核心字段" % i}
		for legacy_key in ["main_stat", "main_min", "main_max", "unidentified_chance", "effects", "base_stats", "star_max", "max_sockets", "default_socket_count"]:
			if row.has(legacy_key):
				return {"ok": false, "reason": "equip_templates[%d] 不允许出现旧字段 %s" % [i, legacy_key]}
		var white_stats_check := _validate_template_stat_rows(row.get("white_stats", null), "white_stats", i)
		if not bool(white_stats_check.get("ok", false)):
			return white_stats_check
		var growth_check := _validate_template_stat_rows(row.get("star_growth", []), "star_growth", i, false)
		if not bool(growth_check.get("ok", false)):
			return growth_check
		if row.has("star_cap") and int(row.get("star_cap", 0)) < 0:
			return {"ok": false, "reason": "equip_templates[%d].star_cap 不能为负数" % i}
		var socket_rule := str(row.get("socket_rule_ref", "")).strip_edges()
		if socket_rule != "fixed_star_3_6_8_10":
			return {"ok": false, "reason": "equip_templates[%d].socket_rule_ref 仅支持固定开孔规则" % i}

	return {"ok": true}

func _validate_template_stat_rows(rows_any: Variant, field_name: String, row_index: int, required: bool = true) -> Dictionary:
	if not (rows_any is Array):
		return {"ok": false, "reason": "equip_templates[%d].%s 必须是数组" % [row_index, field_name]}
	var rows: Array = rows_any
	if required and rows.is_empty():
		return {"ok": false, "reason": "equip_templates[%d].%s 不能为空" % [row_index, field_name]}
	var seen: Dictionary = {}
	for stat_idx in range(rows.size()):
		var stat_row_any = rows[stat_idx]
		if not (stat_row_any is Dictionary):
			return {"ok": false, "reason": "equip_templates[%d].%s[%d] 必须是对象" % [row_index, field_name, stat_idx]}
		var stat_row: Dictionary = stat_row_any
		var stat := str(stat_row.get("stat", "")).strip_edges()
		if stat.is_empty():
			return {"ok": false, "reason": "equip_templates[%d].%s[%d].stat 不能为空" % [row_index, field_name, stat_idx]}
		if seen.has(stat):
			return {"ok": false, "reason": "equip_templates[%d].%s 中属性 %s 重复" % [row_index, field_name, stat]}
		if int(stat_row.get("value", -1)) < 0:
			return {"ok": false, "reason": "equip_templates[%d].%s[%d].value 不能为负数" % [row_index, field_name, stat_idx]}
		seen[stat] = true
	return {"ok": true}

func validate_equipment_sets_json(text: String) -> Dictionary:
	if text.strip_edges().is_empty():
		return {"ok": false, "reason": "套装配置内容为空"}

	var parsed: Variant = JSON.parse_string(text)
	if not (parsed is Dictionary):
		return {"ok": false, "reason": "套装配置JSON根节点必须是对象"}
	var root: Dictionary = parsed
	var meta_check := _validate_optional_meta(root, "equipment_sets")
	if not bool(meta_check.get("ok", false)):
		return meta_check

	var rows_any: Variant = root.get("equipment_sets", null)
	if not (rows_any is Array):
		return {"ok": false, "reason": "缺少 equipment_sets 数组"}
	var rows: Array = rows_any
	for i in range(rows.size()):
		var row_any: Variant = rows[i]
		if not (row_any is Dictionary):
			return {"ok": false, "reason": "equipment_sets[%d] 不是对象" % i}
		var row: Dictionary = row_any
		for key in ["id", "name", "piece_count", "thresholds"]:
			if not row.has(key):
				return {"ok": false, "reason": "equipment_sets[%d] 缺少字段 %s" % [i, key]}
		var set_id := str(row.get("id", "")).strip_edges()
		var set_name := str(row.get("name", "")).strip_edges()
		var piece_count := int(row.get("piece_count", 0))
		if set_id.is_empty() or set_name.is_empty():
			return {"ok": false, "reason": "equipment_sets[%d] id/name 不能为空" % i}
		if piece_count < 1:
			return {"ok": false, "reason": "equipment_sets[%d].piece_count 必须 >= 1" % i}

		var thresholds_any: Variant = row.get("thresholds", [])
		if not (thresholds_any is Array):
			return {"ok": false, "reason": "equipment_sets[%d].thresholds 必须是数组" % i}
		var thresholds: Array = thresholds_any
		for j in range(thresholds.size()):
			var th_any: Variant = thresholds[j]
			if not (th_any is Dictionary):
				return {"ok": false, "reason": "equipment_sets[%d].thresholds[%d] 不是对象" % [i, j]}
			var th: Dictionary = th_any
			var cnt := int(th.get("count", 0))
			if cnt < 1:
				return {"ok": false, "reason": "equipment_sets[%d].thresholds[%d].count 必须 >= 1" % [i, j]}
			var bonuses_any: Variant = th.get("bonuses", [])
			if not (bonuses_any is Array):
				return {"ok": false, "reason": "equipment_sets[%d].thresholds[%d].bonuses 必须是数组" % [i, j]}
			var bonuses: Array = bonuses_any
			for k in range(bonuses.size()):
				var bonus_any: Variant = bonuses[k]
				if not (bonus_any is Dictionary):
					return {"ok": false, "reason": "equipment_sets[%d].thresholds[%d].bonuses[%d] 不是对象" % [i, j, k]}
				var bonus: Dictionary = bonus_any
				var btype := str(bonus.get("type", "")).strip_edges()
				if btype == "stat":
					var stat := str(bonus.get("stat", "")).strip_edges()
					if stat.is_empty():
						return {"ok": false, "reason": "equipment_sets[%d].thresholds[%d].bonuses[%d].stat 不能为空" % [i, j, k]}
				elif btype == "skill_level":
					var skill_id := str(bonus.get("skill_id", "")).strip_edges()
					if skill_id.is_empty():
						return {"ok": false, "reason": "equipment_sets[%d].thresholds[%d].bonuses[%d].skill_id 不能为空" % [i, j, k]}
				else:
					return {"ok": false, "reason": "equipment_sets[%d].thresholds[%d].bonuses[%d].type 非法" % [i, j, k]}
				if int(bonus.get("val", -1)) < 0:
					return {"ok": false, "reason": "equipment_sets[%d].thresholds[%d].bonuses[%d].val 不能为负数" % [i, j, k]}

	return {"ok": true}

func validate_skills_catalog(text: String) -> Dictionary:
	if text.strip_edges().is_empty():
		return {"ok": false, "reason": "技能字典内容为空"}

	var parsed: Variant = JSON.parse_string(text)
	if not (parsed is Dictionary):
		return {"ok": false, "reason": "技能字典JSON根节点必须是对象"}
	var root: Dictionary = parsed
	var meta_check := _validate_optional_meta(root, "skills_catalog")
	if not bool(meta_check.get("ok", false)):
		return meta_check

	var rows_any: Variant = root.get("skills_catalog", null)
	if not (rows_any is Array):
		return {"ok": false, "reason": "缺少 skills_catalog 数组"}
	var rows: Array = rows_any
	if rows.is_empty():
		return {"ok": false, "reason": "skills_catalog 不能为空"}

	for i in range(rows.size()):
		var row_any: Variant = rows[i]
		if not (row_any is Dictionary):
			return {"ok": false, "reason": "skills_catalog[%d] 不是对象" % i}
		var row: Dictionary = row_any
		var skill_id := str(row.get("id", "")).strip_edges()
		var skill_name := str(row.get("name", "")).strip_edges()
		if skill_id.is_empty():
			return {"ok": false, "reason": "skills_catalog[%d].id 不能为空" % i}
		if skill_name.is_empty():
			return {"ok": false, "reason": "skills_catalog[%d].name 不能为空" % i}
		if str(row.get("class", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "skills_catalog[%d].class 不能为空" % i}
		if str(row.get("type", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "skills_catalog[%d].type 不能为空" % i}
		if int(row.get("min_level", 0)) < 1:
			return {"ok": false, "reason": "skills_catalog[%d].min_level 非法" % i}
		if int(row.get("max_level", 0)) < 1:
			return {"ok": false, "reason": "skills_catalog[%d].max_level 非法" % i}
		var tags_any: Variant = row.get("tags", [])
		if not (tags_any is Array):
			return {"ok": false, "reason": "skills_catalog[%d].tags 必须是数组" % i}

	return {"ok": true}

func validate_battle_defaults(text: String) -> Dictionary:
	if text.strip_edges().is_empty():
		return {"ok": false, "reason": "战斗默认配置内容为空"}

	var parsed: Variant = JSON.parse_string(text)
	if not (parsed is Dictionary):
		return {"ok": false, "reason": "战斗默认配置JSON根节点必须是对象"}
	var root: Dictionary = parsed
	var meta_check := _validate_optional_meta(root, "battle_defaults")
	if not bool(meta_check.get("ok", false)):
		return meta_check

	var battle_any: Variant = root.get("battle", null)
	if not (battle_any is Dictionary):
		return {"ok": false, "reason": "缺少 battle 对象"}

	var battle: Dictionary = battle_any
	var classes_any: Variant = battle.get("classes", [])
	if not (classes_any is Array):
		return {"ok": false, "reason": "battle.classes 必须是数组"}
	var classes: Array = classes_any
	if classes.is_empty():
		return {"ok": false, "reason": "battle.classes 不能为空"}
	for i in range(classes.size()):
		var class_any: Variant = classes[i]
		if not (class_any is Dictionary):
			return {"ok": false, "reason": "battle.classes[%d] 不是对象" % i}
		var class_row: Dictionary = class_any
		if str(class_row.get("id", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "battle.classes[%d].id 不能为空" % i}
		if str(class_row.get("name", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "battle.classes[%d].name 不能为空" % i}

	var combat_any: Variant = battle.get("combat", null)
	if not (combat_any is Dictionary):
		return {"ok": false, "reason": "battle.combat 必须是对象"}
	if float((combat_any as Dictionary).get("gcd_seconds", 0.0)) <= 0.0:
		return {"ok": false, "reason": "battle.combat.gcd_seconds 必须大于0"}

	var ai_any: Variant = battle.get("ai_profiles", null)
	if not (ai_any is Dictionary):
		return {"ok": false, "reason": "battle.ai_profiles 必须是对象"}
	var profiles_any: Variant = (ai_any as Dictionary).get("profiles", [])
	if not (profiles_any is Array):
		return {"ok": false, "reason": "battle.ai_profiles.profiles 必须是数组"}

	if battle.has("refine_effect_pool"):
		var refine_check := _validate_refine_effect_pool(battle.get("refine_effect_pool", []))
		if not bool(refine_check.get("ok", false)):
			return {
				"ok": true,
				"refine_effect_pool_ok": false,
				"reason": str(refine_check.get("reason", "refine_effect_pool 非法")),
			}

	return {"ok": true, "refine_effect_pool_ok": true}

func validate_star_rules_json(text: String) -> Dictionary:
	if text.strip_edges().is_empty():
		return {"ok": false, "reason": "升星规则内容为空"}

	var parsed_any: Variant = JSON.parse_string(text)
	if not (parsed_any is Dictionary):
		return {"ok": false, "reason": "升星规则JSON根节点必须是对象"}
	var root: Dictionary = parsed_any
	var meta_check := _validate_optional_meta(root, "star_rules")
	if not bool(meta_check.get("ok", false)):
		return meta_check

	var rules_any: Variant = root.get("star_rules", {})
	if not (rules_any is Dictionary):
		return {"ok": false, "reason": "缺少 star_rules 对象"}
	var rules: Dictionary = rules_any
	var tiers_any: Variant = rules.get("tiers", {})
	if not (tiers_any is Dictionary):
		return {"ok": false, "reason": "star_rules.tiers 必须是对象"}
	var tiers: Dictionary = tiers_any
	if tiers.is_empty():
		return {"ok": false, "reason": "star_rules.tiers 不能为空"}

	for tier_key_any in tiers.keys():
		var tier_key := str(tier_key_any).strip_edges()
		if tier_key.is_empty():
			return {"ok": false, "reason": "star_rules.tiers 存在空tier键名"}
		var tier_any: Variant = tiers.get(tier_key_any, {})
		if not (tier_any is Dictionary):
			return {"ok": false, "reason": "star_rules.tiers.%s 必须是对象" % tier_key}
		var tier: Dictionary = tier_any

		var range_any: Variant = tier.get("level_range", [])
		if not (range_any is Array):
			return {"ok": false, "reason": "star_rules.tiers.%s.level_range 必须是数组" % tier_key}
		var level_range: Array = range_any
		if level_range.size() < 2:
			return {"ok": false, "reason": "star_rules.tiers.%s.level_range 至少2项" % tier_key}
		var lv_min := int(level_range[0])
		var lv_max := int(level_range[1])
		if lv_min < 1 or lv_max < lv_min:
			return {"ok": false, "reason": "star_rules.tiers.%s.level_range 非法" % tier_key}

		var stages_any: Variant = tier.get("stages", {})
		if not (stages_any is Dictionary):
			return {"ok": false, "reason": "star_rules.tiers.%s.stages 必须是对象" % tier_key}
		var stages: Dictionary = stages_any
		if stages.is_empty():
			return {"ok": false, "reason": "star_rules.tiers.%s.stages 不能为空" % tier_key}

		for stage_key_any in stages.keys():
			var stage_key := str(stage_key_any).strip_edges()
			if stage_key.is_empty():
				return {"ok": false, "reason": "star_rules.tiers.%s.stages 存在空阶段键名" % tier_key}
			var stage_any: Variant = stages.get(stage_key_any, {})
			if not (stage_any is Dictionary):
				return {"ok": false, "reason": "star_rules.tiers.%s.stages.%s 必须是对象" % [tier_key, stage_key]}
			var stage: Dictionary = stage_any
			var min_star := int(stage.get("min_star", -1))
			var max_star := int(stage.get("max_star", -1))
			var target_star_max := int(stage.get("target_star_max", 0))
			if min_star < 0 or max_star < min_star:
				return {"ok": false, "reason": "star_rules.tiers.%s.stages.%s 星级区间非法" % [tier_key, stage_key]}
			if target_star_max < 1:
				return {"ok": false, "reason": "star_rules.tiers.%s.stages.%s.target_star_max 必须 >= 1" % [tier_key, stage_key]}
			if target_star_max > 10:
				return {"ok": false, "reason": "star_rules.tiers.%s.stages.%s.target_star_max 不能超过10" % [tier_key, stage_key]}

			var gold_cost := int(stage.get("gold_cost", -1))
			if gold_cost < 0:
				return {"ok": false, "reason": "star_rules.tiers.%s.stages.%s.gold_cost 不能为负数" % [tier_key, stage_key]}

			if stage.has("applicable_slot_groups"):
				var groups_any: Variant = stage.get("applicable_slot_groups", [])
				if not (groups_any is Array):
					return {"ok": false, "reason": "star_rules.tiers.%s.stages.%s.applicable_slot_groups 必须是数组" % [tier_key, stage_key]}
				for g_any in groups_any:
					if str(g_any).strip_edges().is_empty():
						return {"ok": false, "reason": "star_rules.tiers.%s.stages.%s.applicable_slot_groups 含空值" % [tier_key, stage_key]}

			var options_any: Variant = stage.get("material_options", [])
			if not (options_any is Array):
				return {"ok": false, "reason": "star_rules.tiers.%s.stages.%s.material_options 必须是数组" % [tier_key, stage_key]}
			var options: Array = options_any
			if options.is_empty():
				return {"ok": false, "reason": "star_rules.tiers.%s.stages.%s.material_options 不能为空" % [tier_key, stage_key]}
			for i in range(options.size()):
				var opt_any: Variant = options[i]
				if not (opt_any is Dictionary):
					return {"ok": false, "reason": "star_rules.tiers.%s.stages.%s.material_options[%d] 必须是对象" % [tier_key, stage_key, i]}
				var opt: Dictionary = opt_any
				var item_id := str(opt.get("item_id", "")).strip_edges()
				var count := int(opt.get("count", 0))
				if item_id.is_empty():
					return {"ok": false, "reason": "star_rules.tiers.%s.stages.%s.material_options[%d].item_id 不能为空" % [tier_key, stage_key, i]}
				if count <= 0:
					return {"ok": false, "reason": "star_rules.tiers.%s.stages.%s.material_options[%d].count 必须 > 0" % [tier_key, stage_key, i]}

	return {"ok": true}

func validate_forge_rules_json(text: String) -> Dictionary:
	if text.strip_edges().is_empty():
		return {"ok": false, "reason": "打造规则内容为空"}

	var parsed_any: Variant = JSON.parse_string(text)
	if not (parsed_any is Dictionary):
		return {"ok": false, "reason": "打造规则JSON根节点必须是对象"}
	var root: Dictionary = parsed_any
	var meta_check := _validate_optional_meta(root, "forge_rules")
	if not bool(meta_check.get("ok", false)):
		return meta_check

	var rules_any: Variant = root.get("forge_rules", {})
	if not (rules_any is Dictionary):
		return {"ok": false, "reason": "缺少 forge_rules 对象"}
	var rules: Dictionary = rules_any

	var tiers_any: Variant = rules.get("tiers", {})
	if not (tiers_any is Dictionary):
		return {"ok": false, "reason": "forge_rules.tiers 必须是对象"}
	var tiers: Dictionary = tiers_any
	if tiers.is_empty():
		return {"ok": false, "reason": "forge_rules.tiers 不能为空"}
	for tier_key_any in tiers.keys():
		var tier_key := str(tier_key_any).strip_edges()
		if tier_key.is_empty():
			return {"ok": false, "reason": "forge_rules.tiers 存在空tier键名"}
		var row_any: Variant = tiers.get(tier_key_any, {})
		if not (row_any is Dictionary):
			return {"ok": false, "reason": "forge_rules.tiers.%s 必须是对象" % tier_key}
		var row: Dictionary = row_any
		var lv_any: Variant = row.get("level_range", [])
		if not (lv_any is Array):
			return {"ok": false, "reason": "forge_rules.tiers.%s.level_range 必须是数组" % tier_key}
		var lv: Array = lv_any
		if lv.size() < 2:
			return {"ok": false, "reason": "forge_rules.tiers.%s.level_range 至少2项" % tier_key}
		var min_lv := int(lv[0])
		var max_lv := int(lv[1])
		if min_lv < 1 or max_lv < min_lv:
			return {"ok": false, "reason": "forge_rules.tiers.%s.level_range 非法" % tier_key}

	var normal_any: Variant = rules.get("normal_forge_rules", [])
	if not (normal_any is Array):
		return {"ok": false, "reason": "forge_rules.normal_forge_rules 必须是数组"}
	var normal_rows: Array = normal_any
	if normal_rows.is_empty():
		return {"ok": false, "reason": "forge_rules.normal_forge_rules 不能为空"}
	for i in range(normal_rows.size()):
		var row_any: Variant = normal_rows[i]
		if not (row_any is Dictionary):
			return {"ok": false, "reason": "normal_forge_rules[%d] 必须是对象" % i}
		var row: Dictionary = row_any
		if str(row.get("forge_tier", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "normal_forge_rules[%d].forge_tier 不能为空" % i}
		if str(row.get("slot_group", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "normal_forge_rules[%d].slot_group 不能为空" % i}
		if int(row.get("gold_cost", -1)) < 0:
			return {"ok": false, "reason": "normal_forge_rules[%d].gold_cost 不能为负数" % i}
		var mats_any: Variant = row.get("materials", [])
		if not (mats_any is Array):
			return {"ok": false, "reason": "normal_forge_rules[%d].materials 必须是数组" % i}
		var mats: Array = mats_any
		if mats.is_empty():
			return {"ok": false, "reason": "normal_forge_rules[%d].materials 不能为空" % i}
		for j in range(mats.size()):
			var mat_any: Variant = mats[j]
			if not (mat_any is Dictionary):
				return {"ok": false, "reason": "normal_forge_rules[%d].materials[%d] 必须是对象" % [i, j]}
			var mat: Dictionary = mat_any
			if str(mat.get("item_id", "")).strip_edges().is_empty():
				return {"ok": false, "reason": "normal_forge_rules[%d].materials[%d].item_id 不能为空" % [i, j]}
			if int(mat.get("count", 0)) <= 0:
				return {"ok": false, "reason": "normal_forge_rules[%d].materials[%d].count 必须 > 0" % [i, j]}

	var high_any: Variant = rules.get("high_forge_rules", [])
	if not (high_any is Array):
		return {"ok": false, "reason": "forge_rules.high_forge_rules 必须是数组"}
	var high_rows: Array = high_any
	if high_rows.is_empty():
		return {"ok": false, "reason": "forge_rules.high_forge_rules 不能为空"}
	for i in range(high_rows.size()):
		var row_any: Variant = high_rows[i]
		if not (row_any is Dictionary):
			return {"ok": false, "reason": "high_forge_rules[%d] 必须是对象" % i}
		var row: Dictionary = row_any
		if str(row.get("forge_tier", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "high_forge_rules[%d].forge_tier 不能为空" % i}
		if str(row.get("slot_group", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "high_forge_rules[%d].slot_group 不能为空" % i}
		if str(row.get("theme_key", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "high_forge_rules[%d].theme_key 不能为空" % i}
		if int(row.get("gold_cost", -1)) < 0:
			return {"ok": false, "reason": "high_forge_rules[%d].gold_cost 不能为负数" % i}
		var mats_any: Variant = row.get("materials", [])
		if not (mats_any is Array):
			return {"ok": false, "reason": "high_forge_rules[%d].materials 必须是数组" % i}
		var mats: Array = mats_any
		if mats.is_empty():
			return {"ok": false, "reason": "high_forge_rules[%d].materials 不能为空" % i}
		for j in range(mats.size()):
			var mat_any: Variant = mats[j]
			if not (mat_any is Dictionary):
				return {"ok": false, "reason": "high_forge_rules[%d].materials[%d] 必须是对象" % [i, j]}
			var mat: Dictionary = mat_any
			if str(mat.get("item_id", "")).strip_edges().is_empty():
				return {"ok": false, "reason": "high_forge_rules[%d].materials[%d].item_id 不能为空" % [i, j]}
			if int(mat.get("count", 0)) <= 0:
				return {"ok": false, "reason": "high_forge_rules[%d].materials[%d].count 必须 > 0" % [i, j]}
		if row.has("extra_materials"):
			var extra_any: Variant = row.get("extra_materials", [])
			if not (extra_any is Array):
				return {"ok": false, "reason": "high_forge_rules[%d].extra_materials 必须是数组" % i}
			for j in range((extra_any as Array).size()):
				var mat_any: Variant = (extra_any as Array)[j]
				if not (mat_any is Dictionary):
					return {"ok": false, "reason": "high_forge_rules[%d].extra_materials[%d] 必须是对象" % [i, j]}
				var mat: Dictionary = mat_any
				if str(mat.get("item_id", "")).strip_edges().is_empty():
					return {"ok": false, "reason": "high_forge_rules[%d].extra_materials[%d].item_id 不能为空" % [i, j]}
				if int(mat.get("count", 0)) <= 0:
					return {"ok": false, "reason": "high_forge_rules[%d].extra_materials[%d].count 必须 > 0" % [i, j]}

	var compose_any: Variant = rules.get("blueprint_compose_rules", [])
	if not (compose_any is Array):
		return {"ok": false, "reason": "forge_rules.blueprint_compose_rules 必须是数组"}
	var compose_rows: Array = compose_any
	if compose_rows.is_empty():
		return {"ok": false, "reason": "forge_rules.blueprint_compose_rules 不能为空"}
	for i in range(compose_rows.size()):
		var row_any: Variant = compose_rows[i]
		if not (row_any is Dictionary):
			return {"ok": false, "reason": "blueprint_compose_rules[%d] 必须是对象" % i}
		var row: Dictionary = row_any
		if str(row.get("theme_key", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "blueprint_compose_rules[%d].theme_key 不能为空" % i}
		if str(row.get("fragment_item_id", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "blueprint_compose_rules[%d].fragment_item_id 不能为空" % i}
		if int(row.get("fragment_count", 0)) <= 0:
			return {"ok": false, "reason": "blueprint_compose_rules[%d].fragment_count 必须 > 0" % i}
		if int(row.get("gold_cost", -1)) < 0:
			return {"ok": false, "reason": "blueprint_compose_rules[%d].gold_cost 不能为负数" % i}

	return {"ok": true}

func validate_equip_slots_json(text: String) -> Dictionary:
	var check := _validate_catalog_array_json(text, "equip_slots", "equip_slots")
	if not bool(check.get("ok", false)):
		return check
	var rows_any = check.get("rows", [])
	if not (rows_any is Array):
		return {"ok": false, "reason": "equip_slots 结构错误"}
	var rows: Array = rows_any
	for i in range(rows.size()):
		var row_any = rows[i]
		if not (row_any is Dictionary):
			return {"ok": false, "reason": "equip_slots[%d] 必须是对象" % i}
		var row: Dictionary = row_any
		var slot_id := str(row.get("slot_id", row.get("id", ""))).strip_edges()
		if slot_id.is_empty():
			return {"ok": false, "reason": "equip_slots[%d].slot_id 不能为空" % i}
		if int(row.get("equip_limit", 1)) < 1:
			return {"ok": false, "reason": "equip_slots[%d].equip_limit 必须 >= 1" % i}
		if int(row.get("unlock_level", 0)) < 0:
			return {"ok": false, "reason": "equip_slots[%d].unlock_level 不能为负数" % i}
	return {"ok": true}

func validate_equipment_growth_rules_json(text: String) -> Dictionary:
	if text.strip_edges().is_empty():
		return {"ok": false, "reason": "equipment_growth_rules 内容为空"}
	var parsed_any: Variant = JSON.parse_string(text)
	if not (parsed_any is Dictionary):
		return {"ok": false, "reason": "equipment_growth_rules 根节点必须是对象"}
	var root: Dictionary = parsed_any
	var meta_check := _validate_optional_meta(root, "equipment_growth_rules")
	if not bool(meta_check.get("ok", false)):
		return meta_check
	var rules_any = root.get("equipment_growth_rules", {})
	if not (rules_any is Dictionary):
		return {"ok": false, "reason": "缺少 equipment_growth_rules 对象"}
	var rules: Dictionary = rules_any
	var star_caps_any = rules.get("star_caps", {})
	if not (star_caps_any is Dictionary):
		return {"ok": false, "reason": "equipment_growth_rules.star_caps 必须是对象"}
	var max_star := 0
	for value_any in (star_caps_any as Dictionary).values():
		max_star = maxi(max_star, int(value_any))
	if max_star < 1:
		return {"ok": false, "reason": "equipment_growth_rules.star_caps 至少要有一个正整数上限"}
	var stage_check := _validate_star_material_stage_rows(rules, max_star)
	if not bool(stage_check.get("ok", false)):
		return stage_check
	return {"ok": true}

func validate_character_growth_rules_json(text: String) -> Dictionary:
	if text.strip_edges().is_empty():
		return {"ok": false, "reason": "character_growth_rules 内容为空"}
	var parsed_any: Variant = JSON.parse_string(text)
	if not (parsed_any is Dictionary):
		return {"ok": false, "reason": "character_growth_rules 根节点必须是对象"}
	var root: Dictionary = parsed_any
	var meta_check := _validate_optional_meta(root, "character_growth_rules")
	if not bool(meta_check.get("ok", false)):
		return meta_check
	var rules_any = root.get("character_growth_rules", {})
	if not (rules_any is Dictionary):
		return {"ok": false, "reason": "缺少 character_growth_rules 对象"}
	var rules: Dictionary = rules_any
	var level_cap := int(rules.get("level_cap", 0))
	if level_cap < 20:
		return {"ok": false, "reason": "character_growth_rules.level_cap 必须 >= 20"}

	var initial_any = rules.get("initial", {})
	if not (initial_any is Dictionary):
		return {"ok": false, "reason": "character_growth_rules.initial 必须是对象"}
	var initial: Dictionary = initial_any
	if int(initial.get("level", 0)) < 1:
		return {"ok": false, "reason": "character_growth_rules.initial.level 必须 >= 1"}
	if int(initial.get("level", 0)) > level_cap:
		return {"ok": false, "reason": "character_growth_rules.initial.level 不能超过 level_cap"}
	if int(initial.get("free_attr_points", -1)) < 0:
		return {"ok": false, "reason": "character_growth_rules.initial.free_attr_points 不能为负数"}
	if int(initial.get("skill_points", -1)) < 0:
		return {"ok": false, "reason": "character_growth_rules.initial.skill_points 不能为负数"}
	var current_class := str(initial.get("current_class", "")).strip_edges()
	if current_class != "bing" and current_class != "vajra" and current_class != "talisman":
		return {"ok": false, "reason": "character_growth_rules.initial.current_class 非法"}
	var attrs_any = initial.get("base_attributes", {})
	if not (attrs_any is Dictionary):
		return {"ok": false, "reason": "character_growth_rules.initial.base_attributes 必须是对象"}
	for key in ["strength", "physique", "agility", "spirit", "true_energy", "fortune"]:
		if not (attrs_any as Dictionary).has(key):
			return {"ok": false, "reason": "character_growth_rules.initial.base_attributes 缺少 %s" % key}
		if int((attrs_any as Dictionary).get(key, -1)) < 0:
			return {"ok": false, "reason": "character_growth_rules.initial.base_attributes.%s 不能为负数" % key}

	var exp_rows_any = rules.get("level_exp_table", [])
	if not (exp_rows_any is Array):
		return {"ok": false, "reason": "character_growth_rules.level_exp_table 必须是数组"}
	var exp_rows: Array = exp_rows_any
	if exp_rows.size() != level_cap - 1:
		return {"ok": false, "reason": "character_growth_rules.level_exp_table 必须覆盖 1 到 %d 级" % (level_cap - 1)}
	for i in range(exp_rows.size()):
		var row_any = exp_rows[i]
		if not (row_any is Dictionary):
			return {"ok": false, "reason": "character_growth_rules.level_exp_table[%d] 必须是对象" % i}
		var row: Dictionary = row_any
		if int(row.get("level", 0)) != i + 1:
			return {"ok": false, "reason": "character_growth_rules.level_exp_table[%d].level 必须连续递增且从 1 开始" % i}
		if int(row.get("exp_to_next", 0)) <= 0:
			return {"ok": false, "reason": "character_growth_rules.level_exp_table[%d].exp_to_next 必须 > 0" % i}
		if int(row.get("attr_points_gain", -1)) < 0:
			return {"ok": false, "reason": "character_growth_rules.level_exp_table[%d].attr_points_gain 不能为负数" % i}

	var growth_any = rules.get("base_growth", {})
	if not (growth_any is Dictionary):
		return {"ok": false, "reason": "character_growth_rules.base_growth 必须是对象"}
	var growth: Dictionary = growth_any
	for key in ["hp", "qi", "atk", "def", "crit_percent", "loot_bonus_percent"]:
		if not (growth.get(key, null) is Dictionary):
			return {"ok": false, "reason": "character_growth_rules.base_growth.%s 必须是对象" % key}
	if int((growth.get("hp", {}) as Dictionary).get("per_level_every", 0)) < 1:
		return {"ok": false, "reason": "character_growth_rules.base_growth.hp.per_level_every 必须 >= 1"}
	if int((growth.get("qi", {}) as Dictionary).get("per_level_every", 0)) < 1:
		return {"ok": false, "reason": "character_growth_rules.base_growth.qi.per_level_every 必须 >= 1"}

	var formulas_any = rules.get("attribute_formulas", {})
	if not (formulas_any is Dictionary):
		return {"ok": false, "reason": "character_growth_rules.attribute_formulas 必须是对象"}
	var formulas: Dictionary = formulas_any
	for key in ["physique", "true_energy", "agility", "strength", "spirit", "fortune"]:
		if not (formulas.get(key, null) is Dictionary):
			return {"ok": false, "reason": "character_growth_rules.attribute_formulas.%s 必须是对象" % key}

	return {"ok": true}

func validate_progression_milestones_json(text: String) -> Dictionary:
	if text.strip_edges().is_empty():
		return {"ok": false, "reason": "progression_milestones 内容为空"}
	var parsed_any: Variant = JSON.parse_string(text)
	if not (parsed_any is Dictionary):
		return {"ok": false, "reason": "progression_milestones 根节点必须是对象"}
	var root: Dictionary = parsed_any
	var meta_check := _validate_optional_meta(root, "progression_milestones")
	if not bool(meta_check.get("ok", false)):
		return meta_check
	var rules_any = root.get("progression_milestones", {})
	if not (rules_any is Dictionary):
		return {"ok": false, "reason": "缺少 progression_milestones 对象"}
	var rules: Dictionary = rules_any
	if int(rules.get("version", 0)) < 1:
		return {"ok": false, "reason": "progression_milestones.version 必须 >= 1"}
	var range_any = rules.get("range", {})
	if not (range_any is Dictionary):
		return {"ok": false, "reason": "progression_milestones.range 必须是对象"}
	var level_min := int((range_any as Dictionary).get("min_level", 0))
	var level_max := int((range_any as Dictionary).get("max_level", 0))
	if level_min != 1 or level_max != 20:
		return {"ok": false, "reason": "progression_milestones 当前 V1 只允许 1-20 级范围"}
	var rows_any = rules.get("milestones", [])
	if not (rows_any is Array):
		return {"ok": false, "reason": "progression_milestones.milestones 必须是数组"}
	var rows: Array = rows_any
	var allowed_types := ["main_stage", "daily_dungeon", "blue_gear", "feature_unlock"]
	var seen := {}
	for i in range(rows.size()):
		var row_any = rows[i]
		if not (row_any is Dictionary):
			return {"ok": false, "reason": "progression_milestones.milestones[%d] 必须是对象" % i}
		var row: Dictionary = row_any
		var level := int(row.get("level", 0))
		if level < level_min or level > level_max:
			return {"ok": false, "reason": "progression_milestones.milestones[%d].level 必须位于 %d-%d 之间" % [i, level_min, level_max]}
		if seen.has(level):
			return {"ok": false, "reason": "progression_milestones.milestones level 重复：%d" % level}
		seen[level] = true
		if str(row.get("milestone_key", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "progression_milestones.milestones[%d].milestone_key 不能为空" % i}
		if str(row.get("title", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "progression_milestones.milestones[%d].title 不能为空" % i}
		if str(row.get("summary", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "progression_milestones.milestones[%d].summary 不能为空" % i}
		if not (row.get("image", "") is String):
			return {"ok": false, "reason": "progression_milestones.milestones[%d].image 必须是字符串" % i}
		var unlocks_any = row.get("unlock_contents", [])
		if not (unlocks_any is Array):
			return {"ok": false, "reason": "progression_milestones.milestones[%d].unlock_contents 必须是数组" % i}
		for j in range((unlocks_any as Array).size()):
			var unlock_any = (unlocks_any as Array)[j]
			if not (unlock_any is Dictionary):
				return {"ok": false, "reason": "progression_milestones.milestones[%d].unlock_contents[%d] 必须是对象" % [i, j]}
			var unlock_row: Dictionary = unlock_any
			var unlock_type := str(unlock_row.get("type", "")).strip_edges()
			if allowed_types.find(unlock_type) == -1:
				return {"ok": false, "reason": "progression_milestones.milestones[%d].unlock_contents[%d].type 非法" % [i, j]}
			if str(unlock_row.get("content", "")).strip_edges().is_empty():
				return {"ok": false, "reason": "progression_milestones.milestones[%d].unlock_contents[%d].content 不能为空" % [i, j]}
		if str(row.get("reward_item_id", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "progression_milestones.milestones[%d].reward_item_id 不能为空" % i}
		if int(row.get("reward_count", 0)) < 1:
			return {"ok": false, "reason": "progression_milestones.milestones[%d].reward_count 必须 >= 1" % i}
		if int(row.get("sort", -1)) < 0:
			return {"ok": false, "reason": "progression_milestones.milestones[%d].sort 不能为负数" % i}
	return {"ok": true}

func _validate_star_material_stage_rows(rules: Dictionary, max_star: int) -> Dictionary:
	var rows_any = rules.get("star_material_stage", [])
	if not (rows_any is Array):
		return {"ok": false, "reason": "equipment_growth_rules.star_material_stage 必须是数组"}
	var rows: Array = rows_any
	if rows.is_empty():
		return {"ok": false, "reason": "equipment_growth_rules.star_material_stage 不能为空"}

	var ranges: Array = []
	for i in range(rows.size()):
		var row_any = rows[i]
		if not (row_any is Dictionary):
			return {"ok": false, "reason": "equipment_growth_rules.star_material_stage[%d] 必须是对象" % i}
		var row: Dictionary = row_any
		var star_from := int(row.get("star_from", 0))
		var star_to := int(row.get("star_to", 0))
		var material_id := str(row.get("material_id", "")).strip_edges()
		var material_name := str(row.get("material_name", "")).strip_edges()
		var material_count := int(row.get("material_count", 0))
		var sort_value := int(row.get("sort", 0))

		if star_from < 1:
			return {"ok": false, "reason": "equipment_growth_rules.star_material_stage[%d].star_from 必须 >= 1" % i}
		if star_to < 1:
			return {"ok": false, "reason": "equipment_growth_rules.star_material_stage[%d].star_to 必须 >= 1" % i}
		if star_from > star_to:
			return {"ok": false, "reason": "equipment_growth_rules.star_material_stage[%d] 起始星级不能大于结束星级" % i}
		if star_to > max_star:
			return {"ok": false, "reason": "equipment_growth_rules.star_material_stage[%d].star_to 不能超过系统星级上限" % i}
		if material_id.is_empty():
			return {"ok": false, "reason": "equipment_growth_rules.star_material_stage[%d].material_id 不能为空" % i}
		if material_name.is_empty():
			return {"ok": false, "reason": "equipment_growth_rules.star_material_stage[%d].material_name 不能为空" % i}
		if material_count < 1:
			return {"ok": false, "reason": "equipment_growth_rules.star_material_stage[%d].material_count 必须 >= 1" % i}
		if sort_value < 1:
			return {"ok": false, "reason": "equipment_growth_rules.star_material_stage[%d].sort 必须 >= 1" % i}

		ranges.append({
			"index": i,
			"star_from": star_from,
			"star_to": star_to,
		})

	for i in range(ranges.size()):
		var current: Dictionary = ranges[i]
		for j in range(i + 1, ranges.size()):
			var other: Dictionary = ranges[j]
			var overlaps := maxi(int(current.get("star_from", 0)), int(other.get("star_from", 0))) <= mini(int(current.get("star_to", 0)), int(other.get("star_to", 0)))
			if overlaps:
				return {"ok": false, "reason": "equipment_growth_rules.star_material_stage[%d] 与 [%d] 星级区间重叠" % [int(current.get("index", i)), int(other.get("index", j))]}

	return {"ok": true}

func validate_blue_gear_templates_json(text: String) -> Dictionary:
	var check := _validate_catalog_array_json(text, "blue_gear_templates", "blue_gear_templates")
	if not bool(check.get("ok", false)):
		return check
	var rows_any = check.get("rows", [])
	if not (rows_any is Array):
		return {"ok": false, "reason": "blue_gear_templates 结构错误"}
	var rows: Array = rows_any
	for i in range(rows.size()):
		var row_any = rows[i]
		if not (row_any is Dictionary):
			return {"ok": false, "reason": "blue_gear_templates[%d] 必须是对象" % i}
		var row: Dictionary = row_any
		if str(row.get("id", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "blue_gear_templates[%d].id 不能为空" % i}
		if str(row.get("slot_id", row.get("slot", ""))).strip_edges().is_empty():
			return {"ok": false, "reason": "blue_gear_templates[%d].slot_id 不能为空" % i}
	return {"ok": true}

func validate_blue_affix_pool_json(text: String) -> Dictionary:
	return _validate_affix_pool_json(text, "blue_affix_pool", "blue_affix_pool")

func validate_purple_affix_pool_json(text: String) -> Dictionary:
	return _validate_affix_pool_json(text, "purple_affix_pool", "purple_affix_pool")

func validate_gem_catalog_json(text: String) -> Dictionary:
	var check := _validate_catalog_array_json(text, "gem_catalog", "gem_catalog")
	if not bool(check.get("ok", false)):
		return check
	var rows_any = check.get("rows", [])
	if not (rows_any is Array):
		return {"ok": false, "reason": "gem_catalog 结构错误"}
	var rows: Array = rows_any
	for i in range(rows.size()):
		var row_any = rows[i]
		if not (row_any is Dictionary):
			return {"ok": false, "reason": "gem_catalog[%d] 必须是对象" % i}
		var row: Dictionary = row_any
		var gem_check := _validate_gem_row(row, "gem_catalog", i)
		if not bool(gem_check.get("ok", false)):
			return gem_check
	return {"ok": true}

func _validate_gem_row(row: Dictionary, root_key: String, index: int) -> Dictionary:
	if str(row.get("id", "")).strip_edges().is_empty():
		return {"ok": false, "reason": "%s[%d].id 不能为空" % [root_key, index]}
	var gem_type := str(row.get("gem_type", row.get("sub_type", ""))).strip_edges()
	if gem_type != "attr" and gem_type != "skill":
		return {"ok": false, "reason": "%s[%d].gem_type 非法" % [root_key, index]}
	var effect_type := str(row.get("effect_type", "")).strip_edges()
	if gem_type == "attr" and effect_type != "stat":
		return {"ok": false, "reason": "%s[%d].effect_type 必须是 stat" % [root_key, index]}
	if gem_type == "skill" and effect_type != "skill_modifier":
		return {"ok": false, "reason": "%s[%d].effect_type 必须是 skill_modifier" % [root_key, index]}
	var payload_check := _validate_gem_effect_payload(row.get("effect_payload", {}), gem_type, "%s[%d]" % [root_key, index])
	if not bool(payload_check.get("ok", false)):
		return payload_check
	if gem_type == "skill" and str(row.get("target_scope", "")).strip_edges().is_empty():
		return {"ok": false, "reason": "%s[%d].target_scope 不能为空" % [root_key, index]}
	return {"ok": true}

func _validate_gem_effect_payload(payload_any: Variant, gem_type: String, path: String) -> Dictionary:
	if not (payload_any is Dictionary):
		return {"ok": false, "reason": "%s.effect_payload 必须是对象" % path}
	var payload: Dictionary = payload_any
	var effect_code := str(payload.get("effect_code", "")).strip_edges()
	if effect_code.is_empty():
		return {"ok": false, "reason": "%s.effect_payload.effect_code 不能为空" % path}
	var params_any: Variant = payload.get("params", {})
	if not (params_any is Dictionary):
		return {"ok": false, "reason": "%s.effect_payload.params 必须是对象" % path}
	var params: Dictionary = params_any
	if gem_type == "attr":
		if effect_code != "add_attr":
			return {"ok": false, "reason": "%s.effect_payload.effect_code 必须是 add_attr" % path}
		var stat := str(params.get("stat", "")).strip_edges()
		if stat.is_empty():
			return {"ok": false, "reason": "%s.effect_payload.params.stat 不能为空" % path}
		if not params.has("value"):
			return {"ok": false, "reason": "%s.effect_payload.params.value 不能为空" % path}
		var value_any: Variant = params.get("value", null)
		if not (value_any is int or value_any is float):
			return {"ok": false, "reason": "%s.effect_payload.params.value 必须是数字" % path}
		var value_type := str(params.get("value_type", "flat")).strip_edges()
		if value_type != "flat" and value_type != "percent":
			return {"ok": false, "reason": "%s.effect_payload.params.value_type 非法" % path}
		for key_any in params.keys():
			var key := str(key_any)
			if key != "stat" and key != "value" and key != "value_type":
				return {"ok": false, "reason": "%s.effect_payload.params.%s 非法" % [path, key]}
		return {"ok": true}

	var skill_templates := {
		"damage_up": ["damage_multiplier"],
		"range_up": ["range_multiplier"],
		"crit_up": ["crit_rate_bonus"],
		"shield_up": ["shield_multiplier"],
		"cooldown_down": ["cooldown_reduction"],
		"duration_up": ["duration_multiplier"],
		"burn_up": ["burn_multiplier"],
		"slow_up": ["slow_multiplier"],
		"mana_cost_down": ["mana_cost_reduction"],
	}
	if not skill_templates.has(effect_code):
		return {"ok": false, "reason": "%s.effect_payload.effect_code 未知" % path}
	var required_params: Array = skill_templates.get(effect_code, [])
	for param_key_any in required_params:
		var param_key := str(param_key_any)
		if not params.has(param_key):
			return {"ok": false, "reason": "%s.effect_payload.params.%s 不能为空" % [path, param_key]}
		var param_value: Variant = params.get(param_key, null)
		if not (param_value is int or param_value is float):
			return {"ok": false, "reason": "%s.effect_payload.params.%s 必须是数字" % [path, param_key]}
	for key_any in params.keys():
		var key := str(key_any)
		if not required_params.has(key):
			return {"ok": false, "reason": "%s.effect_payload.params.%s 非法" % [path, key]}
	return {"ok": true}

func _validate_item_use_effect_payload(row: Dictionary, index: int) -> Dictionary:
	var payload_any: Variant = row.get("effect_payload", {})
	if not (payload_any is Dictionary):
		return {"ok": false, "reason": "items[%d].effect_payload 必须是对象" % index}
	var payload: Dictionary = payload_any
	var effect_code := str(payload.get("effect_code", "")).strip_edges()
	if effect_code.is_empty():
		return {"ok": false, "reason": "items[%d].effect_payload.effect_code 不能为空" % index}
	var params_any: Variant = payload.get("params", {})
	if not (params_any is Dictionary):
		return {"ok": false, "reason": "items[%d].effect_payload.params 必须是对象" % index}
	var params: Dictionary = params_any
	var templates := {
		"restore_stamina": ["stamina_amount"],
		"grant_exp": ["exp_amount"],
		"grant_item": ["reward_item_id", "reward_count"],
		"reset_dungeon_attempt": ["dungeon_id", "reset_count"],
	}
	if not templates.has(effect_code):
		return {"ok": false, "reason": "items[%d].effect_payload.effect_code 未知" % index}
	var required_params: Array = templates.get(effect_code, [])
	for param_key_any in required_params:
		var param_key := str(param_key_any)
		if not params.has(param_key):
			return {"ok": false, "reason": "items[%d].effect_payload.params.%s 不能为空" % [index, param_key]}
		var param_value: Variant = params.get(param_key, null)
		if param_key == "reward_item_id" or param_key == "dungeon_id":
			if str(param_value).strip_edges().is_empty():
				return {"ok": false, "reason": "items[%d].effect_payload.params.%s 不能为空" % [index, param_key]}
		elif not (param_value is int or param_value is float) or int(param_value) < 1:
			return {"ok": false, "reason": "items[%d].effect_payload.params.%s 必须是正数" % [index, param_key]}
	for key_any in params.keys():
		var key := str(key_any)
		if not required_params.has(key):
			return {"ok": false, "reason": "items[%d].effect_payload.params.%s 非法" % [index, key]}
	return {"ok": true}

func validate_material_catalog_json(text: String) -> Dictionary:
	var check := _validate_catalog_array_json(text, "material_catalog", "material_catalog")
	if not bool(check.get("ok", false)):
		return check
	var rows_any = check.get("rows", [])
	if not (rows_any is Array):
		return {"ok": false, "reason": "material_catalog 结构错误"}
	var rows: Array = rows_any
	for i in range(rows.size()):
		var row_any = rows[i]
		if not (row_any is Dictionary):
			return {"ok": false, "reason": "material_catalog[%d] 必须是对象" % i}
		var row: Dictionary = row_any
		if str(row.get("id", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "material_catalog[%d].id 不能为空" % i}
	return {"ok": true}

func validate_material_dungeons_json(text: String) -> Dictionary:
	var check := _validate_catalog_array_json(text, "material_dungeons", "material_dungeons")
	if not bool(check.get("ok", false)):
		return check
	var parsed_any: Variant = JSON.parse_string(text)
	if not (parsed_any is Dictionary):
		return {"ok": false, "reason": "material_dungeons 根节点必须是对象"}
	var root: Dictionary = parsed_any
	var rows_any = check.get("rows", [])
	if not (rows_any is Array):
		return {"ok": false, "reason": "material_dungeons 结构错误"}
	var group_rows_any = root.get("material_dungeon_drop_groups", [])
	if not (group_rows_any is Array):
		return {"ok": false, "reason": "缺少 material_dungeon_drop_groups 数组"}
	var group_rows: Array = group_rows_any
	var valid_group_ids := {}
	for g in range(group_rows.size()):
		var group_any = group_rows[g]
		if not (group_any is Dictionary):
			return {"ok": false, "reason": "material_dungeon_drop_groups[%d] 必须是对象" % g}
		var group_check := _validate_material_dungeon_drop_group(group_any as Dictionary, g)
		if not bool(group_check.get("ok", false)):
			return group_check
		valid_group_ids[str((group_any as Dictionary).get("group_id", ""))] = true
	var rows: Array = rows_any
	for i in range(rows.size()):
		var row_any = rows[i]
		if not (row_any is Dictionary):
			return {"ok": false, "reason": "material_dungeons[%d] 必须是对象" % i}
		var row: Dictionary = row_any
		if str(row.get("dungeon_id", row.get("id", ""))).strip_edges().is_empty():
			return {"ok": false, "reason": "material_dungeons[%d].dungeon_id 不能为空" % i}
		if row.has("unlock_stage_id") and row.get("unlock_stage_id", null) != null and str(row.get("unlock_stage_id", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "material_dungeons[%d].unlock_stage_id 不能为空字符串" % i}
		var display_rewards_any = row.get("display_rewards", [])
		if not (display_rewards_any is Array):
			return {"ok": false, "reason": "material_dungeons[%d].display_rewards 必须是数组" % i}
		for reward_index in range((display_rewards_any as Array).size()):
			if str((display_rewards_any as Array)[reward_index]).strip_edges().is_empty():
				return {"ok": false, "reason": "material_dungeons[%d].display_rewards[%d] 不能为空" % [i, reward_index]}
		var level_configs_any = row.get("level_configs", [])
		if not (level_configs_any is Array):
			return {"ok": false, "reason": "material_dungeons[%d].level_configs 必须是数组" % i}
		if (level_configs_any as Array).is_empty():
			return {"ok": false, "reason": "material_dungeons[%d].level_configs 不能为空" % i}
		for level_index in range((level_configs_any as Array).size()):
			var level_any = (level_configs_any as Array)[level_index]
			if not (level_any is Dictionary):
				return {"ok": false, "reason": "material_dungeons[%d].level_configs[%d] 必须是对象" % [i, level_index]}
			var level_row: Dictionary = level_any
			if int(level_row.get("level", 0)) != level_index + 1:
				return {"ok": false, "reason": "material_dungeons[%d].level_configs[%d].level 必须从1开始连续" % [i, level_index]}
			if float(level_row.get("reward_multiplier", 0.0)) < 1.0:
				return {"ok": false, "reason": "material_dungeons[%d].level_configs[%d].reward_multiplier 必须 >= 1" % [i, level_index]}
			var upgrade_costs_any = level_row.get("upgrade_costs", [])
			if not (upgrade_costs_any is Array):
				return {"ok": false, "reason": "material_dungeons[%d].level_configs[%d].upgrade_costs 必须是数组" % [i, level_index]}
			if level_index > 0 and (upgrade_costs_any as Array).is_empty():
				return {"ok": false, "reason": "material_dungeons[%d].level_configs[%d].upgrade_costs 不能为空" % [i, level_index]}
			for cost_index in range((upgrade_costs_any as Array).size()):
				var cost_any = (upgrade_costs_any as Array)[cost_index]
				if not (cost_any is Dictionary):
					return {"ok": false, "reason": "material_dungeons[%d].level_configs[%d].upgrade_costs[%d] 必须是对象" % [i, level_index, cost_index]}
				var cost: Dictionary = cost_any
				if str(cost.get("item_id", "")).strip_edges().is_empty():
					return {"ok": false, "reason": "material_dungeons[%d].level_configs[%d].upgrade_costs[%d].item_id 不能为空" % [i, level_index, cost_index]}
				if int(cost.get("count", 0)) < 1:
					return {"ok": false, "reason": "material_dungeons[%d].level_configs[%d].upgrade_costs[%d].count 必须 >= 1" % [i, level_index, cost_index]}
		var layer_rules_any = row.get("layer_rules", [])
		if not (layer_rules_any is Array):
			return {"ok": false, "reason": "material_dungeons[%d].layer_rules 必须是数组" % i}
		if (layer_rules_any as Array).is_empty():
			return {"ok": false, "reason": "material_dungeons[%d].layer_rules 不能为空" % i}
		var seen_layers := {}
		for rule_index in range((layer_rules_any as Array).size()):
			var rule_any = (layer_rules_any as Array)[rule_index]
			if not (rule_any is Dictionary):
				return {"ok": false, "reason": "material_dungeons[%d].layer_rules[%d] 必须是对象" % [i, rule_index]}
			var rule: Dictionary = rule_any
			var layer := int(rule.get("layer", 0))
			if layer < 1:
				return {"ok": false, "reason": "material_dungeons[%d].layer_rules[%d].layer 非法" % [i, rule_index]}
			if seen_layers.has(layer):
				return {"ok": false, "reason": "material_dungeons[%d].layer_rules[%d].layer 重复" % [i, rule_index]}
			seen_layers[layer] = true
			var drop_group_id := str(rule.get("drop_group_id", "")).strip_edges()
			if drop_group_id.is_empty():
				return {"ok": false, "reason": "material_dungeons[%d].layer_rules[%d].drop_group_id 不能为空" % [i, rule_index]}
			if not valid_group_ids.has(drop_group_id):
				return {"ok": false, "reason": "material_dungeons[%d].layer_rules[%d].drop_group_id 无效" % [i, rule_index]}
			var first_clear_value: Variant = rule.get("first_clear_reward_group_id", "")
			var first_clear_group_id := ""
			if first_clear_value != null:
				first_clear_group_id = str(first_clear_value).strip_edges()
			if not first_clear_group_id.is_empty() and first_clear_group_id != "Null" and not valid_group_ids.has(first_clear_group_id):
				return {"ok": false, "reason": "material_dungeons[%d].layer_rules[%d].first_clear_reward_group_id 无效" % [i, rule_index]}
			if rule.has("recommended_power") and rule.get("recommended_power", null) != null and int(rule.get("recommended_power", 0)) < 0:
				return {"ok": false, "reason": "material_dungeons[%d].layer_rules[%d].recommended_power 非法" % [i, rule_index]}
	return {"ok": true}

func _validate_material_dungeon_drop_group(group: Dictionary, index: int) -> Dictionary:
	var group_id := str(group.get("group_id", "")).strip_edges()
	if group_id.is_empty():
		return {"ok": false, "reason": "material_dungeon_drop_groups[%d].group_id 不能为空" % index}
	if str(group.get("name", "")).strip_edges().is_empty():
		return {"ok": false, "reason": "material_dungeon_drop_groups[%d].name 不能为空" % index}
	var rewards_any = group.get("rewards", [])
	if not (rewards_any is Array):
		return {"ok": false, "reason": "material_dungeon_drop_groups[%d].rewards 必须是数组" % index}
	if (rewards_any as Array).is_empty():
		return {"ok": false, "reason": "material_dungeon_drop_groups[%d].rewards 不能为空" % index}
	for reward_index in range((rewards_any as Array).size()):
		var reward_any = (rewards_any as Array)[reward_index]
		if not (reward_any is Dictionary):
			return {"ok": false, "reason": "material_dungeon_drop_groups[%d].rewards[%d] 必须是对象" % [index, reward_index]}
		var reward: Dictionary = reward_any
		if str(reward.get("item_id", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "material_dungeon_drop_groups[%d].rewards[%d].item_id 不能为空" % [index, reward_index]}
		var count_min := int(reward.get("count_min", 0))
		var count_max := int(reward.get("count_max", 0))
		if count_min < 1 or count_max < count_min:
			return {"ok": false, "reason": "material_dungeon_drop_groups[%d].rewards[%d] count_min/count_max 非法" % [index, reward_index]}
		var probability_any: Variant = reward.get("probability", 0.0)
		if not (probability_any is int or probability_any is float) or float(probability_any) <= 0.0 or float(probability_any) > 1.0:
			return {"ok": false, "reason": "material_dungeon_drop_groups[%d].rewards[%d].probability 非法" % [index, reward_index]}
	return {"ok": true}

func validate_crafting_recipes_json(text: String) -> Dictionary:
	var check := _validate_catalog_array_json(text, "crafting_recipes", "crafting_recipes")
	if not bool(check.get("ok", false)):
		return check
	var rows_any = check.get("rows", [])
	if not (rows_any is Array):
		return {"ok": false, "reason": "crafting_recipes 结构错误"}
	var rows: Array = rows_any
	for i in range(rows.size()):
		var row_any = rows[i]
		if not (row_any is Dictionary):
			return {"ok": false, "reason": "crafting_recipes[%d] 必须是对象" % i}
		var row: Dictionary = row_any
		if str(row.get("recipe_id", row.get("id", ""))).strip_edges().is_empty():
			return {"ok": false, "reason": "crafting_recipes[%d].recipe_id 不能为空" % i}
	return {"ok": true}

func _validate_affix_pool_json(text: String, root_key: String, expected_meta_key: String) -> Dictionary:
	var check := _validate_catalog_array_json(text, root_key, expected_meta_key)
	if not bool(check.get("ok", false)):
		return check
	var rows_any = check.get("rows", [])
	if not (rows_any is Array):
		return {"ok": false, "reason": "%s 结构错误" % root_key}
	var rows: Array = rows_any
	for i in range(rows.size()):
		var row_any = rows[i]
		if not (row_any is Dictionary):
			return {"ok": false, "reason": "%s[%d] 必须是对象" % [root_key, i]}
		var row: Dictionary = row_any
		if str(row.get("affix_id", row.get("id", ""))).strip_edges().is_empty():
			return {"ok": false, "reason": "%s[%d].affix_id 不能为空" % [root_key, i]}
		var min_v := float(row.get("min_value", 0.0))
		var max_v := float(row.get("max_value", 0.0))
		if max_v < min_v:
			return {"ok": false, "reason": "%s[%d] min_value/max_value 非法" % [root_key, i]}
	return {"ok": true}

func _validate_catalog_array_json(text: String, root_key: String, expected_meta_key: String) -> Dictionary:
	if text.strip_edges().is_empty():
		return {"ok": false, "reason": "%s 内容为空" % root_key}
	var parsed_any: Variant = JSON.parse_string(text)
	if not (parsed_any is Dictionary):
		return {"ok": false, "reason": "%s 根节点必须是对象" % root_key}
	var root: Dictionary = parsed_any
	var meta_check := _validate_optional_meta(root, expected_meta_key)
	if not bool(meta_check.get("ok", false)):
		return meta_check
	var rows_any = root.get(root_key, null)
	if not (rows_any is Array):
		return {"ok": false, "reason": "缺少 %s 数组" % root_key}
	return {"ok": true, "rows": rows_any}

func is_valid_refine_effect_pool(pool_any: Variant) -> bool:
	var ret := _validate_refine_effect_pool(pool_any)
	return bool(ret.get("ok", false))

func _validate_refine_effect_pool(pool_any: Variant) -> Dictionary:
	if not (pool_any is Array):
		return {"ok": false, "reason": "refine_effect_pool 必须是数组"}
	var pool: Array = pool_any
	if pool.is_empty():
		return {"ok": false, "reason": "refine_effect_pool 不能为空"}

	var total_w := 0
	for i in range(pool.size()):
		var row_any: Variant = pool[i]
		if not (row_any is Dictionary):
			return {"ok": false, "reason": "refine_effect_pool[%d] 必须是对象" % i}
		var row: Dictionary = row_any
		var w := int(row.get("w", -1))
		if w < 0:
			return {"ok": false, "reason": "refine_effect_pool[%d].w 不能为负数" % i}
		var val := int(row.get("val", -1))
		if val < 0:
			return {"ok": false, "reason": "refine_effect_pool[%d].val 不能为负数" % i}
		var effect_type := str(row.get("type", "")).strip_edges()
		if effect_type != "stat" and effect_type != "skill_level":
			return {"ok": false, "reason": "refine_effect_pool[%d].type 仅支持 stat/skill_level" % i}
		if effect_type == "stat":
			var stat := str(row.get("stat", "")).strip_edges()
			if stat.is_empty():
				return {"ok": false, "reason": "refine_effect_pool[%d].stat 不能为空" % i}
		else:
			var skill_id := str(row.get("skill_id", "")).strip_edges()
			if skill_id.is_empty():
				return {"ok": false, "reason": "refine_effect_pool[%d].skill_id 不能为空" % i}
		total_w += maxi(0, w)
	if total_w <= 0:
		return {"ok": false, "reason": "refine_effect_pool 权重总和必须 > 0"}
	return {"ok": true}

func download_bundle(on_done: Callable) -> void:
	_download_text(get_bundle_manifest_url(), func(ok: bool, text: String, msg: String) -> void:
		if not ok:
			_call_done(on_done, false, "配置包更新失败：%s" % msg)
			return

		var manifest_check := _parse_manifest_info(text)
		if not bool(manifest_check.get("ok", false)):
			_call_done(on_done, false, "配置包更新失败：%s" % str(manifest_check.get("reason", "manifest 校验失败")))
			return

		var bundle_id := str(manifest_check.get("bundle_id", "")).strip_edges()
		var files_any = manifest_check.get("files", [])
		if bundle_id.is_empty() or not (files_any is Array):
			_call_done(on_done, false, "配置包更新失败：manifest 缺少必要信息")
			return
		var files: Array = files_any

		var tmp_bundle_dir := "%s/%s" % [TMP_DIR, bundle_id]
		_remove_dir_recursive_absolute(tmp_bundle_dir)
		var mk_tmp_err := DirAccess.make_dir_recursive_absolute(tmp_bundle_dir)
		if mk_tmp_err != OK:
			_call_done(on_done, false, "配置包更新失败：无法创建临时目录（%d）" % mk_tmp_err)
			return

		_download_bundle_file_recursive(0, files, tmp_bundle_dir, text, bundle_id, on_done)
	)

func download_all(on_done: Callable) -> void:
	download_bundle(func(ok: bool, msg: String) -> void:
		var results: Array[Dictionary] = []
		for key in FILE_KEY_ORDER:
			_append_download_result(results, str(key), ok, msg)
		_call_done_results(on_done, results)
	)

func _download_bundle_file_recursive(index: int, files: Array, tmp_bundle_dir: String, manifest_text: String, bundle_id: String, on_done: Callable) -> void:
	if index >= files.size():
		var activate_err := _activate_bundle(files, tmp_bundle_dir, manifest_text)
		_remove_dir_recursive_absolute(tmp_bundle_dir)
		if not activate_err.is_empty():
			_call_done(on_done, false, "配置包更新失败：%s" % activate_err)
			return
		_call_done(on_done, true, "配置包更新成功（bundle_id=%s）" % bundle_id)
		return

	var row_any: Variant = files[index]
	if not (row_any is Dictionary):
		_remove_dir_recursive_absolute(tmp_bundle_dir)
		_call_done(on_done, false, "配置包更新失败：manifest 文件项结构错误")
		return
	var row: Dictionary = row_any
	var key := str(row.get("key", "")).strip_edges()
	var filename := str(row.get("filename", "")).strip_edges()
	var expect_sha := str(row.get("sha256", "")).strip_edges().to_lower()
	if key.is_empty() or filename.is_empty() or expect_sha.is_empty():
		_remove_dir_recursive_absolute(tmp_bundle_dir)
		_call_done(on_done, false, "配置包更新失败：manifest 文件项字段缺失")
		return

	_download_text(get_bundle_file_url(filename), func(ok: bool, text: String, msg: String) -> void:
		if not ok:
			_remove_dir_recursive_absolute(tmp_bundle_dir)
			_call_done(on_done, false, "配置包更新失败：%s 下载失败（%s）" % [key, msg])
			return

		var payload_check: Dictionary = _validate_payload_by_key(key, text)
		if not bool(payload_check.get("ok", false)):
			_remove_dir_recursive_absolute(tmp_bundle_dir)
			_call_done(on_done, false, "配置包更新失败：%s 校验失败（%s）" % [key, str(payload_check.get("reason", "未知错误"))])
			return

		var actual_sha := _sha256_text(text)
		if actual_sha != expect_sha:
			_remove_dir_recursive_absolute(tmp_bundle_dir)
			_call_done(on_done, false, "配置包更新失败：%s 哈希不匹配" % key)
			return

		var write_err := _write_text("%s/%s" % [tmp_bundle_dir, filename], text)
		if not write_err.is_empty():
			_remove_dir_recursive_absolute(tmp_bundle_dir)
			_call_done(on_done, false, "配置包更新失败：%s 写入临时文件失败（%s）" % [key, write_err])
			return

		_download_bundle_file_recursive(index + 1, files, tmp_bundle_dir, manifest_text, bundle_id, on_done)
	)

func _validate_bundle_manifest(text: String) -> Dictionary:
	if text.strip_edges().is_empty():
		return {"ok": false, "reason": "manifest 内容为空"}
	var parsed_any: Variant = JSON.parse_string(text)
	if not (parsed_any is Dictionary):
		return {"ok": false, "reason": "manifest 必须是 JSON 对象"}
	var parsed: Dictionary = parsed_any

	var meta_any: Variant = parsed.get("meta", {})
	if not (meta_any is Dictionary):
		return {"ok": false, "reason": "manifest.meta 缺失"}
	var meta: Dictionary = meta_any
	var bundle_id := str(meta.get("bundle_id", "")).strip_edges()
	if bundle_id.is_empty():
		return {"ok": false, "reason": "manifest.meta.bundle_id 不能为空"}
	var generated_at := str(meta.get("generated_at", "")).strip_edges()
	if generated_at.is_empty():
		return {"ok": false, "reason": "manifest.meta.generated_at 不能为空"}

	var files_any: Variant = parsed.get("files", [])
	if not (files_any is Array):
		return {"ok": false, "reason": "manifest.files 必须是数组"}
	var files_arr: Array = files_any
	if files_arr.is_empty():
		return {"ok": false, "reason": "manifest.files 不能为空"}

	var by_key: Dictionary = {}
	for item_any in files_arr:
		if not (item_any is Dictionary):
			return {"ok": false, "reason": "manifest.files 项必须是对象"}
		var item: Dictionary = item_any
		var key := str(item.get("key", "")).strip_edges()
		var filename := str(item.get("filename", "")).strip_edges()
		var sha256 := str(item.get("sha256", "")).strip_edges()
		var updated_at := str(item.get("updated_at", "")).strip_edges()
		var size := int(item.get("size", -1))
		if key.is_empty() or filename.is_empty() or sha256.is_empty():
			return {"ok": false, "reason": "manifest.files 项缺少 key/filename/sha256"}
		if size < 0:
			return {"ok": false, "reason": "manifest.files.size 必须 >= 0"}
		if updated_at.is_empty():
			return {"ok": false, "reason": "manifest.files.updated_at 不能为空"}
		if not FILE_KEY_TO_NAME.has(key):
			continue
		by_key[key] = {
			"key": key,
			"filename": filename,
			"version": maxi(0, int(item.get("version", 0))),
			"sha256": sha256.to_lower(),
			"size": size,
			"updated_at": updated_at,
		}

	var ordered: Array[Dictionary] = []
	for key_any in FILE_KEY_ORDER:
		var key := str(key_any)
		if not by_key.has(key):
			return {"ok": false, "reason": "manifest 缺少文件项：%s" % key}
		var row_any = by_key.get(key, {})
		if not (row_any is Dictionary):
			return {"ok": false, "reason": "manifest 文件项格式错误：%s" % key}
		var row: Dictionary = row_any
		var expected_filename := str(FILE_KEY_TO_NAME.get(key, ""))
		var actual_filename := str(row.get("filename", ""))
		if actual_filename != expected_filename:
			return {"ok": false, "reason": "%s 文件名不匹配：%s" % [key, actual_filename]}
		ordered.append(row)

	for key_any in OPTIONAL_FILE_KEYS:
		var key := str(key_any)
		if not by_key.has(key):
			continue
		var row_any = by_key.get(key, {})
		if not (row_any is Dictionary):
			return {"ok": false, "reason": "manifest 文件项格式错误：%s" % key}
		var row: Dictionary = row_any
		var expected_filename := str(FILE_KEY_TO_NAME.get(key, ""))
		var actual_filename := str(row.get("filename", ""))
		if actual_filename != expected_filename:
			return {"ok": false, "reason": "%s 文件名不匹配：%s" % [key, actual_filename]}
		ordered.append(row)

	return {
		"ok": true,
		"bundle_id": bundle_id,
		"files": ordered,
	}

func _parse_manifest_info(text: String) -> Dictionary:
	var check := _validate_bundle_manifest(text)
	if not bool(check.get("ok", false)):
		return check
	var parsed_any: Variant = JSON.parse_string(text)
	if not (parsed_any is Dictionary):
		return {"ok": false, "reason": "manifest 必须是 JSON 对象"}
	var parsed: Dictionary = parsed_any
	var meta_any = parsed.get("meta", {})
	var meta: Dictionary = meta_any if meta_any is Dictionary else {}
	return {
		"ok": true,
		"bundle_id": str(check.get("bundle_id", "")),
		"files": check.get("files", []),
		"manifest": parsed,
		"meta": meta.duplicate(true),
	}

func _find_manifest_row(manifest_info: Dictionary, key: String) -> Dictionary:
	var files_any = manifest_info.get("files", [])
	if not (files_any is Array):
		return {}
	for row_any in files_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		if str(row.get("key", "")).strip_edges() == key.strip_edges():
			return row.duplicate(true)
	return {}

func _validate_payload_by_key(key: String, text: String) -> Dictionary:
	match key:
		"stages":
			return validate_stages_json(text)
		"items":
			return validate_items_json(text)
		"equip_templates":
			return validate_equip_templates_json(text)
		"equipment_sets":
			return validate_equipment_sets_json(text)
		"equip_slots":
			return validate_equip_slots_json(text)
		"equipment_growth_rules":
			return validate_equipment_growth_rules_json(text)
		"character_growth_rules":
			return validate_character_growth_rules_json(text)
		"progression_milestones":
			return validate_progression_milestones_json(text)
		"blue_gear_templates":
			return validate_blue_gear_templates_json(text)
		"blue_affix_pool":
			return validate_blue_affix_pool_json(text)
		"purple_affix_pool":
			return validate_purple_affix_pool_json(text)
		"gem_catalog":
			return validate_gem_catalog_json(text)
		"material_catalog":
			return validate_material_catalog_json(text)
		"material_dungeons":
			return validate_material_dungeons_json(text)
		"sect_tasks":
			return validate_sect_tasks_json(text)
		"mountain_god":
			return validate_mountain_god_json(text)
		"shop_goods":
			return validate_shop_goods_json(text)
		"crafting_recipes":
			return validate_crafting_recipes_json(text)
		"monsters":
			return validate_monsters_json(text)
		"skills_catalog":
			return validate_skills_catalog(text)
		"battle_defaults":
			return validate_battle_defaults(text)
		"star_rules":
			return validate_star_rules_json(text)
		"forge_rules":
			return validate_forge_rules_json(text)
		_:
			return {"ok": false, "reason": "未知配置 key：%s" % key}

func _activate_bundle(files: Array, tmp_bundle_dir: String, manifest_text: String) -> String:
	var backup_dir := "%s_prev" % ACTIVE_DIR
	_remove_dir_recursive_absolute(backup_dir)
	if DirAccess.dir_exists_absolute(ACTIVE_DIR):
		var backup_err := _copy_dir_recursive_absolute(ACTIVE_DIR, backup_dir)
		if not backup_err.is_empty():
			return "active 切换失败：备份旧配置失败（%s）" % backup_err

	_remove_dir_recursive_absolute(ACTIVE_DIR)
	var mk_active_err := DirAccess.make_dir_recursive_absolute(ACTIVE_DIR)
	if mk_active_err != OK:
		if DirAccess.dir_exists_absolute(backup_dir):
			_remove_dir_recursive_absolute(ACTIVE_DIR)
			_copy_dir_recursive_absolute(backup_dir, ACTIVE_DIR)
		return "无法创建 active 目录（%d）" % mk_active_err

	for row_any in files:
		if not (row_any is Dictionary):
			_restore_active_from_backup(backup_dir)
			return "active 切换失败：文件列表项结构错误"
		var row: Dictionary = row_any
		var filename := str(row.get("filename", "")).strip_edges()
		if filename.is_empty():
			_restore_active_from_backup(backup_dir)
			return "active 切换失败：文件名为空"
		var src_path := "%s/%s" % [tmp_bundle_dir, filename]
		if not FileAccess.file_exists(src_path):
			_restore_active_from_backup(backup_dir)
			return "active 切换失败：临时文件缺失 %s" % filename
		var content := FileAccess.get_file_as_string(src_path)
		var write_err := _write_text("%s/%s" % [ACTIVE_DIR, filename], content)
		if not write_err.is_empty():
			_restore_active_from_backup(backup_dir)
			return "active 切换失败：写入 %s 失败（%s）" % [filename, write_err]

	var mk_root_err := DirAccess.make_dir_recursive_absolute(ACTIVE_ROOT)
	if mk_root_err != OK:
		_restore_active_from_backup(backup_dir)
		return "active 切换失败：无法创建 remote 根目录（%d）" % mk_root_err
	var manifest_write_err := _write_text(ACTIVE_MANIFEST, manifest_text)
	if not manifest_write_err.is_empty():
		_restore_active_from_backup(backup_dir)
		return "active 切换失败：写入 active_manifest 失败（%s）" % manifest_write_err

	_remove_dir_recursive_absolute(backup_dir)
	return ""

func _download_text(url: String, on_done: Callable) -> void:
	var trimmed := url.strip_edges()
	if trimmed.is_empty():
		if on_done.is_valid():
			on_done.call(false, "", "URL为空")
		return

	var req := HTTPRequest.new()
	req.request_completed.connect(_on_download_text_completed.bind(req, on_done), CONNECT_ONE_SHOT)
	add_child(req)
	var err := req.request(trimmed)
	if err != OK:
		if is_instance_valid(req):
			req.queue_free()
		if on_done.is_valid():
			on_done.call(false, "", "请求启动失败（%d）" % err)

func _on_download_text_completed(result: int, response_code: int, _headers: PackedStringArray, body: PackedByteArray, req: HTTPRequest, on_done: Callable) -> void:
	if is_instance_valid(req):
		req.queue_free()
	if result != HTTPRequest.RESULT_SUCCESS:
		if on_done.is_valid():
			on_done.call(false, "", "网络错误（%d）" % result)
		return
	if response_code < 200 or response_code >= 300:
		if on_done.is_valid():
			on_done.call(false, "", "HTTP %d" % response_code)
		return
	if on_done.is_valid():
		on_done.call(true, body.get_string_from_utf8(), "ok")

func _write_text(path: String, text: String) -> String:
	var parent_idx := path.rfind("/")
	if parent_idx > 0:
		var parent := path.substr(0, parent_idx)
		var mk_err := DirAccess.make_dir_recursive_absolute(parent)
		if mk_err != OK:
			return "创建目录失败（%d）" % mk_err
	var file := FileAccess.open(path, FileAccess.WRITE)
	if file == null:
		return "打开文件失败"
	file.store_string(text)
	file.flush()
	file.close()
	return ""

func _copy_dir_recursive_absolute(src: String, dst: String) -> String:
	if not DirAccess.dir_exists_absolute(src):
		return "源目录不存在"
	_remove_dir_recursive_absolute(dst)
	var mk_err := DirAccess.make_dir_recursive_absolute(dst)
	if mk_err != OK:
		return "创建目标目录失败（%d）" % mk_err
	var dir := DirAccess.open(src)
	if dir == null:
		return "打开源目录失败"
	dir.list_dir_begin()
	var name := dir.get_next()
	while name != "":
		if name != "." and name != "..":
			var src_path := "%s/%s" % [src, name]
			var dst_path := "%s/%s" % [dst, name]
			if dir.current_is_dir():
				var sub_err := _copy_dir_recursive_absolute(src_path, dst_path)
				if not sub_err.is_empty():
					dir.list_dir_end()
					return sub_err
			else:
				var bytes := FileAccess.get_file_as_bytes(src_path)
				var file := FileAccess.open(dst_path, FileAccess.WRITE)
				if file == null:
					dir.list_dir_end()
					return "写入文件失败：%s" % dst_path
				file.store_buffer(bytes)
				file.close()
		name = dir.get_next()
	dir.list_dir_end()
	return ""

func _restore_active_from_backup(backup_dir: String) -> void:
	if not DirAccess.dir_exists_absolute(backup_dir):
		return
	_remove_dir_recursive_absolute(ACTIVE_DIR)
	_copy_dir_recursive_absolute(backup_dir, ACTIVE_DIR)

func _sha256_text(text: String) -> String:
	var ctx := HashingContext.new()
	var err := ctx.start(HashingContext.HASH_SHA256)
	if err != OK:
		return ""
	ctx.update(text.to_utf8_buffer())
	var digest := ctx.finish()
	return digest.hex_encode().to_lower()

func _remove_dir_recursive_absolute(path: String) -> void:
	if path.strip_edges().is_empty():
		return
	if not DirAccess.dir_exists_absolute(path):
		return
	var dir := DirAccess.open(path)
	if dir == null:
		return
	dir.list_dir_begin()
	var name := dir.get_next()
	while name != "":
		if name != "." and name != "..":
			var child := "%s/%s" % [path, name]
			if dir.current_is_dir():
				_remove_dir_recursive_absolute(child)
			else:
				DirAccess.remove_absolute(child)
		name = dir.get_next()
	dir.list_dir_end()
	DirAccess.remove_absolute(path)

func _check_probability(dict: Dictionary, key: String) -> bool:
	if not dict.has(key):
		return false
	var v := float(dict.get(key, -1.0))
	return v >= 0.0 and v <= 1.0

func _call_done(cb: Callable, ok: bool, msg: String) -> void:
	if cb.is_valid():
		cb.call(ok, msg)

func _call_manifest_done(cb: Callable, ok: bool, manifest_info: Dictionary, msg: String) -> void:
	if cb.is_valid():
		cb.call(ok, manifest_info, msg)

func _call_file_done(cb: Callable, ok: bool, key: String, text: String, meta: Dictionary, msg: String) -> void:
	if cb.is_valid():
		cb.call(ok, key, text, meta, msg)

func _call_done_results(cb: Callable, results: Array[Dictionary]) -> void:
	if cb.is_valid():
		cb.call(results)

func _append_download_result(results: Array[Dictionary], key: String, ok: bool, msg: String) -> void:
	results.append({
		"key": key,
		"ok": ok,
		"msg": msg,
	})

func _is_debug_logging_enabled() -> bool:
	return OS.is_debug_build()

func _debug_log(message: String) -> void:
	if not _is_debug_logging_enabled():
		return
	print("[RemoteConfig] %s" % message)

func _validate_optional_meta(root: Dictionary, expected_key: String) -> Dictionary:
	if not root.has("meta"):
		return {"ok": true}

	var meta_any: Variant = root.get("meta", null)
	if not (meta_any is Dictionary):
		return {"ok": false, "reason": "meta 必须是对象"}
	var meta: Dictionary = meta_any
	var schema_version := int(meta.get("schema_version", 1))
	if schema_version < 1:
		return {"ok": false, "reason": "meta.schema_version 必须 >= 1"}
	if meta.has("exported_at") and str(meta.get("exported_at", "")).strip_edges().is_empty():
		return {"ok": false, "reason": "meta.exported_at 不能为空"}

	var key := str(meta.get("key", "")).strip_edges()
	if not key.is_empty() and key != expected_key:
		return {"ok": false, "reason": "meta.key 不匹配，期望 %s 实际 %s" % [expected_key, key]}

	return {"ok": true}

func validate_sect_tasks_json(text: String) -> Dictionary:
	if text.strip_edges().is_empty():
		return {"ok": false, "reason": "sect_tasks 内容为空"}
	var parsed_any: Variant = JSON.parse_string(text)
	if not (parsed_any is Dictionary):
		return {"ok": false, "reason": "sect_tasks 根节点必须是对象"}
	var root: Dictionary = parsed_any
	var meta_check := _validate_optional_meta(root, "sect_tasks")
	if not bool(meta_check.get("ok", false)):
		return meta_check
	var rules_any = root.get("sect_tasks", {})
	if not (rules_any is Dictionary):
		return {"ok": false, "reason": "缺少 sect_tasks 对象"}
	var rules: Dictionary = rules_any
	for key in ["daily_tasks", "milestone_tasks"]:
		var rows_any = rules.get(key, [])
		if not (rows_any is Array):
			return {"ok": false, "reason": "sect_tasks.%s 必须是数组" % key}
		for i in range((rows_any as Array).size()):
			var row_any = (rows_any as Array)[i]
			if not (row_any is Dictionary):
				return {"ok": false, "reason": "sect_tasks.%s[%d] 必须是对象" % [key, i]}
			var row: Dictionary = row_any
			if str(row.get("task_id", "")).strip_edges().is_empty():
				return {"ok": false, "reason": "sect_tasks.%s[%d].task_id 不能为空" % [key, i]}
			if str(row.get("name", "")).strip_edges().is_empty():
				return {"ok": false, "reason": "sect_tasks.%s[%d].name 不能为空" % [key, i]}
			if str(row.get("goal_type", "")).strip_edges().is_empty():
				return {"ok": false, "reason": "sect_tasks.%s[%d].goal_type 不能为空" % [key, i]}
			if int(row.get("target", 0)) < 1:
				return {"ok": false, "reason": "sect_tasks.%s[%d].target 必须 >= 1" % [key, i]}
			var rewards_check := _validate_task_reward_payload(row.get("rewards", {}), "sect_tasks.%s[%d].rewards" % [key, i])
			if not bool(rewards_check.get("ok", false)):
				return rewards_check
	return {"ok": true}

func validate_mountain_god_json(text: String) -> Dictionary:
	if text.strip_edges().is_empty():
		return {"ok": false, "reason": "mountain_god 内容为空"}
	var parsed_any: Variant = JSON.parse_string(text)
	if not (parsed_any is Dictionary):
		return {"ok": false, "reason": "mountain_god 根节点必须是对象"}
	var root: Dictionary = parsed_any
	var meta_check := _validate_optional_meta(root, "mountain_god")
	if not bool(meta_check.get("ok", false)):
		return meta_check
	var rules_any = root.get("mountain_god", {})
	if not (rules_any is Dictionary):
		return {"ok": false, "reason": "缺少 mountain_god 对象"}
	var rules: Dictionary = rules_any
	if str(rules.get("god_id", "")).strip_edges().is_empty():
		return {"ok": false, "reason": "mountain_god.god_id 不能为空"}
	if str(rules.get("name", "")).strip_edges().is_empty():
		return {"ok": false, "reason": "mountain_god.name 不能为空"}
	if str(rules.get("unlock_stage_id", "")).strip_edges().is_empty():
		return {"ok": false, "reason": "mountain_god.unlock_stage_id 不能为空"}
	var offerings_any = rules.get("offerings", [])
	if not (offerings_any is Array):
		return {"ok": false, "reason": "mountain_god.offerings 必须是数组"}
	if (offerings_any as Array).is_empty():
		return {"ok": false, "reason": "mountain_god.offerings 不能为空"}
	for i in range((offerings_any as Array).size()):
		var row_any = (offerings_any as Array)[i]
		if not (row_any is Dictionary):
			return {"ok": false, "reason": "mountain_god.offerings[%d] 必须是对象" % i}
		var row: Dictionary = row_any
		if str(row.get("offering_id", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "mountain_god.offerings[%d].offering_id 不能为空" % i}
		if str(row.get("name", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "mountain_god.offerings[%d].name 不能为空" % i}
		if str(row.get("offering_item_id", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "mountain_god.offerings[%d].offering_item_id 不能为空" % i}
		if int(row.get("daily_limit", 0)) < 1:
			return {"ok": false, "reason": "mountain_god.offerings[%d].daily_limit 必须 >= 1" % i}
		var exchange_any = row.get("exchange_cost", {})
		if not (exchange_any is Dictionary):
			return {"ok": false, "reason": "mountain_god.offerings[%d].exchange_cost 必须是对象" % i}
		var exchange: Dictionary = exchange_any
		if int(exchange.get("gold", 0)) < 0 or int(exchange.get("sect_contribution", 0)) < 0:
			return {"ok": false, "reason": "mountain_god.offerings[%d].exchange_cost 不能为负数" % i}
		var rewards_check := _validate_task_reward_payload(row.get("rewards", {}), "mountain_god.offerings[%d].rewards" % i, true)
		if not bool(rewards_check.get("ok", false)):
			return rewards_check
	return {"ok": true}

func validate_shop_goods_json(text: String) -> Dictionary:
	if text.strip_edges().is_empty():
		return {"ok": false, "reason": "shop_goods 内容为空"}
	var parsed_any: Variant = JSON.parse_string(text)
	if not (parsed_any is Dictionary):
		return {"ok": false, "reason": "shop_goods 根节点必须是对象"}
	var root: Dictionary = parsed_any
	var meta_check := _validate_optional_meta(root, "shop_goods")
	if not bool(meta_check.get("ok", false)):
		return meta_check
	var rows_any = root.get("shop_goods", [])
	if not (rows_any is Array):
		return {"ok": false, "reason": "shop_goods 必须是数组"}
	var seen_goods: Dictionary = {}
	for i in range((rows_any as Array).size()):
		var row_any = (rows_any as Array)[i]
		if not (row_any is Dictionary):
			return {"ok": false, "reason": "shop_goods[%d] 必须是对象" % i}
		var row: Dictionary = row_any
		var goods_id := str(row.get("goods_id", "")).strip_edges()
		if goods_id.is_empty():
			return {"ok": false, "reason": "shop_goods[%d].goods_id 不能为空" % i}
		if seen_goods.has(goods_id):
			return {"ok": false, "reason": "shop_goods.goods_id 重复：%s" % goods_id}
		seen_goods[goods_id] = true
		var shop_type := str(row.get("shop_type", "")).strip_edges()
		if shop_type != "gold" and shop_type != "crystal" and shop_type != "contribution":
			return {"ok": false, "reason": "shop_goods[%d].shop_type 非法" % i}
		if str(row.get("title", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "shop_goods[%d].title 不能为空" % i}
		if str(row.get("reward_item_id", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "shop_goods[%d].reward_item_id 不能为空" % i}
		if int(row.get("reward_count", 0)) < 1:
			return {"ok": false, "reason": "shop_goods[%d].reward_count 必须 >= 1" % i}
		var currency_type := str(row.get("cost_currency_type", "")).strip_edges()
		if currency_type != "gold" and currency_type != "crystal" and currency_type != "contribution":
			return {"ok": false, "reason": "shop_goods[%d].cost_currency_type 非法" % i}
		if int(row.get("cost_amount", -1)) < 0:
			return {"ok": false, "reason": "shop_goods[%d].cost_amount 不能为负数" % i}
		if int(row.get("unlock_level", 0)) < 1:
			return {"ok": false, "reason": "shop_goods[%d].unlock_level 必须 >= 1" % i}
		if int(row.get("sort_order", -1)) < 0:
			return {"ok": false, "reason": "shop_goods[%d].sort_order 不能为负数" % i}
		for limit_key in ["daily_limit", "weekly_limit", "lifetime_limit"]:
			var limit_any: Variant = row.get(limit_key, null)
			if limit_any == null:
				continue
			if int(limit_any) < 1:
				return {"ok": false, "reason": "shop_goods[%d].%s 必须 >= 1" % [i, limit_key]}
	return {"ok": true}

func _validate_task_reward_payload(rewards_any: Variant, path: String, allow_spirit_stone: bool = false) -> Dictionary:
	if not (rewards_any is Dictionary):
		return {"ok": false, "reason": "%s 必须是对象" % path}
	var rewards: Dictionary = rewards_any
	for numeric_key in ["gold", "sect_contribution", "skill_points"]:
		if int(rewards.get(numeric_key, 0)) < 0:
			return {"ok": false, "reason": "%s.%s 不能为负数" % [path, numeric_key]}
	if allow_spirit_stone and int(rewards.get("spirit_stone", 0)) < 0:
		return {"ok": false, "reason": "%s.spirit_stone 不能为负数" % path}
	var items_any = rewards.get("items", [])
	if not (items_any is Array):
		return {"ok": false, "reason": "%s.items 必须是数组" % path}
	for i in range((items_any as Array).size()):
		var item_any = (items_any as Array)[i]
		if not (item_any is Dictionary):
			return {"ok": false, "reason": "%s.items[%d] 必须是对象" % [path, i]}
		var item: Dictionary = item_any
		if str(item.get("item_id", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "%s.items[%d].item_id 不能为空" % [path, i]}
		if int(item.get("count", 0)) < 1:
			return {"ok": false, "reason": "%s.items[%d].count 必须 >= 1" % [path, i]}
	return {"ok": true}
