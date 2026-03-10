extends Control

const PAGE_BATTLE := "res://ui/pages/PageBattle.tscn"
const PAGE_CHARACTER := "res://ui/pages/PageCharacter.tscn"
const PAGE_SKILLS := "res://ui/pages/PageSkills.tscn"
const PAGE_MAP := "res://ui/pages/PageMap.tscn"
const PAGE_DUNGEON := "res://ui/pages/PageDungeon.tscn"
const PAGE_MONSTER_DEX := "res://ui/pages/PageMonsterDex.tscn"
const PAGE_ITEM_DEX := "res://ui/pages/PageItemDex.tscn"
const PAGE_EQUIP_DEX := "res://ui/pages/PageEquipDex.tscn"

@onready var _title: Label = $RootVBox/Header/HeaderRow/Title
@onready var _hint: Label = $RootVBox/Header/HeaderRow/Hint
@onready var _btn_back: Button = $RootVBox/Header/HeaderRow/BtnBack

@onready var _lbl_total_progress: Label = $RootVBox/SummaryPanel/SummaryVBox/LblTotalProgress
@onready var _lbl_total_completion: Label = $RootVBox/SummaryPanel/SummaryVBox/LblTotalCompletion
@onready var _lbl_total_pending: Label = $RootVBox/SummaryPanel/SummaryVBox/LblTotalPending

@onready var _lbl_monster_title: Label = $RootVBox/Cards/CardMonster/CardVBox/LblTitle
@onready var _lbl_monster_progress: Label = $RootVBox/Cards/CardMonster/CardVBox/LblProgress
@onready var _lbl_monster_completion: Label = $RootVBox/Cards/CardMonster/CardVBox/LblCompletion
@onready var _lbl_monster_pending: Label = $RootVBox/Cards/CardMonster/CardVBox/LblPending
@onready var _btn_monster_enter: Button = $RootVBox/Cards/CardMonster/CardVBox/BtnEnter
@onready var _badge_monster: Node = $RootVBox/Cards/CardMonster/CardVBox/BtnEnter/Badge

@onready var _lbl_item_title: Label = $RootVBox/Cards/CardItem/CardVBox/LblTitle
@onready var _lbl_item_progress: Label = $RootVBox/Cards/CardItem/CardVBox/LblProgress
@onready var _lbl_item_completion: Label = $RootVBox/Cards/CardItem/CardVBox/LblCompletion
@onready var _lbl_item_pending: Label = $RootVBox/Cards/CardItem/CardVBox/LblPending
@onready var _btn_item_enter: Button = $RootVBox/Cards/CardItem/CardVBox/BtnEnter
@onready var _badge_item: Node = $RootVBox/Cards/CardItem/CardVBox/BtnEnter/Badge

@onready var _lbl_equip_title: Label = $RootVBox/Cards/CardEquip/CardVBox/LblTitle
@onready var _lbl_equip_progress: Label = $RootVBox/Cards/CardEquip/CardVBox/LblProgress
@onready var _lbl_equip_completion: Label = $RootVBox/Cards/CardEquip/CardVBox/LblCompletion
@onready var _lbl_equip_pending: Label = $RootVBox/Cards/CardEquip/CardVBox/LblPending
@onready var _btn_equip_enter: Button = $RootVBox/Cards/CardEquip/CardVBox/BtnEnter
@onready var _badge_equip: Node = $RootVBox/Cards/CardEquip/CardVBox/BtnEnter/Badge

@onready var _btn_nav_character: Button = $RootVBox/BottomNav/BtnNavCharacter
@onready var _btn_nav_skills: Button = $RootVBox/BottomNav/BtnNavSkills
@onready var _btn_nav_battle: Button = $RootVBox/BottomNav/BtnNavBattle
@onready var _btn_nav_bag: Button = $RootVBox/BottomNav/BtnNavBag
@onready var _btn_nav_dex: Button = $RootVBox/BottomNav/BtnNavDex
@onready var _btn_nav_map: Button = $RootVBox/BottomNav/BtnNavMap
@onready var _badge_char: Node = $RootVBox/BottomNav/BtnNavCharacter/Badge
@onready var _badge_skill: Node = $RootVBox/BottomNav/BtnNavSkills/Badge

func _ready() -> void:
	_apply_i18n()
	_connect_signals()
	_refresh_all()
	_refresh_badges()

func _apply_i18n() -> void:
	_title.text = "图鉴"
	_hint.text = "总览与奖励领取进度"
	_btn_back.text = "返回"

	_lbl_monster_title.text = "怪物图鉴"
	_lbl_item_title.text = "材料图鉴"
	_lbl_equip_title.text = "装备图鉴"
	_btn_monster_enter.text = "进入"
	_btn_item_enter.text = "进入"
	_btn_equip_enter.text = "进入"

	_btn_nav_character.text = I18nService.t("ui.nav.character", "人物")
	_btn_nav_skills.text = I18nService.t("ui.nav.skills", "技能")
	_btn_nav_battle.text = I18nService.t("ui.nav.battle", "战斗")
	_btn_nav_bag.text = I18nService.t("ui.nav.bag", "背包")
	_btn_nav_dex.text = I18nService.t("ui.nav.dex", "图鉴")
	_btn_nav_map.text = I18nService.t("ui.nav.dungeon", "副本")
	_btn_nav_dex.disabled = true

