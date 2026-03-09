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
var _stage_order: Dictionary = {} # stage_id -> int
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
	_stage_order.clear()
	_indexes_ready = false

	var cfg: Dictionary = ConfigService.get_cfg()
	_collect_equip_templates_by_rarity(cfg)
	var equip_drop_cfg := _resolve_equip_drop_cfg(cfg)

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

		var diffs := _resolve_difficulties(stage)
		for diff_idx in range(diffs.size()):
			var diff_any = diffs[diff_idx]
			if not (diff_any is Dictionary):
				continue
			var diff: Dictionary = diff_any
			var diff_name := _resolve_difficulty_name(diff, diff_idx)
			var monsters_cfg := _resolve_monsters_for_difficulty(stage, diff)
			_collect_stage_monster_sources(stage_id, stage_name, diff_idx, diff_name, monsters_cfg)
			_append_item_pool_sources(stage_id, stage_name, diff_idx, diff_name, stage, diff)
			_append_equip_template_sources(stage_id, stage_name, diff_idx, diff_name, equip_drop_cfg)

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

func _resolve_monsters_for_difficulty(stage_cfg: Dictionary, difficulty_cfg: Dictionary) -> Dictionary:
	for key in ["monsters", "monsters_patch"]:
		var diff_monsters_any = difficulty_cfg.get(key, {})
		if diff_monsters_any is Dictionary:
			return (diff_monsters_any as Dictionary).duplicate(true)
	var stage_monsters_any = stage_cfg.get("monsters", {})
	if stage_monsters_any is Dictionary:
		return (stage_monsters_any as Dictionary).duplicate(true)
	return {}

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
			}
			_add_monster_source(monster_id, source)

func _extract_monster_ids(monsters_cfg: Dictionary, role: String) -> Array[String]:
	var out: Array[String] = []
	var seen: Dictionary = {}

	var pool_key := "%s_pool" % role
	var pool_any = monsters_cfg.get(pool_key, null)
	if pool_any is Array:
		for row_any in (pool_any as Array):
			if not (row_any is Dictionary):
				continue
			var row: Dictionary = row_any
			var monster_id := str(row.get("id", "")).strip_edges()
			if monster_id.is_empty() or seen.has(monster_id):
				continue
			seen[monster_id] = true
			out.append(monster_id)
		if not out.is_empty():
			return out

	var legacy_id := str(monsters_cfg.get(role, "")).strip_edges()
	if not legacy_id.is_empty() and not seen.has(legacy_id):
		out.append(legacy_id)
	return out

func _append_item_pool_sources(stage_id: String, stage_name: String, diff_index: int, diff_name: String, stage_cfg: Dictionary, diff_cfg: Dictionary) -> void:
	var final_drops := BattleService.get_final_drops_for_stage_difficulty(stage_cfg, diff_cfg)
	var pools_any = final_drops.get("items_by_rarity", {})
	if not (pools_any is Dictionary):
		return
	var pools: Dictionary = pools_any
	var rarities := _collect_rarity_keys(pools)
	for rarity in rarities:
		var pool := _as_clean_string_array(pools.get(rarity, []))
		if pool.is_empty():
			continue
		for item_id in pool:
			var source := {
				"stage_id": stage_id,
				"stage_name": stage_name,
				"difficulty_index": diff_index,
				"difficulty_name": diff_name,
				"rarity": rarity,
				"source_type": "items_by_rarity",
			}
			_add_item_source(item_id, source)

func _collect_equip_templates_by_rarity(cfg: Dictionary) -> void:
	var equip_db_any = cfg.get("equip_db", {})
	if not (equip_db_any is Dictionary):
		return
	var templates_any = (equip_db_any as Dictionary).get("equip_templates", [])
	if not (templates_any is Array):
		return
	for tpl_any in templates_any:
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

