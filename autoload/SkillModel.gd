extends Node

const SAVE_PATH := "user://skills.json"
const SECT_TOKEN_ITEM_ID := "宗门令"

var current_class: String = "bing"
var skill_points: int = 1
var skill_levels: Dictionary = {}
var ai_profile: String = "clear"
var last_switch_ts: float = -999.0

func _ready() -> void:
	load_skills()

func can_upgrade(skill_id: String) -> bool:
	var skill := _find_skill(skill_id)
	if skill.is_empty():
		return false
	var skill_class := str(skill.get("class", ""))
	if skill_class != "global" and skill_class != current_class:
		return false
	if ProgressModel.level < int(skill.get("min_level", 1)):
		return false
	var max_level := maxi(1, int(skill.get("max_level", 1)))
	if get_skill_level(skill_id) >= max_level:
		return false
	return skill_points > 0

func upgrade(skill_id: String) -> bool:
	if not can_upgrade(skill_id):
		return false
	var cur_level := get_skill_level(skill_id)
	var next_level := maxi(1, cur_level + 1)
	skill_levels[skill_id] = next_level
	skill_points -= 1
	save_skills()
	EventBus.notify_inventory_updated()
	var skill := _find_skill(skill_id)
	var skill_name := str(skill.get("name", skill_id))
	EventBus.add_log("技能升级：%s Lv%d" % [skill_name, next_level])
	return true

func switch_class(next_id: String) -> bool:
	if next_id.is_empty():
		return false
	if next_id == current_class:
		return true
	if not _has_class(next_id):
		return false
	if not InventoryModel.spend_item(SECT_TOKEN_ITEM_ID, 1):
		return false
	current_class = next_id
	save_skills()
	EventBus.notify_inventory_updated()
	EventBus.add_log("切换宗门：%s" % _class_name(next_id))
	TaskService.on_class_switched()
	return true

func grant_skill_points(n: int, reason: String = "") -> void:
	if n <= 0:
		return
	skill_points += n
	save_skills()
	EventBus.notify_inventory_updated()
	if not reason.is_empty():
		EventBus.add_log("获得技能点+%d（%s）" % [n, reason])

func set_ai_profile(p: String) -> void:
	if not _is_valid_ai_profile(p):
		return
	if ai_profile == p:
		return
	ai_profile = p
	save()
	EventBus.notify_inventory_updated()

func cycle_ai_profile() -> void:
	var order := PackedStringArray(["clear", "boss", "survival"])
	var idx := order.find(ai_profile)
	if idx < 0:
		idx = 0
	var next_profile := order[(idx + 1) % order.size()]
	set_ai_profile(next_profile)

func get_ai_profile_name() -> String:
	return I18nService.t("ui.strategy.%s" % ai_profile, ai_profile)

func get_skill_level(skill_id: String) -> int:
	return maxi(0, int(skill_levels.get(skill_id, 0)))

func get_effective_level(skill_id: String) -> int:
	var base_level := get_skill_level(skill_id)
	if skill_id.is_empty():
		return base_level
	var bonus := 0
	for slot_key_any in EquipmentModel.equipped.keys():
		var slot_key := str(slot_key_any)
		var inst: Dictionary = EquipmentModel.get_equipped_instance(slot_key)
		if inst.is_empty():
			continue
		var effects_any = inst.get("effects", [])
		if not (effects_any is Array):
			continue
		for effect_any in effects_any:
			if not (effect_any is Dictionary):
				continue
			var effect: Dictionary = effect_any
			if str(effect.get("type", "")) != "skill_level":
				continue
			if str(effect.get("skill_id", "")) != skill_id:
				continue
			bonus += int(effect.get("val", 0))
	return maxi(0, base_level + bonus)

func load() -> void:
	load_skills()

func save() -> void:
	save_skills()

