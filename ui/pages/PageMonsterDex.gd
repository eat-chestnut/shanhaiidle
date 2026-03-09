extends Control

const PAGE_BATTLE := "res://ui/pages/PageBattle.tscn"
const PAGE_CHARACTER := "res://ui/pages/PageCharacter.tscn"
const PAGE_SKILLS := "res://ui/pages/PageSkills.tscn"
const PAGE_MAP := "res://ui/pages/PageMap.tscn"

@onready var _title: Label = $RootVBox/Header/HeaderRow/Title
@onready var _hint: Label = $RootVBox/Header/HeaderRow/Hint
@onready var _btn_back: Button = $RootVBox/Header/HeaderRow/BtnBack
@onready var _monster_list: VBoxContainer = $RootVBox/MonsterScroll/MonsterList
@onready var _detail_title: Label = $RootVBox/DetailPanel/DetailVBox/DetailTitle
@onready var _detail_text: RichTextLabel = $RootVBox/DetailPanel/DetailVBox/DetailScroll/DetailText
@onready var _btn_claim: Button = $RootVBox/DetailPanel/DetailVBox/BtnClaim
@onready var _btn_nav_character: Button = $RootVBox/BottomNav/BtnNavCharacter
@onready var _btn_nav_skills: Button = $RootVBox/BottomNav/BtnNavSkills
@onready var _btn_nav_battle: Button = $RootVBox/BottomNav/BtnNavBattle
@onready var _btn_nav_bag: Button = $RootVBox/BottomNav/BtnNavBag
@onready var _btn_nav_dex: Button = $RootVBox/BottomNav/BtnNavDex
@onready var _btn_nav_map: Button = $RootVBox/BottomNav/BtnNavMap
@onready var _badge_char: Node = $RootVBox/BottomNav/BtnNavCharacter/Badge
@onready var _badge_skill: Node = $RootVBox/BottomNav/BtnNavSkills/Badge

var _monsters: Array[Dictionary] = []
var _selected_idx := -1

func _ready() -> void:
	_apply_i18n()
	_connect_signals()
	SourceGuideService.rebuild_indexes()
	_load_monsters()
	_rebuild_list()
	if not _monsters.is_empty():
		_select_monster(0)
	else:
		_refresh_detail()
	_refresh_badges()

func _apply_i18n() -> void:
	_title.text = "怪物图鉴"
	_hint.text = "遇到即解锁（奖励手动领取）"
	_btn_back.text = "返回"
	_detail_title.text = "怪物详情"
	_btn_claim.text = "未解锁不可领取"
	_btn_nav_character.text = I18nService.t("ui.nav.character", "人物")
	_btn_nav_skills.text = I18nService.t("ui.nav.skills", "技能")
	_btn_nav_battle.text = I18nService.t("ui.nav.battle", "战斗")
	_btn_nav_bag.text = I18nService.t("ui.nav.bag", "背包")
	_btn_nav_dex.text = I18nService.t("ui.nav.dex", "图鉴")
	_btn_nav_map.text = I18nService.t("ui.nav.map", "地图")
	_btn_nav_dex.disabled = true
	_btn_nav_bag.disabled = false
	_btn_nav_character.disabled = false
	_btn_nav_skills.disabled = false
	_btn_nav_battle.disabled = false
	_btn_nav_map.disabled = false

func _connect_signals() -> void:
	if not EventBus.inventory_updated.is_connected(_on_model_changed):
		EventBus.inventory_updated.connect(_on_model_changed)
	if not _btn_back.pressed.is_connected(_on_back_pressed):
		_btn_back.pressed.connect(_on_back_pressed)
	if not _btn_nav_character.pressed.is_connected(_on_nav_character_pressed):
		_btn_nav_character.pressed.connect(_on_nav_character_pressed)
	if not _btn_nav_skills.pressed.is_connected(_on_nav_skills_pressed):
		_btn_nav_skills.pressed.connect(_on_nav_skills_pressed)
	if not _btn_nav_battle.pressed.is_connected(_on_nav_battle_pressed):
		_btn_nav_battle.pressed.connect(_on_nav_battle_pressed)
	if not _btn_nav_bag.pressed.is_connected(_on_nav_bag_pressed):
		_btn_nav_bag.pressed.connect(_on_nav_bag_pressed)
	if not _btn_nav_map.pressed.is_connected(_on_nav_map_pressed):
		_btn_nav_map.pressed.connect(_on_nav_map_pressed)
	if not _btn_claim.pressed.is_connected(_on_claim_pressed):
		_btn_claim.pressed.connect(_on_claim_pressed)

