extends Node

const DEFAULT_DIFF_NAME := "普通"
const DEFAULT_DROP_RARITIES := ["white", "blue", "gold", "purple", "orange"]

var _monster_sources: Dictionary = {} # monster_id -> Array[Dictionary]
var _monster_source_keys: Dictionary = {} # monster_id -> Dictionary
var _item_sources: Dictionary = {} # item_id -> Array[Dictionary]
var _item_source_keys: Dictionary = {} # item_id -> Dictionary
var _equip_template_sources: Dictionary = {} # template_id -> Array[Dictionary]
var _equip_template_source_keys: Dictionary = {} # template_id -> Dictionary
var _equip_templates_by_rarity: Dictionary = {} # rarity -> Array[String]
var _blueprints_by_theme: Dictionary = {} # theme_key -> Array[String]
var _item_rarity_by_id: Dictionary = {} # item_id -> rarity
var _stage_order: Dictionary = {} # stage_id -> int
var _stage_unlock_level: Dictionary = {} # stage_id -> unlock_min_level
var _indexes_ready := false

func _ready() -> void:
	rebuild_indexes()

func rebuild_indexes() -> void:
	_monster_sources.clear()
	_monster_source_keys.clear()
	_item_sources.clear()
	_item_source_keys.clear()
	_equip_template_sources.clear()
	_equip_template_source_keys.clear()
	_equip_templates_by_rarity.clear()
	_blueprints_by_theme.clear()
	_item_rarity_by_id.clear()
	_stage_order.clear()
	_stage_unlock_level.clear()
	_indexes_ready = false

	var cfg: Dictionary = ConfigService.get_cfg()
	_collect_item_rarity_map(cfg)
	_collect_equip_templates_by_rarity(cfg)
	_collect_blueprints_by_theme_from_items(cfg)
	_append_material_dungeon_item_sources(cfg)
	var monster_drop_map := _build_monster_drop_map(cfg)

	var stages_db_any = cfg.get("stages_db", {})
	if not (stages_db_any is Dictionary):
		_indexes_ready = true
		return
	var stages_any = (stages_db_any as Dictionary).get("stages", [])
	if not (stages_any is Array):
		_indexes_ready = true
		return
	var stages: Array = stages_any

	for stage_idx in range(stages.size()):
		var stage_any = stages[stage_idx]
		if not (stage_any is Dictionary):
			continue
		var stage: Dictionary = stage_any
		var stage_id := str(stage.get("id", "")).strip_edges()
		if stage_id.is_empty():
			continue
		var stage_name := _resolve_stage_name(stage)
		_stage_order[stage_id] = stage_idx
		_stage_unlock_level[stage_id] = maxi(1, int(stage.get("unlock_min_level", 1)))

		var diffs := _resolve_difficulties(stage)
		for diff_idx in range(diffs.size()):
			var diff_any = diffs[diff_idx]
			if not (diff_any is Dictionary):
				continue
			var diff: Dictionary = diff_any
			var diff_name := _resolve_difficulty_name(diff, diff_idx)
			var monsters_cfg := _resolve_monsters_for_difficulty(diff)
			_collect_stage_monster_sources(stage_id, stage_name, diff_idx, diff_name, monsters_cfg)
			_append_monster_drop_sources(stage_id, stage_name, diff_idx, diff_name, monsters_cfg, monster_drop_map)

	_sort_all_indexes()
	_indexes_ready = true

func get_monster_spawn_sources(monster_id: String) -> Array[Dictionary]:
	_ensure_indexes()
	var id := monster_id.strip_edges()
	if id.is_empty():
		return []
	return _copy_source_array(_monster_sources.get(id, []))

func get_monster_spawn_lines(monster_id: String, max_count: int = 8) -> Array[String]:
	var sources := get_monster_spawn_sources(monster_id)
	if sources.is_empty():
		return []
	var cap := maxi(0, max_count)
	if cap <= 0:
		return []
	var out: Array[String] = []
	var show_count := mini(cap, sources.size())
	for i in range(show_count):
		var src: Dictionary = sources[i]
		out.append(_format_monster_source_line(src))
	if sources.size() > show_count:
		out.append("...（共%d处）" % sources.size())
	return out