func _connect_signals() -> void:
	if not _btn_back.pressed.is_connected(_on_back_pressed):
		_btn_back.pressed.connect(_on_back_pressed)
	if not _btn_monster_enter.pressed.is_connected(_on_open_monster_dex):
		_btn_monster_enter.pressed.connect(_on_open_monster_dex)
	if not _btn_item_enter.pressed.is_connected(_on_open_item_dex):
		_btn_item_enter.pressed.connect(_on_open_item_dex)
	if not _btn_equip_enter.pressed.is_connected(_on_open_equip_dex):
		_btn_equip_enter.pressed.connect(_on_open_equip_dex)

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

	if not EventBus.inventory_updated.is_connected(_on_model_changed):
		EventBus.inventory_updated.connect(_on_model_changed)
	if not DexHubService.summary_changed.is_connected(_on_summary_changed):
		DexHubService.summary_changed.connect(_on_summary_changed)

func _refresh_all() -> void:
	var total: Dictionary = DexHubService.get_total_summary()
	var total_count := int(total.get("total_count", 0))
	var unlocked_count := int(total.get("unlocked_count", 0))
	var pending_count := int(total.get("reward_pending_count", 0))
	var ratio := float(total.get("completion_ratio", 0.0))

	_lbl_total_progress.text = "图鉴进度：%d / %d" % [unlocked_count, total_count]
	_lbl_total_completion.text = "完成度：%.1f%%" % (ratio * 100.0)
	_lbl_total_pending.text = "待领取奖励：%d" % pending_count

	_apply_card(total.get("monster_summary", {}), _lbl_monster_progress, _lbl_monster_completion, _lbl_monster_pending, _badge_monster)
	_apply_card(total.get("item_summary", {}), _lbl_item_progress, _lbl_item_completion, _lbl_item_pending, _badge_item)
	_apply_card(total.get("equip_summary", {}), _lbl_equip_progress, _lbl_equip_completion, _lbl_equip_pending, _badge_equip)

func _apply_card(summary_any: Variant, lbl_progress: Label, lbl_completion: Label, lbl_pending: Label, badge_node: Node) -> void:
	var summary: Dictionary = summary_any if summary_any is Dictionary else {}
	var total_count := int(summary.get("total_count", 0))
	var unlocked_count := int(summary.get("unlocked_count", 0))
	var pending_count := int(summary.get("reward_pending_count", 0))
	var ratio := float(summary.get("completion_ratio", 0.0))

	lbl_progress.text = "%d / %d" % [unlocked_count, total_count]
	lbl_completion.text = "完成度 %.1f%%" % (ratio * 100.0)
	lbl_pending.text = "待领取 %d" % pending_count
	if badge_node != null and badge_node.has_method("set_dot"):
		badge_node.call("set_dot", pending_count > 0)

func _on_model_changed() -> void:
	_refresh_all()
	_refresh_badges()

func _on_summary_changed(_summary: Dictionary) -> void:
	_refresh_all()
	_refresh_badges()

func _on_back_pressed() -> void:
	get_tree().change_scene_to_file(PAGE_MAP)

func _on_open_monster_dex() -> void:
	get_tree().change_scene_to_file(PAGE_MONSTER_DEX)

func _on_open_item_dex() -> void:
	get_tree().change_scene_to_file(PAGE_ITEM_DEX)

func _on_open_equip_dex() -> void:
	get_tree().change_scene_to_file(PAGE_EQUIP_DEX)

func _on_nav_character_pressed() -> void:
	get_tree().change_scene_to_file(PAGE_CHARACTER)

func _on_nav_skills_pressed() -> void:
	get_tree().change_scene_to_file(PAGE_SKILLS)

func _on_nav_battle_pressed() -> void:
	get_tree().change_scene_to_file(PAGE_BATTLE)

func _on_nav_bag_pressed() -> void:
	get_tree().change_scene_to_file(PAGE_BATTLE)

func _on_nav_map_pressed() -> void:
	get_tree().change_scene_to_file(PAGE_DUNGEON)

func _refresh_badges() -> void:
	if _badge_char != null and _badge_char.has_method("set_dot"):
		_badge_char.call("set_dot", ProgressModel.free_attr_points > 0)
	if _badge_skill != null and _badge_skill.has_method("set_value"):
		_badge_skill.call("set_value", int(SkillModel.skill_points), false)