func load_skills() -> void:
	var balance := _balance_cfg()
	var default_class := _default_class_id(balance)
	var default_points := _default_skill_points(balance)

	current_class = default_class
	skill_points = default_points
	skill_levels = {}
	ai_profile = "clear"
	last_switch_ts = -999.0

	if not FileAccess.file_exists(SAVE_PATH):
		save_skills()
		return

	var text := FileAccess.get_file_as_string(SAVE_PATH)
	var parsed: Variant = JSON.parse_string(text)
	if not (parsed is Dictionary):
		save_skills()
		return
	var data: Dictionary = parsed
	current_class = str(data.get("current_class", default_class))
	if not _has_class(current_class):
		current_class = default_class
	skill_points = maxi(0, int(data.get("skill_points", default_points)))
	var levels_any = data.get("skill_levels", {})
	if levels_any is Dictionary:
		skill_levels = (levels_any as Dictionary).duplicate(true)
	else:
		skill_levels = {}
	ai_profile = str(data.get("ai_profile", "clear"))
	if not _is_valid_ai_profile(ai_profile):
		ai_profile = "clear"
	last_switch_ts = float(data.get("last_switch_ts", -999.0))

func save_skills() -> void:
	var file := FileAccess.open(SAVE_PATH, FileAccess.WRITE)
	if file == null:
		push_warning("SkillModel: failed to open save file")
		return
	var payload := {
		"current_class": current_class,
		"skill_points": skill_points,
		"skill_levels": skill_levels,
		"ai_profile": ai_profile,
		"last_switch_ts": last_switch_ts,
	}
	file.store_string(JSON.stringify(payload))

func _find_skill(skill_id: String) -> Dictionary:
	var balance := _balance_cfg()
	var skills_any = balance.get("skills", [])
	if not (skills_any is Array):
		return {}
	for skill_any in skills_any:
		if not (skill_any is Dictionary):
			continue
		var skill: Dictionary = skill_any
		if str(skill.get("id", "")) == skill_id:
			return skill
	return {}

func _has_class(class_id: String) -> bool:
	var balance := _balance_cfg()
	var classes_any = balance.get("classes", [])
	if not (classes_any is Array):
		return false
	for cls_any in classes_any:
		if not (cls_any is Dictionary):
			continue
		var cls: Dictionary = cls_any
		if str(cls.get("id", "")) == class_id:
			return true
	return false

func _class_name(class_id: String) -> String:
	var balance := _balance_cfg()
	var classes_any = balance.get("classes", [])
	if not (classes_any is Array):
		return class_id
	for cls_any in classes_any:
		if not (cls_any is Dictionary):
			continue
		var cls: Dictionary = cls_any
		if str(cls.get("id", "")) == class_id:
			return str(cls.get("name", class_id))
	return class_id

func _default_class_id(balance: Dictionary) -> String:
	var starter_any = balance.get("starter", {})
	if starter_any is Dictionary:
		var starter: Dictionary = starter_any
		var from_starter := str(starter.get("starting_class_id", ""))
		if not from_starter.is_empty():
			return from_starter
	var classes_any = balance.get("classes", [])
	if classes_any is Array:
		for cls_any in classes_any:
			if not (cls_any is Dictionary):
				continue
			var cls: Dictionary = cls_any
			var cls_id := str(cls.get("id", ""))
			if not cls_id.is_empty():
				return cls_id
	return "bing"

func _default_skill_points(balance: Dictionary) -> int:
	var starter_any = balance.get("starter", {})
	if starter_any is Dictionary:
		var starter: Dictionary = starter_any
		return maxi(0, int(starter.get("skill_points", 1)))
	var classes_any = balance.get("classes", [])
	if classes_any is Array:
		for cls_any in classes_any:
			if not (cls_any is Dictionary):
				continue
			var cls: Dictionary = cls_any
			var cls_starter_any = cls.get("starter", {})
			if cls_starter_any is Dictionary:
				return maxi(0, int((cls_starter_any as Dictionary).get("skill_points", 1)))
	return 1

func _balance_cfg() -> Dictionary:
	var cfg: Dictionary = ConfigService.get_cfg()
	var balance_any = cfg.get("balance", {})
	if balance_any is Dictionary:
		return balance_any
	return {}

func _is_valid_ai_profile(p: String) -> bool:
	return p == "clear" or p == "boss" or p == "survival"