func get_item_drop_sources(item_id: String) -> Array[Dictionary]:
	_ensure_indexes()
	var id := item_id.strip_edges()
	if id.is_empty():
		return []
	return _copy_source_array(_item_sources.get(id, []))

func get_item_drop_lines(item_id: String, max_count: int = 8) -> Array[String]:
	var sources := get_item_drop_sources(item_id)
	return _build_lines_from_sources(sources, max_count, Callable(self, "_format_item_source_line"))

func get_equip_template_sources(template_id: String) -> Array[Dictionary]:
	_ensure_indexes()
	var id := template_id.strip_edges()
	if id.is_empty():
		return []
	return _copy_source_array(_equip_template_sources.get(id, []))

func get_equip_template_lines(template_id: String, max_count: int = 8) -> Array[String]:
	var sources := get_equip_template_sources(template_id)
	return _build_lines_from_sources(sources, max_count, Callable(self, "_format_equip_source_line"))

func get_equip_rarity_lines(rarity: String, max_count: int = 8) -> Array[String]:
	_ensure_indexes()
	var r := rarity.strip_edges().to_lower()
	if r.is_empty():
		return []
	var rows: Array[Dictionary] = []
	var seen: Dictionary = {}
	for tpl_any in _equip_template_sources.keys():
		var tpl_id := str(tpl_any)
		if seen.has(tpl_id):
			continue
		var src_any = _equip_template_sources.get(tpl_id, [])
		if not (src_any is Array):
			continue
		for row_any in src_any:
			if not (row_any is Dictionary):
				continue
			var row: Dictionary = row_any
			if str(row.get("rarity", "")).to_lower() != r:
				continue
			rows.append(row.duplicate(true))
			seen[tpl_id] = true
			break
	return _build_lines_from_sources(rows, max_count, Callable(self, "_format_equip_source_line"))

func get_monster_farm_targets(monster_id: String, max_count: int = 3) -> Array[Dictionary]:
	var sources := get_monster_spawn_sources(monster_id)
	return _build_farm_targets(sources, max_count, Callable(self, "_format_monster_source_line"))

func get_item_farm_targets(item_id: String, max_count: int = 3) -> Array[Dictionary]:
	var sources := get_item_drop_sources(item_id)
	return _build_farm_targets(sources, max_count, Callable(self, "_format_item_source_line"))

func get_equip_farm_targets(template_id: String, max_count: int = 3) -> Array[Dictionary]:
	var sources := get_equip_template_sources(template_id)
	return _build_farm_targets(sources, max_count, Callable(self, "_format_equip_source_line"))

func _resolve_stage_name(stage_cfg: Dictionary) -> String:
	var stage_name := str(stage_cfg.get("name", stage_cfg.get("id", ""))).strip_edges()
	return stage_name if not stage_name.is_empty() else str(stage_cfg.get("id", ""))

func _resolve_difficulties(stage_cfg: Dictionary) -> Array:
	var out: Array = []
	var diffs_any = stage_cfg.get("difficulties", [])
	if diffs_any is Array:
		for diff_any in diffs_any:
			if diff_any is Dictionary:
				out.append((diff_any as Dictionary).duplicate(true))
	if out.is_empty():
		out.append({"name": DEFAULT_DIFF_NAME})
	return out

func _resolve_difficulty_name(diff_cfg: Dictionary, diff_index: int) -> String:
	var name := str(diff_cfg.get("name", "")).strip_edges()
	if not name.is_empty():
		return name
	if diff_index <= 0:
		return DEFAULT_DIFF_NAME
	return "难度%d" % diff_index

func _resolve_monsters_for_difficulty(difficulty_cfg: Dictionary) -> Dictionary:
	return {
		"normal_monsters": difficulty_cfg.get("normal_monsters", []),
		"elite_monsters": difficulty_cfg.get("elite_monsters", []),
		"boss_monsters": difficulty_cfg.get("boss_monsters", []),
	}

func _collect_stage_monster_sources(stage_id: String, stage_name: String, diff_index: int, diff_name: String, monsters_cfg: Dictionary) -> void:
	for role in ["normal", "elite", "boss"]:
		var ids := _extract_monster_ids(monsters_cfg, role)
		for monster_id in ids:
			var source := {
				"stage_id": stage_id,
				"stage_name": stage_name,
				"difficulty_index": diff_index,
				"difficulty_name": diff_name,
				"role": role,
				"role_name": _role_name(role),
				"sort_stage_index": _stage_index(stage_id),
			}
			_add_monster_source(monster_id, source)

