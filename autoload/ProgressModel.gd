extends Node

const SAVE_PATH := "user://progress.json"
const StatsServiceRef := preload("res://services/StatsService.gd")
const DEFAULT_SECT_ID := "sect_bingzong"
const DEFAULT_ATTRS := {
	"strength": 0,
	"physique": 0,
	"agility": 0,
	"spirit": 0,
	"true_energy": 0,
	"fortune": 0,
}

var level: int = 1
var exp: int = 0
var free_attr_points: int = 0
var skill_points: int = 0
var attrs: Dictionary = DEFAULT_ATTRS.duplicate(true)
var current_sect_id: String = DEFAULT_SECT_ID
var skill_levels: Dictionary = {}
var claimed_milestones: Dictionary = {}

func _ready() -> void:
	load_progress()

func exp_to_next(lv: int) -> int:
	var safe_lv := maxi(1, lv)
	if safe_lv >= _level_cap():
		return 0
	var row := _level_row(safe_lv)
	if row.is_empty():
		return 0
	return maxi(0, int(row.get("exp_to_next", 0)))

func get_base_stats() -> Dictionary:
	var stats: Dictionary = StatsServiceRef.calc_base_stats(level, attrs)
	stats["CRIT"] = int(stats.get("CRIT_PERCENT", 0))
	stats["DROP"] = int(stats.get("LOOT_BONUS_PERCENT", 0))
	return stats

func add_exp(v: int, _source: String = "online") -> void:
	if v <= 0:
		return
	var level_cap := _level_cap()
	if level >= level_cap:
		exp = 0
		save_progress()
		return

	exp += v
	while level < level_cap:
		var need := exp_to_next(level)
		if need <= 0:
			exp = 0
			break
		if exp < need:
			break
		exp -= need
		var gain_level := level
		level += 1
		var attr_gain := _attr_points_gain_for_level(gain_level)
		if attr_gain > 0:
			free_attr_points += attr_gain
			EventBus.add_log("升级！等级提升到 %d，可用属性点+%d" % [level, attr_gain])
		else:
			EventBus.add_log("升级！等级提升到 %d" % level)
		TaskService.on_level_changed(level)
		_emit_progression_milestone_notice(level)

	if level >= level_cap:
		level = level_cap
		exp = 0

	EventBus.notify_inventory_updated()
	save_progress()

func spend_attr(attr_id: String) -> bool:
	if not attrs.has(attr_id):
		return false
	if free_attr_points <= 0:
		return false
	attrs[attr_id] = int(attrs.get(attr_id, 0)) + 1
	free_attr_points -= 1
	EventBus.notify_inventory_updated()
	save_progress()
	return true

func set_current_sect(sect_id: String) -> void:
	if sect_id.is_empty():
		return
	if current_sect_id == sect_id:
		return
	current_sect_id = sect_id
	EventBus.notify_inventory_updated()
	save_progress()

func spend_skill_point(skill_id: String) -> bool:
	if skill_id.is_empty():
		return false
	if skill_points <= 0:
		return false
	var key := _skill_key(current_sect_id, skill_id)
	skill_points -= 1
	skill_levels[key] = int(skill_levels.get(key, 0)) + 1
	EventBus.notify_inventory_updated()
	save_progress()
	return true

func get_skill_level(sect_id: String, skill_id: String) -> int:
	if sect_id.is_empty() or skill_id.is_empty():
		return 0
	return int(skill_levels.get(_skill_key(sect_id, skill_id), 0))

func load() -> void:
	load_progress()

func save() -> void:
	save_progress()