func _load_monsters() -> void:
	_monsters.clear()
	var cfg: Dictionary = ConfigService.get_cfg()

	var monsters_db_any = cfg.get("monsters_db", {})
	if monsters_db_any is Dictionary:
		var monsters_any = (monsters_db_any as Dictionary).get("monsters", [])
		if monsters_any is Array and not (monsters_any as Array).is_empty():
			var rows: Array[Dictionary] = []
			for mon_any in monsters_any:
				if not (mon_any is Dictionary):
					continue
				var mon: Dictionary = mon_any
				if mon.has("is_enabled") and not bool(mon.get("is_enabled", true)):
					continue
				var row := mon.duplicate(true)
				row["kind"] = _normalize_kind(str(row.get("kind", "normal")))
				rows.append(row)
			if not rows.is_empty():
				rows.sort_custom(_sort_monster_rows)
				for row in rows:
					_monsters.append(row)
				return

	var battle_any = cfg.get("battle", {})
	if not (battle_any is Dictionary):
		return
	var battle_cfg: Dictionary = battle_any
	var normal_any = battle_cfg.get("monsters", [])
	if normal_any is Array and not (normal_any as Array).is_empty():
		var first_any = (normal_any as Array)[0]
		if first_any is Dictionary:
			var normal: Dictionary = (first_any as Dictionary).duplicate(true)
			normal["kind"] = "normal"
			_monsters.append(normal)
	var elite_any = battle_cfg.get("elite_monster", {})
	if elite_any is Dictionary:
		var elite: Dictionary = (elite_any as Dictionary).duplicate(true)
		elite["kind"] = "elite"
		_monsters.append(elite)
	var boss_any = battle_cfg.get("boss_monster", {})
	if boss_any is Dictionary:
		var boss: Dictionary = (boss_any as Dictionary).duplicate(true)
		boss["kind"] = "boss"
		_monsters.append(boss)

func _normalize_kind(raw_kind: String) -> String:
	var kind := raw_kind.strip_edges().to_lower()
	if kind == "elite" or kind == "boss":
		return kind
	return "normal"

func _kind_sort_value(kind: String) -> int:
	match kind:
		"elite":
			return 1
		"boss":
			return 2
		_:
			return 0

func _sort_monster_rows(a: Dictionary, b: Dictionary) -> bool:
	var ka := _kind_sort_value(_normalize_kind(str(a.get("kind", "normal"))))
	var kb := _kind_sort_value(_normalize_kind(str(b.get("kind", "normal"))))
	if ka != kb:
		return ka < kb
	var sa := int(a.get("sort_order", 0))
	var sb := int(b.get("sort_order", 0))
	if sa != sb:
		return sa < sb
	return str(a.get("id", "")) < str(b.get("id", ""))