func _extract_monster_ids(monsters_cfg: Dictionary, role: String) -> Array[String]:
	var out: Array[String] = []
	var seen: Dictionary = {}

	var pool_key := "%s_monsters" % role
	var pool_any = monsters_cfg.get(pool_key, null)
	if pool_any is Array:
		for row_any in (pool_any as Array):
			if not (row_any is Dictionary):
				continue
			var row: Dictionary = row_any
			var monster_id := str(row.get("monster_id", row.get("id", ""))).strip_edges()
			if monster_id.is_empty() or seen.has(monster_id):
				continue
			seen[monster_id] = true
			out.append(monster_id)
		if not out.is_empty():
			return out

	return out

func _build_monster_drop_map(cfg: Dictionary) -> Dictionary:
	var out := {}
	var db_any = cfg.get("monsters_db", {})
	if not (db_any is Dictionary):
		return out
	var rows_any = (db_any as Dictionary).get("monsters", [])
	if not (rows_any is Array):
		return out
	for row_any in rows_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		var monster_id := str(row.get("id", "")).strip_edges()
		if monster_id.is_empty():
			continue
		out[monster_id] = row.get("drops", [])
	return out

func _append_monster_drop_sources(stage_id: String, stage_name: String, diff_index: int, diff_name: String, monsters_cfg: Dictionary, monster_drop_map: Dictionary) -> void:
	for role in ["normal", "elite", "boss"]:
		var monster_ids := _extract_monster_ids(monsters_cfg, role)
		for monster_id in monster_ids:
			var drops_any = monster_drop_map.get(monster_id, [])
			if not (drops_any is Array):
				continue
			for drop_any in drops_any:
				if not (drop_any is Dictionary):
					continue
				var drop: Dictionary = drop_any
				if not bool(drop.get("is_enabled", true)):
					continue
				var item_id := str(drop.get("item_id", "")).strip_edges()
				if item_id.is_empty():
					continue
				_add_item_source(item_id, {
					"stage_id": stage_id,
					"stage_name": stage_name,
					"difficulty_index": diff_index,
					"difficulty_name": diff_name,
					"role": role,
					"role_name": _role_name(role),
					"monster_id": monster_id,
					"monster_name": _monster_name(monster_id),
					"rarity": _item_rarity(item_id, "white"),
					"source_type": "monster_drop",
					"sort_stage_index": _stage_index(stage_id),
				})

func _append_material_dungeon_item_sources(cfg: Dictionary) -> void:
	var db_any = cfg.get("material_dungeons_db", {})
	if not (db_any is Dictionary):
		return
	var db: Dictionary = db_any
	var rows_any = db.get("material_dungeons", [])
	if not (rows_any is Array):
		return
	var group_map := {}
	var group_rows_any = db.get("material_dungeon_drop_groups", [])
	if group_rows_any is Array:
		for group_any in group_rows_any:
			if not (group_any is Dictionary):
				continue
			var group: Dictionary = group_any
			var group_id := str(group.get("group_id", "")).strip_edges()
			if group_id.is_empty():
				continue
			group_map[group_id] = group
	for row_any in rows_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		var dungeon_id := str(row.get("dungeon_id", row.get("id", ""))).strip_edges()
		if dungeon_id.is_empty():
			continue
		var dungeon_name := str(row.get("name", dungeon_id)).strip_edges()
		var unlock_level := maxi(0, int(row.get("unlock_level", 0)))
		var dungeon_type := str(row.get("dungeon_type", "")).strip_edges().to_lower()
		var pool_ids := []
		var layer_rules_any = row.get("layer_rules", [])
		if layer_rules_any is Array:
			for rule_any in layer_rules_any:
				if not (rule_any is Dictionary):
					continue
				var rule: Dictionary = rule_any
				for group_key in ["drop_group_id", "first_clear_reward_group_id"]:
					var group_id := str(rule.get(group_key, "")).strip_edges()
					if group_id.is_empty() or not group_map.has(group_id):
						continue
					var rewards_any = (group_map.get(group_id, {}) as Dictionary).get("rewards", [])
					if not (rewards_any is Array):
						continue
					for reward_any in rewards_any:
						if not (reward_any is Dictionary):
							continue
						var item_id := str((reward_any as Dictionary).get("item_id", "")).strip_edges()
						if item_id.is_empty():
							continue
						pool_ids.append(item_id)
		if pool_ids.is_empty():
			pool_ids = _as_clean_string_array(row.get("display_rewards", []))
		pool_ids = _as_clean_string_array(pool_ids)
		for item_id in pool_ids:
			_add_item_source(item_id, {
				"stage_id": "dungeon:%s" % dungeon_id,
				"stage_name": dungeon_name,
				"difficulty_index": 0,
				"difficulty_name": "材料副本",
				"rarity": _item_rarity(item_id, "white"),
				"source_type": "material_dungeon",
				"dungeon_id": dungeon_id,
				"dungeon_type": dungeon_type,
				"unlock_level": unlock_level,
				"sort_stage_index": 900000 + unlock_level,
			})