func load_progress() -> void:
	var initial := _initial_cfg()
	level = maxi(1, int(initial.get("level", 1)))
	exp = 0
	free_attr_points = maxi(0, int(initial.get("free_attr_points", 0)))
	skill_points = maxi(0, int(initial.get("skill_points", 1)))
	attrs = _initial_base_attributes()
	current_sect_id = DEFAULT_SECT_ID
	skill_levels = {}
	claimed_milestones = {}

	if not FileAccess.file_exists(SAVE_PATH):
		save_progress()
		return

	var text := FileAccess.get_file_as_string(SAVE_PATH)
	var parsed: Variant = JSON.parse_string(text)
	if not (parsed is Dictionary):
		save_progress()
		return
	var data: Dictionary = parsed
	level = maxi(1, int(data.get("level", level)))
	exp = maxi(0, int(data.get("exp", 0)))
	free_attr_points = maxi(0, int(data.get("free_attr_points", data.get("attr_points", free_attr_points))))
	skill_points = maxi(0, int(data.get("skill_points", skill_points)))
	current_sect_id = str(data.get("current_sect_id", DEFAULT_SECT_ID))
	if current_sect_id.is_empty():
		current_sect_id = DEFAULT_SECT_ID
	var levels_any = data.get("skill_levels", {})
	if levels_any is Dictionary:
		skill_levels = (levels_any as Dictionary).duplicate(true)
	else:
		skill_levels = {}
	var claimed_any = data.get("claimed_milestones", {})
	if claimed_any is Dictionary:
		claimed_milestones = (claimed_any as Dictionary).duplicate(true)
	else:
		claimed_milestones = {}
	var attrs_any = data.get("attrs", {})
	if attrs_any is Dictionary:
		var src: Dictionary = attrs_any
		attrs = _initial_base_attributes()
		var legacy_map := {
			"strength": "str",
			"physique": "vit",
			"agility": "agi",
			"spirit": "spi",
			"true_energy": "qi",
			"fortune": "luck",
		}
		for k_any in attrs.keys():
			var key := str(k_any)
			var value: Variant = src.get(key, null)
			if value == null and legacy_map.has(key):
				value = src.get(str(legacy_map[key]), 0)
			attrs[key] = maxi(0, int(value if value != null else 0))
	else:
		attrs = _initial_base_attributes()

	var level_cap := _level_cap()
	level = mini(level, level_cap)
	if level >= level_cap:
		exp = 0
	else:
		var need := exp_to_next(level)
		exp = 0 if need <= 0 else mini(exp, need - 1)

func save_progress() -> void:
	var file := FileAccess.open(SAVE_PATH, FileAccess.WRITE)
	if file == null:
		push_warning("ProgressModel: failed to open save file")
		return
	var payload := {
		"level": level,
		"exp": exp,
		"free_attr_points": free_attr_points,
		"skill_points": skill_points,
		"current_sect_id": current_sect_id,
		"attrs": attrs,
		"skill_levels": skill_levels,
		"claimed_milestones": claimed_milestones,
	}
	file.store_string(JSON.stringify(payload))

func _skill_key(sect_id: String, skill_id: String) -> String:
	return "%s::%s" % [sect_id, skill_id]

func _growth_cfg() -> Dictionary:
	return ConfigService.get_character_growth_rules()

func _initial_cfg() -> Dictionary:
	var growth := _growth_cfg()
	var initial_any = growth.get("initial", {})
	if initial_any is Dictionary:
		return initial_any
	return {}

func _initial_base_attributes() -> Dictionary:
	var out := DEFAULT_ATTRS.duplicate(true)
	var initial := _initial_cfg()
	var attrs_any = initial.get("base_attributes", {})
	if not (attrs_any is Dictionary):
		return out
	var src: Dictionary = attrs_any
	for key_any in out.keys():
		var key := str(key_any)
		out[key] = maxi(0, int(src.get(key, out.get(key, 0))))
	return out

func _level_cap() -> int:
	var growth := _growth_cfg()
	var level_cap := int(growth.get("level_cap", 0))
	return maxi(20, level_cap)

func _level_row(lv: int) -> Dictionary:
	var growth := _growth_cfg()
	var rows_any = growth.get("level_exp_table", [])
	if not (rows_any is Array):
		return {}
	for row_any in rows_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		if int(row.get("level", 0)) == lv:
			return row
	return {}