func _rebuild_list() -> void:
	for child in _monster_list.get_children():
		_monster_list.remove_child(child)
		child.queue_free()

	if _monsters.is_empty():
		var empty := Label.new()
		empty.custom_minimum_size = Vector2(0, 72)
		empty.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
		empty.vertical_alignment = VERTICAL_ALIGNMENT_CENTER
		empty.add_theme_font_size_override("font_size", 24)
		empty.text = "暂无怪物数据"
		_monster_list.add_child(empty)
		return

	for i in range(_monsters.size()):
		var monster: Dictionary = _monsters[i]
		var monster_id := str(monster.get("id", ""))
		var unlocked := MonsterDexModel.is_unlocked(monster_id)
		var panel := PanelContainer.new()
		panel.size_flags_horizontal = Control.SIZE_EXPAND_FILL
		var row := HBoxContainer.new()
		row.custom_minimum_size = Vector2(0, 84)
		row.size_flags_horizontal = Control.SIZE_EXPAND_FILL
		row.add_theme_constant_override("separation", 10)
		panel.add_child(row)

		var icon := ColorRect.new()
		icon.custom_minimum_size = Vector2(44, 44)
		icon.color = _kind_color(str(monster.get("kind", "normal")))
		row.add_child(icon)

		var name_label := Label.new()
		name_label.size_flags_horizontal = Control.SIZE_EXPAND_FILL
		name_label.vertical_alignment = VERTICAL_ALIGNMENT_CENTER
		name_label.add_theme_font_size_override("font_size", 24)
		name_label.text = str(monster.get("name", monster_id)) if unlocked else "？？？"
		row.add_child(name_label)

		var kind_label := Label.new()
		kind_label.custom_minimum_size = Vector2(90, 0)
		kind_label.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
		kind_label.vertical_alignment = VERTICAL_ALIGNMENT_CENTER
		kind_label.add_theme_font_size_override("font_size", 20)
		kind_label.text = _kind_name(str(monster.get("kind", "normal")))
		row.add_child(kind_label)

		var status_label := Label.new()
		status_label.custom_minimum_size = Vector2(110, 0)
		status_label.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
		status_label.vertical_alignment = VERTICAL_ALIGNMENT_CENTER
		status_label.add_theme_font_size_override("font_size", 18)
		status_label.text = "已解锁" if unlocked else "未解锁"
		row.add_child(status_label)

		var btn := Button.new()
		btn.custom_minimum_size = Vector2(86, 44)
		btn.add_theme_font_size_override("font_size", 20)
		btn.text = "查看"
		btn.pressed.connect(_on_select_pressed.bind(i))
		var claim_btn := Button.new()
		claim_btn.custom_minimum_size = Vector2(86, 44)
		claim_btn.add_theme_font_size_override("font_size", 18)
		if MonsterDexModel.can_claim(monster_id):
			claim_btn.disabled = false
			claim_btn.text = "领取"
			claim_btn.pressed.connect(_on_claim_row_pressed.bind(i))
		elif unlocked:
			claim_btn.disabled = true
			claim_btn.text = "已领"
		else:
			claim_btn.disabled = true
			claim_btn.text = "未解锁"
		row.add_child(claim_btn)
		row.add_child(btn)

		_monster_list.add_child(panel)

func _select_monster(idx: int) -> void:
	if idx < 0 or idx >= _monsters.size():
		return
	_selected_idx = idx
	_refresh_detail()

func _refresh_detail() -> void:
	if _selected_idx < 0 or _selected_idx >= _monsters.size():
		_detail_text.text = "请选择怪物"
		_btn_claim.disabled = true
		_btn_claim.text = "未解锁不可领取"
		_btn_claim.visible = true
		return
	var m: Dictionary = _monsters[_selected_idx]
	var monster_id := str(m.get("id", ""))
	var name := str(m.get("name", monster_id))
	var kind := str(m.get("kind", "normal"))
	var unlocked := MonsterDexModel.is_unlocked(monster_id)
	var reward_gold := maxi(0, int(m.get("dex_gold", 5)))
	var got_reward := bool(MonsterDexModel.rewarded.get(monster_id, false))

	var lines: Array[String] = []
	lines.append("%s（%s）" % [name, "已解锁" if unlocked else "未解锁"])
	lines.append("ID：%s" % monster_id)
	lines.append("类型：%s" % _kind_name(kind))
	lines.append("%s：%d" % [I18nService.stat("HP"), int(m.get("hp", 0))])
	lines.append("%s：%d" % [I18nService.stat("ATK"), int(m.get("atk", 0))])
	lines.append("%s：%d" % [I18nService.stat("DEF"), int(m.get("def", 0))])
	lines.append("攻速：%.2f" % float(m.get("attack_interval", 0.0)))
	lines.append("移速：%.0f" % float(m.get("speed", 0.0)))
	lines.append("警戒范围：%.0f" % float(m.get("aggro_range", 0.0)))
	lines.append("攻击范围：%.0f" % float(m.get("attack_range", 0.0)))
	lines.append("经验：%d" % int(m.get("exp", 0)))
	lines.append("掉落加成：%d%%" % int(m.get("drop_bonus_percent", 0)))
	lines.append("解锁状态：%s" % ("已解锁" if unlocked else "未解锁"))
	lines.append("解锁奖励：金币%d（%s）" % [reward_gold, "已领取" if got_reward else "未领取（点击领取）"])
	lines.append("")
	var scenes: Array[String] = SourceGuideService.get_monster_spawn_lines(monster_id, 8)
	if scenes.is_empty():
		lines.append("出现场景：暂无")
	else:
		lines.append("出现场景：")
		var max_show := mini(8, scenes.size())
		for i in range(max_show):
			lines.append("- %s" % scenes[i])
		if scenes.size() > max_show:
			lines.append("...（共%d处）" % scenes.size())
	_detail_text.text = "\n".join(lines)

	if MonsterDexModel.can_claim(monster_id):
		_btn_claim.visible = true
		_btn_claim.disabled = false
		_btn_claim.text = "领取奖励（金币+%d）" % reward_gold
	elif unlocked:
		_btn_claim.visible = true
		_btn_claim.disabled = true
		_btn_claim.text = "奖励已领取"
	else:
		_btn_claim.visible = true
		_btn_claim.disabled = true
		_btn_claim.text = "未解锁不可领取"