func _collect_item_rarity_map(cfg: Dictionary) -> void:
	_item_rarity_by_id.clear()
	var items_db_any = cfg.get("items_db", {})
	if not (items_db_any is Dictionary):
		return
	var rows_any = (items_db_any as Dictionary).get("items", [])
	if not (rows_any is Array):
		return
	for row_any in rows_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		var item_id := str(row.get("id", "")).strip_edges()
		if item_id.is_empty():
			continue
		var rarity := str(row.get("rarity", "white")).strip_edges().to_lower()
		if rarity.is_empty():
			rarity = "white"
		_item_rarity_by_id[item_id] = rarity

func _collect_equip_templates_by_rarity(cfg: Dictionary) -> void:
	for tpl_any in EquipmentModel.get_template_catalog_rows():
		if not (tpl_any is Dictionary):
			continue
		var tpl: Dictionary = tpl_any
		var tpl_id := str(tpl.get("id", "")).strip_edges()
		if tpl_id.is_empty():
			continue
		var rarity := str(tpl.get("rarity", "white")).strip_edges().to_lower()
		if rarity.is_empty():
			rarity = "white"
		var list_any = _equip_templates_by_rarity.get(rarity, [])
		var list: Array = list_any if list_any is Array else []
		if list.find(tpl_id) == -1:
			list.append(tpl_id)
		_equip_templates_by_rarity[rarity] = list

		var theme_key := str(tpl.get("theme_key", "")).strip_edges().to_lower()
		var blueprint_id := str(tpl.get("blueprint_item_id", "")).strip_edges()
		if not theme_key.is_empty() and not blueprint_id.is_empty():
			var bp_any = _blueprints_by_theme.get(theme_key, [])
			var bp_rows: Array = bp_any if bp_any is Array else []
			if bp_rows.find(blueprint_id) == -1:
				bp_rows.append(blueprint_id)
			_blueprints_by_theme[theme_key] = bp_rows

func _collect_blueprints_by_theme_from_items(cfg: Dictionary) -> void:
	var items_db_any = cfg.get("items_db", {})
	if not (items_db_any is Dictionary):
		return
	var rows_any = (items_db_any as Dictionary).get("items", [])
	if not (rows_any is Array):
		return
	for row_any in (rows_any as Array):
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		var item_id := str(row.get("id", "")).strip_edges()
		if item_id.is_empty():
			continue
		var sub_type := str(row.get("sub_type", "")).strip_edges().to_lower()
		var is_blueprint := sub_type == "equipment_blueprint" or item_id.begins_with("bp_")
		if not is_blueprint:
			continue
		for theme_key in ["nanshan", "qingqiu", "kunlun"]:
			if item_id.find(theme_key) == -1:
				continue
			var bp_any = _blueprints_by_theme.get(theme_key, [])
			var bp_rows: Array = bp_any if bp_any is Array else []
			if bp_rows.find(item_id) == -1:
				bp_rows.append(item_id)
			_blueprints_by_theme[theme_key] = bp_rows

