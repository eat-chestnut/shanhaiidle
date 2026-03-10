extends Control

const SECT_TOKEN_ITEM_ID := "宗门令"

@onready var _title: Label = $RootVBox/Header/Title
@onready var _lbl_current_sect: Label = $RootVBox/SectBar/LblCurrentSect
@onready var _lbl_sect_name: Label = $RootVBox/SectBar/LblSectName
@onready var _btn_switch_sect: Button = $RootVBox/SectBar/BtnSwitchSect
@onready var _lbl_switch_need: Label = $RootVBox/SectBar/LblSwitchNeed
@onready var _lbl_skill_points: Label = $RootVBox/SkillPointBar/LblSkillPoints
@onready var _skill_list: VBoxContainer = $RootVBox/SkillScroll/SkillList
@onready var _btn_nav_character: Button = $RootVBox/BottomNav/BtnNavCharacter
@onready var _btn_nav_skills: Button = $RootVBox/BottomNav/BtnNavSkills
@onready var _btn_nav_battle: Button = $RootVBox/BottomNav/BtnNavBattle
@onready var _btn_nav_bag: Button = $RootVBox/BottomNav/BtnNavBag
@onready var _btn_nav_dex: Button = $RootVBox/BottomNav/BtnNavDex
@onready var _btn_nav_map: Button = $RootVBox/BottomNav/BtnNavMap
@onready var _badge_char: Node = $RootVBox/BottomNav/BtnNavCharacter/Badge
@onready var _badge_skill: Node = $RootVBox/BottomNav/BtnNavSkills/Badge
@onready var _badge_dex: Node = $RootVBox/BottomNav/BtnNavDex/Badge

func _ready() -> void:
	_apply_i18n()
	_connect_signals()
	refresh_ui()

func refresh_ui() -> void:
	var sect_name := _class_name(SkillModel.current_class)
	_lbl_sect_name.text = sect_name

	var token_count := InventoryModel.get_count(SECT_TOKEN_ITEM_ID)
	_lbl_switch_need.text = "%s×%d" % [I18nService.t("ui.sect_token", "宗门令"), token_count]
	_btn_switch_sect.disabled = token_count <= 0 or _classes_order().size() <= 1

	_lbl_skill_points.text = "%s：%d" % [
		I18nService.t("ui.skill_points", "技能点"),
		SkillModel.skill_points,
	]
	_rebuild_skill_nodes()
	_refresh_badges()

func _rebuild_skill_nodes() -> void:
	for child in _skill_list.get_children():
		_skill_list.remove_child(child)
		child.queue_free()

	var skills := _active_skills_for_current_class()
	for skill in skills:
		var skill_id := str(skill.get("id", ""))
		var skill_name := SkillNameService.name(skill_id)
		var base_lv := SkillModel.get_skill_level(skill_id)
		var eff_lv := SkillModel.get_effective_level(skill_id)
		var bonus_lv := eff_lv - base_lv
		var max_lv := maxi(1, int(skill.get("max_level", 1)))
		var min_lv := maxi(1, int(skill.get("min_level", 1)))
		var cost_qi := maxi(0, int(skill.get("cost_qi", 0)))
		var cd_sec := float(skill.get("cd_sec", 0.0))

		var row := HBoxContainer.new()
		row.custom_minimum_size = Vector2(0, 84)
		row.size_flags_horizontal = Control.SIZE_EXPAND_FILL
		row.add_theme_constant_override("separation", 8)

		var icon := TextureRect.new()
		icon.custom_minimum_size = Vector2(44, 44)
		icon.texture = load("res://assets/icons/icon_killskill.png")
		icon.expand_mode = TextureRect.EXPAND_IGNORE_SIZE
		icon.stretch_mode = TextureRect.STRETCH_KEEP_ASPECT_CENTERED
		row.add_child(icon)

		var info := VBoxContainer.new()
		info.size_flags_horizontal = Control.SIZE_EXPAND_FILL
		row.add_child(info)

		var title := Label.new()
		title.add_theme_font_size_override("font_size", 22)
		if bonus_lv > 0:
			title.text = "%s  Lv.%d(+%d)/%d" % [skill_name, base_lv, bonus_lv, max_lv]
		else:
			title.text = "%s  Lv.%d/%d" % [skill_name, base_lv, max_lv]
		info.add_child(title)

		var meta := Label.new()
		meta.add_theme_font_size_override("font_size", 16)
		meta.text = "需Lv%d  %s:%d  CD:%.1fs" % [min_lv, I18nService.stat("QI"), cost_qi, cd_sec]
		info.add_child(meta)

		var desc := Label.new()
		desc.add_theme_font_size_override("font_size", 16)
		desc.autowrap_mode = TextServer.AUTOWRAP_WORD_SMART
		desc.modulate = Color(0.8, 0.8, 0.8, 1.0)
		var manual_desc := SkillNameService.desc(skill_id).strip_edges()
		desc.text = manual_desc if not manual_desc.is_empty() else _build_skill_desc(skill)
		info.add_child(desc)

		var btn := Button.new()
		btn.custom_minimum_size = Vector2(52, 40)
		btn.add_theme_font_size_override("font_size", 24)
		btn.text = "+"
		btn.disabled = not SkillModel.can_upgrade(skill_id)
		btn.pressed.connect(_on_upgrade_pressed.bind(skill_id))
		row.add_child(btn)

		var panel := PanelContainer.new()
		panel.size_flags_horizontal = Control.SIZE_EXPAND_FILL
		panel.add_child(row)
		_skill_list.add_child(panel)