func _on_select_pressed(idx: int) -> void:
	_select_monster(idx)

func _on_claim_pressed() -> void:
	if _selected_idx < 0 or _selected_idx >= _monsters.size():
		return
	var monster: Dictionary = _monsters[_selected_idx]
	if MonsterDexModel.claim(monster):
		_on_model_changed()

func _on_claim_row_pressed(idx: int) -> void:
	if idx < 0 or idx >= _monsters.size():
		return
	var monster: Dictionary = _monsters[idx]
	if MonsterDexModel.claim(monster):
		_selected_idx = idx
		_on_model_changed()

func _kind_name(kind: String) -> String:
	match kind:
		"elite":
			return "精英"
		"boss":
			return "Boss"
		_:
			return "普通"

func _kind_color(kind: String) -> Color:
	match kind:
		"elite":
			return Color(0.3, 0.6, 1.0, 1.0)
		"boss":
			return Color(1.0, 0.65, 0.2, 1.0)
		_:
			return Color(0.9, 0.3, 0.3, 1.0)

func _on_model_changed() -> void:
	var selected_id := ""
	if _selected_idx >= 0 and _selected_idx < _monsters.size():
		selected_id = str(_monsters[_selected_idx].get("id", ""))
	SourceGuideService.rebuild_indexes()
	_load_monsters()
	_rebuild_list()
	if selected_id.is_empty():
		_selected_idx = 0 if not _monsters.is_empty() else -1
	else:
		_selected_idx = _find_monster_index(selected_id)
		if _selected_idx < 0 and not _monsters.is_empty():
			_selected_idx = 0
	_refresh_detail()
	_refresh_badges()

func _find_monster_index(monster_id: String) -> int:
	for i in range(_monsters.size()):
		if str(_monsters[i].get("id", "")) == monster_id:
			return i
	return -1

func _on_nav_character_pressed() -> void:
	get_tree().change_scene_to_file(PAGE_CHARACTER)

func _on_back_pressed() -> void:
	get_tree().change_scene_to_file(PAGE_MAP)

func _on_nav_skills_pressed() -> void:
	get_tree().change_scene_to_file(PAGE_SKILLS)

func _on_nav_battle_pressed() -> void:
	get_tree().change_scene_to_file(PAGE_BATTLE)

func _on_nav_bag_pressed() -> void:
	get_tree().change_scene_to_file(PAGE_BATTLE)

func _on_nav_map_pressed() -> void:
	get_tree().change_scene_to_file(PAGE_MAP)

func _refresh_badges() -> void:
	if _badge_char != null and _badge_char.has_method("set_dot"):
		_badge_char.call("set_dot", ProgressModel.free_attr_points > 0)
	if _badge_skill != null and _badge_skill.has_method("set_value"):
		_badge_skill.call("set_value", int(SkillModel.skill_points), false)