func _add_monster_source(monster_id: String, source: Dictionary) -> void:
	var id := monster_id.strip_edges()
	if id.is_empty():
		return
	var key := "%s|%d|%s" % [
		str(source.get("stage_id", "")),
		int(source.get("difficulty_index", 0)),
		str(source.get("role", "normal")),
	]
	var key_map_any = _monster_source_keys.get(id, {})
	var key_map: Dictionary = key_map_any if key_map_any is Dictionary else {}
	if key_map.has(key):
		return
	key_map[key] = true
	_monster_source_keys[id] = key_map

	var arr_any = _monster_sources.get(id, [])
	var arr: Array = arr_any if arr_any is Array else []
	arr.append(source.duplicate(true))
	_monster_sources[id] = arr

func _add_item_source(item_id: String, source: Dictionary) -> void:
	var id := item_id.strip_edges()
	if id.is_empty():
		return
	var key := "%s|%d|%s|%s" % [
		str(source.get("stage_id", "")),
		int(source.get("difficulty_index", 0)),
		str(source.get("rarity", "white")),
		str(source.get("source_type", "items_by_rarity")),
	]
	var key_map_any = _item_source_keys.get(id, {})
	var key_map: Dictionary = key_map_any if key_map_any is Dictionary else {}
	if key_map.has(key):
		return
	key_map[key] = true
	_item_source_keys[id] = key_map

	var arr_any = _item_sources.get(id, [])
	var arr: Array = arr_any if arr_any is Array else []
	arr.append(source.duplicate(true))
	_item_sources[id] = arr

func _add_equip_template_source(template_id: String, source: Dictionary) -> void:
	var id := template_id.strip_edges()
	if id.is_empty():
		return
	var key := "%s|%d|%s|%s" % [
		str(source.get("stage_id", "")),
		int(source.get("difficulty_index", 0)),
		str(source.get("rarity", "white")),
		str(source.get("source_type", "")),
	]
	var key_map_any = _equip_template_source_keys.get(id, {})
	var key_map: Dictionary = key_map_any if key_map_any is Dictionary else {}
	if key_map.has(key):
		return
	key_map[key] = true
	_equip_template_source_keys[id] = key_map

	var arr_any = _equip_template_sources.get(id, [])
	var arr: Array = arr_any if arr_any is Array else []
	arr.append(source.duplicate(true))
	_equip_template_sources[id] = arr

func _sort_all_indexes() -> void:
	for monster_id_any in _monster_sources.keys():
		var monster_id := str(monster_id_any)
		var arr_any = _monster_sources.get(monster_id, [])
		if not (arr_any is Array):
			continue
		var arr: Array = arr_any
		arr.sort_custom(_monster_source_sort)
		_monster_sources[monster_id] = arr

	for item_id_any in _item_sources.keys():
		var item_id := str(item_id_any)
		var arr_any = _item_sources.get(item_id, [])
		if not (arr_any is Array):
			continue
		var arr: Array = arr_any
		arr.sort_custom(_item_source_sort)
		_item_sources[item_id] = arr

	for tpl_id_any in _equip_template_sources.keys():
		var tpl_id := str(tpl_id_any)
		var arr_any = _equip_template_sources.get(tpl_id, [])
		if not (arr_any is Array):
			continue
		var arr: Array = arr_any
		arr.sort_custom(_equip_source_sort)
		_equip_template_sources[tpl_id] = arr

func _monster_source_sort(a_any: Variant, b_any: Variant) -> bool:
	if not (a_any is Dictionary) or not (b_any is Dictionary):
		return false
	var a: Dictionary = a_any
	var b: Dictionary = b_any
	var asid := str(a.get("stage_id", ""))
	var bsid := str(b.get("stage_id", ""))
	var a_stage := _stage_index(asid)
	var b_stage := _stage_index(bsid)
	if a_stage != b_stage:
		return a_stage < b_stage
	var a_diff := int(a.get("difficulty_index", 0))
	var b_diff := int(b.get("difficulty_index", 0))
	if a_diff != b_diff:
		return a_diff < b_diff
	var a_role := _role_sort_value(str(a.get("role", "normal")))
	var b_role := _role_sort_value(str(b.get("role", "normal")))
	if a_role != b_role:
		return a_role < b_role
	return str(a.get("stage_name", "")) < str(b.get("stage_name", ""))