func _attr_points_gain_for_level(lv: int) -> int:
	var row := _level_row(lv)
	if row.is_empty():
		return 0
	return maxi(0, int(row.get("attr_points_gain", 0)))

func _emit_progression_milestone_notice(new_level: int) -> void:
	var milestone := _progression_milestone_for_level(new_level)
	if milestone.is_empty():
		return
	EventBus.add_log("成长里程碑已开放：%s" % str(milestone.get("title", "成长里程碑")))
	var summary := str(milestone.get("summary", "")).strip_edges()
	if not summary.is_empty():
		EventBus.add_log(summary)
	var unlock_parts := _milestone_unlock_texts(milestone)
	if not unlock_parts.is_empty():
		EventBus.add_log("本阶段开放：" + "、".join(unlock_parts.slice(0, mini(2, unlock_parts.size()))))
	var reward_name := _reward_item_name(milestone)
	var reward_count := maxi(1, int(milestone.get("reward_count", 1)))
	if not reward_name.is_empty():
		EventBus.add_log("可领取里程碑礼包：%s x%d" % [reward_name, reward_count])

func _progression_milestone_for_level(target_level: int) -> Dictionary:
	for row in get_growth_milestones():
		if int(row.get("level", 0)) == target_level:
			return row
	return {}

func get_growth_milestones(include_disabled: bool = false) -> Array[Dictionary]:
	var progression := ConfigService.get_progression_milestones()
	var milestones_any = progression.get("milestones", [])
	if not (milestones_any is Array):
		return []
	var rows: Array[Dictionary] = []
	for row_any in milestones_any:
		if not (row_any is Dictionary):
			continue
		var row := _normalize_growth_milestone(row_any as Dictionary)
		if not include_disabled and not bool(row.get("is_enabled", true)):
			continue
		rows.append(row)
	rows.sort_custom(func(a: Dictionary, b: Dictionary) -> bool:
		var level_a := int(a.get("level", 0))
		var level_b := int(b.get("level", 0))
		if level_a != level_b:
			return level_a < level_b
		return int(a.get("sort", 0)) < int(b.get("sort", 0))
	)
	return rows

func get_current_growth_milestone() -> Dictionary:
	var best: Dictionary = {}
	for row in get_growth_milestones():
		var row_level := int(row.get("level", 0))
		if row_level <= level and (best.is_empty() or row_level > int(best.get("level", 0))):
			best = row
	return best

func get_next_growth_milestone() -> Dictionary:
	var best: Dictionary = {}
	for row in get_growth_milestones():
		var row_level := int(row.get("level", 0))
		if row_level > level and (best.is_empty() or row_level < int(best.get("level", 999))):
			best = row
	return best

func has_claimed_milestone(milestone_key: String) -> bool:
	if milestone_key.is_empty():
		return false
	return bool(claimed_milestones.get(milestone_key, false))

func is_milestone_reached(milestone: Dictionary) -> bool:
	var safe_milestone := _normalize_growth_milestone(milestone)
	return level >= int(safe_milestone.get("level", 0))

func can_claim_milestone(milestone: Dictionary) -> bool:
	var safe_milestone := _normalize_growth_milestone(milestone)
	if safe_milestone.is_empty():
		return false
	if not bool(safe_milestone.get("is_enabled", true)):
		return false
	if not is_milestone_reached(safe_milestone):
		return false
	var milestone_key := str(safe_milestone.get("milestone_key", "")).strip_edges()
	if milestone_key.is_empty():
		return false
	if bool(safe_milestone.get("claim_once", true)) and has_claimed_milestone(milestone_key):
		return false
	return not str(safe_milestone.get("reward_item_id", "")).strip_edges().is_empty()

