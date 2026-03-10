extends Control

const ATTR_ROWS := [
	{
		"key": "strength",
		"row": "RowStr",
		"name_key": "attr.str",
		"desc": "攻击+1/点；物伤系数+12‰/点",
	},
	{
		"key": "physique",
		"row": "RowVit",
		"name_key": "attr.vit",
		"desc": "生命+1/点；每10点额外+1生命",
	},
	{
		"key": "agility",
		"row": "RowAgi",
		"name_key": "attr.agi",
		"desc": "暴击率每4点≈+1%（显示整数%）",
	},
	{
		"key": "spirit",
		"row": "RowSpi",
		"name_key": "attr.spi",
		"desc": "术伤系数+12‰/点（预留）",
	},
	{
		"key": "true_energy",
		"row": "RowQi",
		"name_key": "attr.qi",
		"desc": "气上限+1/点（预留）",
	},
	{
		"key": "fortune",
		"row": "RowLuck",
		"name_key": "attr.luck",
		"desc": "掉落加成≈+0.6%/点（显示整数%）",
	},
]

@onready var _title: Label = $RootVBox/Header/Title
@onready var _attr_title: Label = $RootVBox/Main/LeftPanel/LeftVBox/AttrEffectTitle
@onready var _stats_title: Label = $RootVBox/Main/RightPanel/RightVBox/StatsTitle
@onready var _stats_text: RichTextLabel = $RootVBox/Main/RightPanel/RightVBox/StatsText
@onready var _btn_nav_character: Button = $RootVBox/BottomNav/BtnNavCharacter
@onready var _btn_nav_skills: Button = $RootVBox/BottomNav/BtnNavSkills
@onready var _btn_nav_battle: Button = $RootVBox/BottomNav/BtnNavBattle
@onready var _btn_nav_bag: Button = $RootVBox/BottomNav/BtnNavBag
@onready var _btn_nav_dex: Button = $RootVBox/BottomNav/BtnNavDex
@onready var _btn_nav_map: Button = $RootVBox/BottomNav/BtnNavMap
@onready var _badge_char: Node = $RootVBox/BottomNav/BtnNavCharacter/Badge
@onready var _badge_skill: Node = $RootVBox/BottomNav/BtnNavSkills/Badge
@onready var _badge_dex: Node = $RootVBox/BottomNav/BtnNavDex/Badge

var _row_nodes: Dictionary = {}

func _ready() -> void:
	_bind_attr_rows()
	_apply_i18n()
	_connect_signals()
	refresh_ui()

func refresh_ui() -> void:
	var attrs: Dictionary = ProgressModel.attrs
	var points: int = ProgressModel.free_attr_points
	for row_cfg in ATTR_ROWS:
		var key := str(row_cfg.get("key", ""))
		var row_any = _row_nodes.get(key)
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		var name_label_any = row.get("name")
		var desc_label_any = row.get("desc")
		var btn_any = row.get("btn")
		if not (name_label_any is Label) or not (desc_label_any is Label) or not (btn_any is Button):
			continue
		var name_label: Label = name_label_any
		var desc_label: Label = desc_label_any
		var btn: Button = btn_any
		var title := I18nService.t(str(row_cfg.get("name_key", key)), key)
		var value := int(attrs.get(key, 0))
		name_label.text = "%s  %d" % [title, value]
		desc_label.text = str(row_cfg.get("desc", ""))
		btn.disabled = points <= 0
		btn.text = "+"

	var stats: Dictionary = EquipmentModel.get_total_stats()
	var lines: Array[String] = []
	lines.append("%s: Lv %d" % [I18nService.t("ui.level", "等级"), ProgressModel.level])
	lines.append("%s: %d/%d" % [
		I18nService.t("ui.exp", "经验"),
		ProgressModel.exp,
		ProgressModel.exp_to_next(ProgressModel.level),
	])
	lines.append("%s: %d" % [I18nService.t("ui.attr_points", "属性点"), points])
	lines.append("")
	lines.append("%s %d  %s %d" % [
		I18nService.stat("HP"),
		int(stats.get("HP", 10)),
		I18nService.stat("QI"),
		int(stats.get("QI", 10))
	])
	lines.append("%s %d  %s %d" % [
		I18nService.stat("ATK"),
		int(stats.get("ATK", 1)),
		I18nService.stat("DEF"),
		int(stats.get("DEF", 0))
	])
	lines.append("%s %d%%  %s %d%%" % [
		I18nService.stat("CRIT_PERCENT"),
		int(stats.get("CRIT_PERCENT", 5)),
		I18nService.stat("LOOT_BONUS_PERCENT"),
		int(stats.get("LOOT_BONUS_PERCENT", 0))
	])
	_stats_text.text = "\n".join(lines)
	_refresh_badges()