func _item_source_sort(a_any: Variant, b_any: Variant) -> bool:
	if not (a_any is Dictionary) or not (b_any is Dictionary):
		return false
	var a: Dictionary = a_any
	var b: Dictionary = b_any
	var asid := str(a.get("stage_id", ""))
	var bsid := str(b.get("stage_id", ""))
	var a_stage := _stage_index(asid)
	var b_stage := _stage_index(bsid)
	if a_stage != b_stage:
		return a_stage < b_stage
	var a_diff := int(a.get("difficulty_index", 0))
	var b_diff := int(b.get("difficulty_index", 0))
	if a_diff != b_diff:
		return a_diff < b_diff
	var a_rarity := _rarity_sort_value(str(a.get("rarity", "white")))
	var b_rarity := _rarity_sort_value(str(b.get("rarity", "white")))
	if a_rarity != b_rarity:
		return a_rarity < b_rarity
	return str(a.get("stage_name", "")) < str(b.get("stage_name", ""))

func _equip_source_sort(a_any: Variant, b_any: Variant) -> bool:
	if not (a_any is Dictionary) or not (b_any is Dictionary):
		return false
	var a: Dictionary = a_any
	var b: Dictionary = b_any
	var asid := str(a.get("stage_id", ""))
	var bsid := str(b.get("stage_id", ""))
	var a_stage := _stage_index(asid)
	var b_stage := _stage_index(bsid)
	if a_stage != b_stage:
		return a_stage < b_stage
	var a_diff := int(a.get("difficulty_index", 0))
	var b_diff := int(b.get("difficulty_index", 0))
	if a_diff != b_diff:
		return a_diff < b_diff
	var a_rarity := _rarity_sort_value(str(a.get("rarity", "white")))
	var b_rarity := _rarity_sort_value(str(b.get("rarity", "white")))
	if a_rarity != b_rarity:
		return a_rarity < b_rarity
	return str(a.get("stage_name", "")) < str(b.get("stage_name", ""))

func _stage_index(stage_id: String) -> int:
	return int(_stage_order.get(stage_id, 999999))

func _role_sort_value(role: String) -> int:
	match role:
		"elite":
			return 1
		"boss":
			return 2
		_:
			return 0

func _rarity_sort_value(rarity: String) -> int:
	match rarity:
		"white":
			return 0
		"blue":
			return 1
		"gold":
			return 2
		"purple":
			return 3
		"orange":
			return 4
		_:
			return 50

func _role_name(role: String) -> String:
	match role:
		"elite":
			return "精英"
		"boss":
			return "Boss"
		_:
			return "普通"

func _format_monster_source_line(source: Dictionary) -> String:
	var stage_name := str(source.get("stage_name", source.get("stage_id", ""))).strip_edges()
	var diff_name := str(source.get("difficulty_name", DEFAULT_DIFF_NAME)).strip_edges()
	var role_name := str(source.get("role_name", _role_name(str(source.get("role", "normal")))))
	if stage_name.is_empty():
		stage_name = str(source.get("stage_id", "未知地图"))
	if diff_name.is_empty():
		diff_name = DEFAULT_DIFF_NAME
	if role_name.is_empty():
		role_name = "普通"
	return "%s（%s）- %s" % [stage_name, diff_name, role_name]

func _format_item_source_line(source: Dictionary) -> String:
	var stage_name := str(source.get("stage_name", source.get("stage_id", ""))).strip_edges()
	var diff_name := str(source.get("difficulty_name", DEFAULT_DIFF_NAME)).strip_edges()
	var rarity := str(source.get("rarity", "white")).strip_edges().to_lower()
	var source_type := str(source.get("source_type", "items_by_rarity")).strip_edges()
	var theme_key := str(source.get("theme_key", "")).strip_edges().to_lower()
	if stage_name.is_empty():
		stage_name = str(source.get("stage_id", "未知地图"))
	if diff_name.is_empty():
		diff_name = DEFAULT_DIFF_NAME
	var prefix := "%s（%s）" % [stage_name, diff_name]
	match source_type:
		"material_dungeon":
			var unlock_level := maxi(0, int(source.get("unlock_level", 0)))
			if unlock_level > 0:
				return "%s - 材料副本（Lv%d解锁）" % [stage_name, unlock_level]
			return "%s - 材料副本" % stage_name
		"monster_drop":
			var role_name := str(source.get("role_name", "怪物")).strip_edges()
			var monster_name := str(source.get("monster_name", "怪物")).strip_edges()
			return "%s - %s[%s]掉落" % [prefix, monster_name, role_name]
	return "%s（%s）- %s来源" % [stage_name, diff_name, _rarity_name(rarity)]