func _build_skill_desc(skill: Dictionary) -> String:
	var tags_any = skill.get("tags", [])
	var tags: Array = tags_any if tags_any is Array else []
	var is_aoe := tags.has("aoe") or tags.has("multi")
	var dmg_any = skill.get("damage", {})
	if dmg_any is Dictionary and (dmg_any as Dictionary).has("aoe"):
		is_aoe = true
	var prefix := "群体" if is_aoe else "单体"

	var damage_txt := "伤害 0%攻击，每级+0%"
	if dmg_any is Dictionary:
		var dmg: Dictionary = dmg_any
		var base := float(dmg.get("base_coef", 0.0))
		var per := float(dmg.get("per_level", 0.0))
		damage_txt = "伤害 %.0f%%攻击，每级+%.0f%%" % [base * 100.0, per * 100.0]

	var debuff_txt := ""
	var debuffs_any = skill.get("debuffs", [])
	if debuffs_any is Array and not (debuffs_any as Array).is_empty():
		var first_any = (debuffs_any as Array)[0]
		if first_any is Dictionary:
			var first: Dictionary = first_any
			var pct := float(first.get("armor_reduction_pct", 0.0))
			var dur := float(first.get("duration_sec", 0.0))
			if pct > 0.0 and dur > 0.0:
				debuff_txt = "；破甲 -%.0f%% 持续%.0fs" % [pct * 100.0, dur]

	var milestone_txt := ""
	var milestones_any = skill.get("milestones", {})
	if milestones_any is Dictionary and (
		(milestones_any as Dictionary).has("5")
		or (milestones_any as Dictionary).has("10")
		or (milestones_any as Dictionary).has("15")
	):
		milestone_txt = "（Lv5/10/15有特性）"

	return "%s：%s%s%s" % [prefix, damage_txt, debuff_txt, milestone_txt]

func _active_skills_for_current_class() -> Array[Dictionary]:
	var rows: Array[Dictionary] = []
	var cfg: Dictionary = ConfigService.get_cfg()
	var balance_any = cfg.get("balance", {})
	if not (balance_any is Dictionary):
		return rows
	var balance: Dictionary = balance_any
	var skills_any = balance.get("skills", [])
	if not (skills_any is Array):
		return rows
	for skill_any in skills_any:
		if not (skill_any is Dictionary):
			continue
		var skill: Dictionary = skill_any
		if str(skill.get("type", "")) != "active":
			continue
		if str(skill.get("class", "")) != SkillModel.current_class:
			continue
		rows.append(skill)
	rows.sort_custom(_sort_skill_rows)
	return rows

func _sort_skill_rows(a: Dictionary, b: Dictionary) -> bool:
	var la := int(a.get("min_level", 1))
	var lb := int(b.get("min_level", 1))
	if la != lb:
		return la < lb
	return str(a.get("id", "")) < str(b.get("id", ""))