func _bind_attr_rows() -> void:
	_row_nodes.clear()
	for row_cfg in ATTR_ROWS:
		var key := str(row_cfg.get("key", ""))
		var row_name := str(row_cfg.get("row", ""))
		var base_path := "RootVBox/Main/LeftPanel/LeftVBox/AttrRows/%s" % row_name
		var row_node = get_node_or_null(base_path)
		if not (row_node is HBoxContainer):
			continue
		var name_label = get_node_or_null("%s/Texts/NameValue" % base_path)
		var desc_label = get_node_or_null("%s/Texts/Desc" % base_path)
		var plus_btn = get_node_or_null("%s/BtnPlus" % base_path)
		if not (name_label is Label) or not (desc_label is Label) or not (plus_btn is Button):
			continue
		var btn: Button = plus_btn
		if not btn.pressed.is_connected(_on_add_attr_pressed.bind(key)):
			btn.pressed.connect(_on_add_attr_pressed.bind(key))
		_row_nodes[key] = {
			"name": name_label,
			"desc": desc_label,
			"btn": plus_btn,
		}

func _apply_i18n() -> void:
	_title.text = I18nService.t("ui.character.title", "人物")
	_attr_title.text = I18nService.t("ui.attr_effect", "属性作用")
	_stats_title.text = I18nService.t("ui.total_stats", "总属性")
	_btn_nav_character.text = I18nService.t("ui.nav.character", "人物")
	_btn_nav_skills.text = I18nService.t("ui.nav.skills", "技能")
	_btn_nav_battle.text = I18nService.t("ui.nav.battle", "战斗")
	_btn_nav_bag.text = I18nService.t("ui.nav.bag", "背包")
	_btn_nav_dex.text = I18nService.t("ui.nav.dex", "图鉴")
	_btn_nav_map.text = I18nService.t("ui.nav.dungeon", "副本")
	_btn_nav_character.disabled = true
	_btn_nav_dex.disabled = false
	_btn_nav_map.disabled = false

func _connect_signals() -> void:
	if not EventBus.inventory_updated.is_connected(_on_model_changed):
		EventBus.inventory_updated.connect(_on_model_changed)
	if not _btn_nav_skills.pressed.is_connected(_on_nav_skills_pressed):
		_btn_nav_skills.pressed.connect(_on_nav_skills_pressed)
	if not _btn_nav_battle.pressed.is_connected(_on_nav_battle_pressed):
		_btn_nav_battle.pressed.connect(_on_nav_battle_pressed)
	if not _btn_nav_dex.pressed.is_connected(_on_nav_dex_pressed):
		_btn_nav_dex.pressed.connect(_on_nav_dex_pressed)
	if not _btn_nav_map.pressed.is_connected(_on_nav_map_pressed):
		_btn_nav_map.pressed.connect(_on_nav_map_pressed)

func _on_model_changed() -> void:
	refresh_ui()

func _on_add_attr_pressed(attr_key: String) -> void:
	if ProgressModel.spend_attr(attr_key):
		refresh_ui()

func _on_nav_skills_pressed() -> void:
	get_tree().change_scene_to_file("res://ui/pages/PageSkills.tscn")

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
