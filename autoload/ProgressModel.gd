extends Node

const SAVE_PATH := "user://progress.json"
const LEVEL_CAP := 60
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

func _ready() -> void:
	load_progress()

func exp_to_next(lv: int) -> int:
	var safe_lv := maxi(1, lv)
	return 10 + safe_lv * 2

func get_base_stats() -> Dictionary:
	var stats: Dictionary = StatsServiceRef.calc_base_stats(level, attrs)
	stats["CRIT"] = int(stats.get("CRIT_PERCENT", 0))
	stats["DROP"] = int(stats.get("LOOT_BONUS_PERCENT", 0))
	return stats

func add_exp(v: int, _source: String = "online") -> void:
	if v <= 0:
		return
	if level >= LEVEL_CAP:
		exp = 0
		save_progress()
		return

	exp += v
	while level < LEVEL_CAP:
		var need := exp_to_next(level)
		if exp < need:
			break
		exp -= need
		level += 1
		free_attr_points += 1
		EventBus.add_log("升级！等级提升到 %d，可用属性点+1" % level)
		TaskService.on_level_changed(level)

	if level >= LEVEL_CAP:
		level = LEVEL_CAP
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
	level = 1
	exp = 0
	free_attr_points = 0
	skill_points = 0
	attrs = DEFAULT_ATTRS.duplicate(true)
	current_sect_id = DEFAULT_SECT_ID
	skill_levels = {}

	if not FileAccess.file_exists(SAVE_PATH):
		save_progress()
		return

	var text := FileAccess.get_file_as_string(SAVE_PATH)
	var parsed: Variant = JSON.parse_string(text)
	if not (parsed is Dictionary):
		save_progress()
		return
	var data: Dictionary = parsed
	level = maxi(1, int(data.get("level", 1)))
	exp = maxi(0, int(data.get("exp", 0)))
	free_attr_points = maxi(0, int(data.get("free_attr_points", data.get("attr_points", 0))))
	skill_points = maxi(0, int(data.get("skill_points", 0)))
	current_sect_id = str(data.get("current_sect_id", DEFAULT_SECT_ID))
	if current_sect_id.is_empty():
		current_sect_id = DEFAULT_SECT_ID
	var levels_any = data.get("skill_levels", {})
	if levels_any is Dictionary:
		skill_levels = (levels_any as Dictionary).duplicate(true)
	else:
		skill_levels = {}
	var attrs_any = data.get("attrs", {})
	if attrs_any is Dictionary:
		var src: Dictionary = attrs_any
		attrs = DEFAULT_ATTRS.duplicate(true)
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
		attrs = DEFAULT_ATTRS.duplicate(true)

	level = mini(level, LEVEL_CAP)
	if level >= LEVEL_CAP:
		exp = 0
	else:
		exp = mini(exp, exp_to_next(level) - 1)

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
	}
	file.store_string(JSON.stringify(payload))

func _skill_key(sect_id: String, skill_id: String) -> String:
	return "%s::%s" % [sect_id, skill_id]
