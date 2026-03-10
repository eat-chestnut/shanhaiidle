extends Control

const PAGE_BATTLE := "res://ui/pages/PageBattle.tscn"
const PAGE_CHARACTER := "res://ui/pages/PageCharacter.tscn"
const PAGE_SKILLS := "res://ui/pages/PageSkills.tscn"
const PAGE_DEX := "res://ui/pages/PageDexHome.tscn"
const CARD_SCENE := preload("res://ui/components/DungeonBannerCard.tscn")

@onready var _title: Label = $RootVBox/Header/Title
@onready var _hint: Label = $RootVBox/Hint
@onready var _btn_tab_elite: Button = $RootVBox/TabBar/BtnTabElite
@onready var _btn_tab_normal: Button = $RootVBox/TabBar/BtnTabNormal
@onready var _btn_tab_event: Button = $RootVBox/TabBar/BtnTabEvent
@onready var _scroll: ScrollContainer = $RootVBox/ListWrap/Scroll
@onready var _list: VBoxContainer = $RootVBox/ListWrap/Scroll/List
@onready var _empty_hint: Label = $RootVBox/ListWrap/LblEmpty

@onready var _btn_nav_character: Button = $RootVBox/BottomNav/BtnNavCharacter
@onready var _btn_nav_skills: Button = $RootVBox/BottomNav/BtnNavSkills
@onready var _btn_nav_battle: Button = $RootVBox/BottomNav/BtnNavBattle
@onready var _btn_nav_bag: Button = $RootVBox/BottomNav/BtnNavBag
@onready var _btn_nav_dex: Button = $RootVBox/BottomNav/BtnNavDex
@onready var _btn_nav_map: Button = $RootVBox/BottomNav/BtnNavMap
@onready var _badge_char: Node = $RootVBox/BottomNav/BtnNavCharacter/Badge
@onready var _badge_skill: Node = $RootVBox/BottomNav/BtnNavSkills/Badge
@onready var _badge_dex: Node = $RootVBox/BottomNav/BtnNavDex/Badge

var _tab := "normal"

func _ready() -> void:
	_apply_i18n()
	_connect_signals()
	_set_tab("normal")
	DungeonDisplayService.refresh()
	_refresh_badges()

func _apply_i18n() -> void:
	_title.text = "副本"
	_hint.text = "选择副本挑战，获取养成材料"
	_btn_tab_elite.text = "精英副本"
	_btn_tab_normal.text = "普通副本"
	_btn_tab_event.text = "活动副本"

	_btn_nav_character.text = I18nService.t("ui.nav.character", "人物")
	_btn_nav_skills.text = I18nService.t("ui.nav.skills", "技能")
	_btn_nav_battle.text = I18nService.t("ui.nav.battle", "战斗")
	_btn_nav_bag.text = I18nService.t("ui.nav.bag", "背包")
	_btn_nav_dex.text = I18nService.t("ui.nav.dex", "图鉴")
	_btn_nav_map.text = I18nService.t("ui.nav.dungeon", "副本")
	_btn_nav_map.disabled = true

func _connect_signals() -> void:
	if not _btn_tab_elite.pressed.is_connected(_on_tab_elite_pressed):
		_btn_tab_elite.pressed.connect(_on_tab_elite_pressed)
	if not _btn_tab_normal.pressed.is_connected(_on_tab_normal_pressed):
		_btn_tab_normal.pressed.connect(_on_tab_normal_pressed)
	if not _btn_tab_event.pressed.is_connected(_on_tab_event_pressed):
		_btn_tab_event.pressed.connect(_on_tab_event_pressed)

	if not _btn_nav_character.pressed.is_connected(_on_nav_character_pressed):
		_btn_nav_character.pressed.connect(_on_nav_character_pressed)
	if not _btn_nav_skills.pressed.is_connected(_on_nav_skills_pressed):
		_btn_nav_skills.pressed.connect(_on_nav_skills_pressed)
	if not _btn_nav_battle.pressed.is_connected(_on_nav_battle_pressed):
		_btn_nav_battle.pressed.connect(_on_nav_battle_pressed)
	if not _btn_nav_bag.pressed.is_connected(_on_nav_bag_pressed):
		_btn_nav_bag.pressed.connect(_on_nav_bag_pressed)
	if not _btn_nav_dex.pressed.is_connected(_on_nav_dex_pressed):
		_btn_nav_dex.pressed.connect(_on_nav_dex_pressed)

	if not EventBus.inventory_updated.is_connected(_on_model_changed):
		EventBus.inventory_updated.connect(_on_model_changed)
	if not DungeonDisplayService.data_changed.is_connected(_on_dungeon_data_changed):
		DungeonDisplayService.data_changed.connect(_on_dungeon_data_changed)

func _set_tab(tab: String) -> void:
	_tab = tab
	_refresh_tab_visual()
	_rebuild_list()

func _refresh_tab_visual() -> void:
	var active_color := Color(1.0, 0.9, 0.35, 1.0)
	var normal_color := Color(0.85, 0.85, 0.85, 1.0)
	_btn_tab_elite.modulate = active_color if _tab == "elite" else normal_color
	_btn_tab_normal.modulate = active_color if _tab == "normal" else normal_color
	_btn_tab_event.modulate = active_color if _tab == "event" else normal_color