func _classes_order() -> Array[String]:
	var order: Array[String] = []
	var cfg: Dictionary = ConfigService.get_cfg()
	var balance_any = cfg.get("balance", {})
	if not (balance_any is Dictionary):
		return order
	var classes_any = (balance_any as Dictionary).get("classes", [])
	if not (classes_any is Array):
		return order
	for cls_any in classes_any:
		if not (cls_any is Dictionary):
			continue
		var cls: Dictionary = cls_any
		var cls_id := str(cls.get("id", ""))
		if cls_id.is_empty():
			continue
		order.append(cls_id)
	return order

func _class_name(class_id: String) -> String:
	var cfg: Dictionary = ConfigService.get_cfg()
	var balance_any = cfg.get("balance", {})
	if balance_any is Dictionary:
		var classes_any = (balance_any as Dictionary).get("classes", [])
		if classes_any is Array:
			for cls_any in classes_any:
				if not (cls_any is Dictionary):
					continue
				var cls: Dictionary = cls_any
				if str(cls.get("id", "")) == class_id:
					return str(cls.get("name", class_id))
	return class_id

func _next_class_id() -> String:
	var order := _classes_order()
	if order.is_empty():
		return SkillModel.current_class
	var idx := order.find(SkillModel.current_class)
	if idx < 0:
		return order[0]
	return order[(idx + 1) % order.size()]

func _apply_i18n() -> void:
	_title.text = I18nService.t("ui.skills.title", "技能")
	_lbl_current_sect.text = "%s：" % I18nService.t("ui.current_sect", "当前宗门")
	_btn_switch_sect.text = I18nService.t("ui.switch_sect", "切换宗门")
	_btn_nav_character.text = I18nService.t("ui.nav.character", "人物")
	_btn_nav_skills.text = I18nService.t("ui.nav.skills", "技能")
	_btn_nav_battle.text = I18nService.t("ui.nav.battle", "战斗")
	_btn_nav_bag.text = I18nService.t("ui.nav.bag", "背包")
	_btn_nav_dex.text = I18nService.t("ui.nav.dex", "图鉴")
	_btn_nav_map.text = I18nService.t("ui.nav.dungeon", "副本")
	_btn_nav_skills.disabled = true
	_btn_nav_dex.disabled = false
	_btn_nav_map.disabled = false

func _connect_signals() -> void:
	if not EventBus.inventory_updated.is_connected(_on_model_changed):
		EventBus.inventory_updated.connect(_on_model_changed)
	if not _btn_switch_sect.pressed.is_connected(_on_switch_sect_pressed):
		_btn_switch_sect.pressed.connect(_on_switch_sect_pressed)
	if not _btn_nav_character.pressed.is_connected(_on_nav_character_pressed):
		_btn_nav_character.pressed.connect(_on_nav_character_pressed)
	if not _btn_nav_battle.pressed.is_connected(_on_nav_battle_pressed):
		_btn_nav_battle.pressed.connect(_on_nav_battle_pressed)
	if not _btn_nav_dex.pressed.is_connected(_on_nav_dex_pressed):
		_btn_nav_dex.pressed.connect(_on_nav_dex_pressed)
	if not _btn_nav_map.pressed.is_connected(_on_nav_map_pressed):
		_btn_nav_map.pressed.connect(_on_nav_map_pressed)

func _on_model_changed() -> void:
	refresh_ui()

func _on_upgrade_pressed(skill_id: String) -> void:
	if SkillModel.upgrade(skill_id):
		refresh_ui()

func _on_switch_sect_pressed() -> void:
	SkillModel.switch_class(_next_class_id())
	refresh_ui()

func _on_nav_character_pressed() -> void:
	get_tree().change_scene_to_file("res://ui/pages/PageCharacter.tscn")

func _on_nav_battle_pressed() -> void:
	get_tree().change_scene_to_file("res://ui/pages/PageBattle.tscn")

func _on_nav_dex_pressed() -> void:
	get_tree().change_scene_to_file("res://ui/pages/PageDexHome.tscn")

func _on_nav_map_pressed() -> void:
	get_tree().change_scene_to_file("res://ui/pages/PageDungeon.tscn")

func _refresh_badges() -> void:
	if _badge_char != null and _badge_char.has_method("set_dot"):
		_badge_char.call("set_dot", ProgressModel.free_attr_points > 0)
	if _badge_skill != null and _badge_skill.has_method("set_value"):
		_badge_skill.call("set_value", int(SkillModel.skill_points), false)
	if _badge_dex != null and _badge_dex.has_method("set_dot"):
		_badge_dex.call("set_dot", DexHubService.has_pending_rewards())