func claim_milestone_reward(milestone: Dictionary) -> Dictionary:
	var safe_milestone := _normalize_growth_milestone(milestone)
	if safe_milestone.is_empty():
		return {"ok": false, "reason": "missing"}
	if not bool(safe_milestone.get("is_enabled", true)):
		return {"ok": false, "reason": "disabled"}
	if not is_milestone_reached(safe_milestone):
		return {"ok": false, "reason": "locked"}
	var milestone_key := str(safe_milestone.get("milestone_key", "")).strip_edges()
	if milestone_key.is_empty():
		return {"ok": false, "reason": "missing"}
	if bool(safe_milestone.get("claim_once", true)) and has_claimed_milestone(milestone_key):
		return {"ok": false, "reason": "claimed"}
	var item_id := str(safe_milestone.get("reward_item_id", "")).strip_edges()
	var reward_count := maxi(1, int(safe_milestone.get("reward_count", 1)))
	if item_id.is_empty():
		return {"ok": false, "reason": "missing"}
	InventoryModel.add_item(item_id, reward_count, "system")
	if bool(safe_milestone.get("claim_once", true)):
		claimed_milestones[milestone_key] = true
		save_progress()
	var reward_name := _item_name(item_id)
	EventBus.add_log("已领取成长里程碑：%s（%s x%d）" % [
		str(safe_milestone.get("title", "成长里程碑")),
		reward_name if not reward_name.is_empty() else item_id,
		reward_count,
	])
	return {
		"ok": true,
		"item_id": item_id,
		"count": reward_count,
		"title": str(safe_milestone.get("title", "")),
	}

func _milestone_unlock_texts(milestone: Dictionary) -> Array[String]:
	var out: Array[String] = []
	var rows_any = _normalize_growth_milestone(milestone).get("unlock_contents", [])
	if not (rows_any is Array):
		return out
	for row_any in rows_any:
		if not (row_any is Dictionary):
			continue
		var text := str((row_any as Dictionary).get("content", "")).strip_edges()
		if text.is_empty():
			continue
		out.append(text)
	return out

func _reward_item_name(milestone: Dictionary) -> String:
	var safe_milestone := _normalize_growth_milestone(milestone)
	return _item_name(str(safe_milestone.get("reward_item_id", "")))

func _normalize_growth_milestone(row: Dictionary) -> Dictionary:
	if row.is_empty():
		return {}
	var out: Dictionary = row.duplicate(true)
	var level_value := maxi(1, int(out.get("level", 0)))
	out["level"] = level_value

	var milestone_key := str(out.get("milestone_key", "")).strip_edges()
	if milestone_key.is_empty():
		milestone_key = "level_%d" % level_value
	out["milestone_key"] = milestone_key

	var reward_item_id := str(out.get("reward_item_id", "")).strip_edges()
	var reward_count := maxi(1, int(out.get("reward_count", 1)))
	out["reward_item_id"] = reward_item_id
	out["reward_count"] = reward_count

	out["unlock_contents"] = _normalize_milestone_unlock_contents(out.get("unlock_contents", []))
	return out

func _normalize_milestone_unlock_contents(rows_any: Variant) -> Array[Dictionary]:
	var out: Array[Dictionary] = []
	if not (rows_any is Array):
		return out
	for row_any in rows_any:
		if row_any is Dictionary:
			var row: Dictionary = row_any
			var text := str(row.get("content", "")).strip_edges()
			if text.is_empty():
				continue
			out.append({
				"type": str(row.get("type", "feature_unlock")).strip_edges(),
				"content": text,
			})
			continue
		var text := str(row_any).strip_edges()
		if text.is_empty():
			continue
		out.append({
			"type": "feature_unlock",
			"content": text,
		})
	return out

func _item_name(item_id: String) -> String:
	if item_id.is_empty():
		return ""
	var cfg: Dictionary = ConfigService.get_cfg()
	var items_db_any = cfg.get("items_db", {})
	if not (items_db_any is Dictionary):
		return item_id
	var rows_any = (items_db_any as Dictionary).get("items", [])
	if not (rows_any is Array):
		return item_id
	for row_any in rows_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		if str(row.get("id", "")) == item_id:
			return str(row.get("name", item_id))
	return item_id