func _format_equip_source_line(source: Dictionary) -> String:
	var stage_name := str(source.get("stage_name", source.get("stage_id", ""))).strip_edges()
	var diff_name := str(source.get("difficulty_name", DEFAULT_DIFF_NAME)).strip_edges()
	var rarity := str(source.get("rarity", "white")).strip_edges().to_lower()
	if stage_name.is_empty():
		stage_name = str(source.get("stage_id", "未知地图"))
	if diff_name.is_empty():
		diff_name = DEFAULT_DIFF_NAME
	return "%s（%s）- %s装来源" % [stage_name, diff_name, _rarity_equip_name(rarity)]

func _rarity_name(rarity: String) -> String:
	match rarity:
		"blue":
			return "蓝色"
		"gold":
			return "金色"
		"purple":
			return "紫色"
		"orange":
			return "橙色"
		_:
			return "白色"

func _rarity_equip_name(rarity: String) -> String:
	match rarity:
		"blue":
			return "蓝"
		"gold":
			return "金"
		"purple":
			return "紫"
		"orange":
			return "橙"
		_:
			return "白"

func _build_lines_from_sources(sources: Array[Dictionary], max_count: int, formatter: Callable) -> Array[String]:
	if sources.is_empty():
		return []
	var cap := maxi(0, max_count)
	if cap <= 0:
		return []
	var out: Array[String] = []
	var seen: Dictionary = {}
	for source in sources:
		if not (source is Dictionary):
			continue
		var line := str(formatter.call(source)).strip_edges()
		if line.is_empty() or seen.has(line):
			continue
		seen[line] = true
		out.append(line)
		if out.size() >= cap:
			break
	return out

func _build_farm_targets(sources: Array[Dictionary], max_count: int, formatter: Callable) -> Array[Dictionary]:
	if sources.is_empty():
		return []
	var cap := maxi(0, max_count)
	if cap <= 0:
		return []

	var unlocked_rows: Array[Dictionary] = []
	var locked_rows: Array[Dictionary] = []
	var seen: Dictionary = {}

	for source in sources:
		if not (source is Dictionary):
			continue
		var src: Dictionary = source
		var stage_id := str(src.get("stage_id", "")).strip_edges()
		var diff_index := maxi(0, int(src.get("difficulty_index", 0)))
		var line := str(formatter.call(src)).strip_edges()
		if stage_id.is_empty() or line.is_empty():
			continue
		var dedup_key := "%s|%d|%s|%s|%s" % [
			stage_id,
			diff_index,
			str(src.get("role", "")),
			str(src.get("rarity", "")),
			str(src.get("source_type", "")),
		]
		if seen.has(dedup_key):
			continue
		seen[dedup_key] = true

		var unlocked := _is_stage_diff_unlocked(stage_id, diff_index)
		var row := src.duplicate(true)
		row["unlocked"] = unlocked
		row["summary_line"] = line
		row["sort_stage_index"] = int(row.get("sort_stage_index", _stage_index(stage_id)))
		if unlocked:
			unlocked_rows.append(row)
		else:
			locked_rows.append(row)

	var out: Array[Dictionary] = []
	for row in unlocked_rows:
		out.append(row)
		if out.size() >= cap:
			return out
	for row in locked_rows:
		out.append(row)
		if out.size() >= cap:
			return out
	return out

func _is_stage_diff_unlocked(stage_id: String, diff_index: int) -> bool:
	var sid := stage_id.strip_edges()
	if sid.is_empty():
		return false
	var need_level := maxi(1, int(_stage_unlock_level.get(sid, 1)))
	if ProgressModel.level < need_level:
		return false
	return diff_index >= 0

func _copy_source_array(raw_any: Variant) -> Array[Dictionary]:
	var out: Array[Dictionary] = []
	if not (raw_any is Array):
		return out
	for row_any in raw_any:
		if not (row_any is Dictionary):
			continue
		out.append((row_any as Dictionary).duplicate(true))
	return out