func _resolve_equip_drop_cfg(cfg: Dictionary) -> Dictionary:
	var out := {
		"chance": 0.0,
		"weights": {},
		"pool": {},
	}
	var battle_any = cfg.get("battle", {})
	if not (battle_any is Dictionary):
		return out
	var drops_any = (battle_any as Dictionary).get("drops", {})
	if not (drops_any is Dictionary):
		return out
	var drops: Dictionary = drops_any
	out["chance"] = clampf(float(drops.get("equip_chance", 0.0)), 0.0, 1.0)

	var weights: Dictionary = {}
	var weights_any = drops.get("equip_weights", {})
	if weights_any is Dictionary:
		var raw_weights: Dictionary = weights_any
		for key_any in raw_weights.keys():
			var rarity := str(key_any).strip_edges().to_lower()
			if rarity.is_empty():
				continue
			weights[rarity] = maxi(0, int(raw_weights.get(key_any, 0)))
	out["weights"] = weights

	var pool: Dictionary = {}
	var pool_any = drops.get("equip_by_rarity", {})
	if pool_any is Dictionary:
		var raw_pool: Dictionary = pool_any
		for key_any in raw_pool.keys():
			var rarity := str(key_any).strip_edges().to_lower()
			if rarity.is_empty():
				continue
			pool[rarity] = _as_clean_string_array(raw_pool.get(key_any, []))
	out["pool"] = pool
	return out

func _append_equip_template_sources(stage_id: String, stage_name: String, diff_index: int, diff_name: String, equip_drop_cfg: Dictionary) -> void:
	var chance := clampf(float(equip_drop_cfg.get("chance", 0.0)), 0.0, 1.0)
	if chance <= 0.0:
		return
	var weights_any = equip_drop_cfg.get("weights", {})
	if not (weights_any is Dictionary):
		return
	var weights: Dictionary = weights_any
	var pool_any = equip_drop_cfg.get("pool", {})
	var pool: Dictionary = pool_any if pool_any is Dictionary else {}

	var rarity_keys := _collect_rarity_keys(weights, pool)
	for rarity in rarity_keys:
		var weight := maxi(0, int(weights.get(rarity, 0)))
		if weight <= 0:
			continue

		var source_base := {
			"stage_id": stage_id,
			"stage_name": stage_name,
			"difficulty_index": diff_index,
			"difficulty_name": diff_name,
			"rarity": rarity,
		}

		var explicit_pool := _as_clean_string_array(pool.get(rarity, []))
		if not explicit_pool.is_empty():
			for tpl_id in explicit_pool:
				var source := source_base.duplicate(true)
				source["source_type"] = "template_pool"
				source["approx"] = false
				_add_equip_template_source(tpl_id, source)
			continue

		# 当前掉落链路没有该 rarity 的 template 级池时，退化为“同稀有度推荐来源”。
		# 这是近似来源，不表示精确模板必掉。
		var rarity_tpl_any = _equip_templates_by_rarity.get(rarity, [])
		if not (rarity_tpl_any is Array):
			continue
		for tpl_any in (rarity_tpl_any as Array):
			var tpl_id := str(tpl_any).strip_edges()
			if tpl_id.is_empty():
				continue
			var source := source_base.duplicate(true)
			source["source_type"] = "equip_drop"
			source["approx"] = true
			_add_equip_template_source(tpl_id, source)

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
		str(source.get("source_type", "equip_drop")),
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
	if stage_name.is_empty():
		stage_name = str(source.get("stage_id", "未知地图"))
	if diff_name.is_empty():
		diff_name = DEFAULT_DIFF_NAME
	return "%s（%s）- %s掉落池" % [stage_name, diff_name, _rarity_name(rarity)]

func _format_equip_source_line(source: Dictionary) -> String:
	var stage_name := str(source.get("stage_name", source.get("stage_id", ""))).strip_edges()
	var diff_name := str(source.get("difficulty_name", DEFAULT_DIFF_NAME)).strip_edges()
	var rarity := str(source.get("rarity", "white")).strip_edges().to_lower()
	if stage_name.is_empty():
		stage_name = str(source.get("stage_id", "未知地图"))
	if diff_name.is_empty():
		diff_name = DEFAULT_DIFF_NAME
	var source_type := str(source.get("source_type", "equip_drop"))
	if source_type == "template_pool" and not bool(source.get("approx", false)):
		return "%s（%s）- 可掉落该装备（%s）" % [stage_name, diff_name, _rarity_name(rarity)]
	return "%s（%s）- 推荐刷%s装来源" % [stage_name, diff_name, _rarity_equip_name(rarity)]

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

func _ensure_indexes() -> void:
	if _indexes_ready:
		return
	rebuild_indexes()