func _rebuild_list() -> void:
	for child in _list.get_children():
		_list.remove_child(child)
		child.queue_free()

	var rows := DungeonDisplayService.get_rows(_tab)
	if rows.is_empty():
		_empty_hint.visible = true
		_empty_hint.text = DungeonDisplayService.get_empty_hint(_tab)
		return

	_empty_hint.visible = false
	for row in rows:
		var card_any = CARD_SCENE.instantiate()
		if not (card_any is Control):
			continue
		var card: Control = card_any
		_list.add_child(card)
		if card.has_method("bind_data"):
			card.call("bind_data", row)
		if card.has_signal("challenge_pressed"):
			card.connect("challenge_pressed", _on_card_challenge_pressed)
		if card.has_signal("sweep_pressed"):
			card.connect("sweep_pressed", _on_card_sweep_pressed)
		if card.has_signal("plus_pressed"):
			card.connect("plus_pressed", _on_card_plus_pressed)

func _on_card_challenge_pressed(dungeon_id: String) -> void:
	var ret := DungeonDisplayService.challenge(dungeon_id)
	if not bool(ret.get("ok", false)):
		EventBus.add_log(_challenge_fail_text(ret))
		_rebuild_list()
		return
	EventBus.add_log("进入副本：%s（占位）" % str(ret.get("name", dungeon_id)))
	get_tree().change_scene_to_file(PAGE_BATTLE)

func _on_card_sweep_pressed(dungeon_id: String) -> void:
	var ret := DungeonDisplayService.sweep(dungeon_id)
	if not bool(ret.get("ok", false)):
		EventBus.add_log(_sweep_fail_text(ret))
		_rebuild_list()
		return
	var rewards_any = ret.get("reward_preview", [])
	var rewards: Array = rewards_any if rewards_any is Array else []
	var reward_line := ""
	if not rewards.is_empty():
		reward_line = "（%s）" % "、".join(_take_string_items(rewards, 3))
	EventBus.add_log("扫荡完成：%s%s（占位）" % [str(ret.get("name", dungeon_id)), reward_line])
	_rebuild_list()

func _on_card_plus_pressed(dungeon_id: String) -> void:
	var ret := DungeonDisplayService.plus_action(dungeon_id)
	if not bool(ret.get("ok", false)):
		EventBus.add_log("%s：暂未开放补充次数" % str(ret.get("name", dungeon_id)))

func _challenge_fail_text(ret: Dictionary) -> String:
	var reason := str(ret.get("reason", ""))
	match reason:
		"locked":
			return "副本未开放：%d级解锁" % int(ret.get("unlock_level", 0))
		"no_count":
			return "今日挑战次数不足"
		"not_found":
			return "副本不存在"
		_:
			return "当前无法挑战该副本"

func _sweep_fail_text(ret: Dictionary) -> String:
	var reason := str(ret.get("reason", ""))
	match reason:
		"locked":
			return "副本未开放：%d级解锁" % int(ret.get("unlock_level", 0))
		"need_clear":
			return "需先通关后开启扫荡"
		"no_count":
			return "今日扫荡次数不足"
		"not_found":
			return "副本不存在"
		_:
			return "当前无法扫荡该副本"

func _take_string_items(arr: Array, max_count: int) -> Array[String]:
	var out: Array[String] = []
	var limit := mini(max_count, arr.size())
	for i in range(limit):
		out.append(str(arr[i]))
	if arr.size() > limit:
		out.append("…")
	return out

func _on_tab_elite_pressed() -> void:
	_set_tab("elite")

func _on_tab_normal_pressed() -> void:
	_set_tab("normal")

func _on_tab_event_pressed() -> void:
	_set_tab("event")

func _on_model_changed() -> void:
	_rebuild_list()
	_refresh_badges()

func _on_dungeon_data_changed() -> void:
	_rebuild_list()

func _on_nav_character_pressed() -> void:
	get_tree().change_scene_to_file(PAGE_CHARACTER)

func _on_nav_skills_pressed() -> void:
	get_tree().change_scene_to_file(PAGE_SKILLS)

func _on_nav_battle_pressed() -> void:
	get_tree().change_scene_to_file(PAGE_BATTLE)

func _on_nav_bag_pressed() -> void:
	get_tree().change_scene_to_file(PAGE_BATTLE)

func _on_nav_dex_pressed() -> void:
	get_tree().change_scene_to_file(PAGE_DEX)

func _refresh_badges() -> void:
	if _badge_char != null and _badge_char.has_method("set_dot"):
		_badge_char.call("set_dot", ProgressModel.free_attr_points > 0)
	if _badge_skill != null and _badge_skill.has_method("set_value"):
		_badge_skill.call("set_value", int(SkillModel.skill_points), false)
	if _badge_dex != null and _badge_dex.has_method("set_dot"):
		_badge_dex.call("set_dot", DexHubService.has_pending_rewards())