func _as_clean_string_array(v: Variant) -> Array[String]:
	var out: Array[String] = []
	if not (v is Array):
		return out
	var seen: Dictionary = {}
	for entry_any in (v as Array):
		var s := str(entry_any).strip_edges()
		if s.is_empty() or seen.has(s):
			continue
		seen[s] = true
		out.append(s)
	return out

func _monster_name(monster_id: String) -> String:
	var target := monster_id.strip_edges()
	if target.is_empty():
		return ""
	var cfg: Dictionary = ConfigService.get_cfg()
	var db_any = cfg.get("monsters_db", {})
	if not (db_any is Dictionary):
		return target
	var rows_any = (db_any as Dictionary).get("monsters", [])
	if not (rows_any is Array):
		return target
	for row_any in rows_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		if str(row.get("id", "")).strip_edges() != target:
			continue
		var name := str(row.get("name", target)).strip_edges()
		return name if not name.is_empty() else target
	return target

func _collect_rarity_keys(primary_any: Variant, fallback_any: Variant = null) -> Array[String]:
	var out: Array[String] = []
	var seen: Dictionary = {}
	for rarity in DEFAULT_DROP_RARITIES:
		out.append(rarity)
		seen[rarity] = true
	if fallback_any is Dictionary:
		var fallback: Dictionary = fallback_any
		for key_any in fallback.keys():
			var key := str(key_any).strip_edges().to_lower()
			if key.is_empty() or seen.has(key):
				continue
			seen[key] = true
			out.append(key)
	if primary_any is Dictionary:
		var primary: Dictionary = primary_any
		for key_any in primary.keys():
			var key := str(key_any).strip_edges().to_lower()
			if key.is_empty() or seen.has(key):
				continue
			seen[key] = true
			out.append(key)
	return out

func _resolve_stage_theme_key(stage_cfg: Dictionary) -> String:
	var direct := str(stage_cfg.get("theme_key", "")).strip_edges().to_lower()
	if not direct.is_empty():
		return direct
	var stage_id := str(stage_cfg.get("id", "")).strip_edges().to_lower()
	if stage_id.begins_with("nan"):
		return "nanshan"
	if stage_id.begins_with("qing") or stage_id.begins_with("qiu"):
		return "qingqiu"
	if stage_id.begins_with("kun"):
		return "kunlun"
	var stage_name := str(stage_cfg.get("name", "")).strip_edges()
	if stage_name.find("南山") != -1:
		return "nanshan"
	if stage_name.find("青丘") != -1:
		return "qingqiu"
	if stage_name.find("昆仑") != -1:
		return "kunlun"
	return ""

func _theme_has_core_requirement(theme_key: String) -> bool:
	var cfg: Dictionary = ConfigService.get_cfg()
	var rules_db_any = cfg.get("forge_rules_db", {})
	if not (rules_db_any is Dictionary):
		return false
	var root_any = (rules_db_any as Dictionary).get("forge_rules", {})
	if not (root_any is Dictionary):
		return false
	var rows_any = (root_any as Dictionary).get("high_forge_rules", [])
	if not (rows_any is Array):
		return false
	var expected_core := "boss_core_%s" % theme_key
	for row_any in (rows_any as Array):
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		if str(row.get("theme_key", "")).strip_edges().to_lower() != theme_key:
			continue
		var extra_any = row.get("extra_materials", [])
		if not (extra_any is Array):
			continue
		for mat_any in (extra_any as Array):
			if not (mat_any is Dictionary):
				continue
			var mat: Dictionary = mat_any
			if str(mat.get("item_id", "")).strip_edges() != expected_core:
				continue
			if int(mat.get("count", 0)) > 0:
				return true
	return false

func _item_rarity(item_id: String, fallback: String = "white") -> String:
	var id := item_id.strip_edges()
	if id.is_empty():
		return fallback
	var rarity := str(_item_rarity_by_id.get(id, fallback)).strip_edges().to_lower()
	if rarity.is_empty():
		return fallback
	return rarity

func _theme_name(theme_key: String) -> String:
	match theme_key:
		"nanshan":
			return "南山"
		"qingqiu":
			return "青丘"
		"kunlun":
			return "昆仑"
		_:
			return ""

func _ensure_indexes() -> void:
	if _indexes_ready:
		return
	rebuild_indexes()
