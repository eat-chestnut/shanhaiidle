extends Control

const PAGE_BATTLE := "res://ui/pages/PageBattle.tscn"
const PAGE_CHARACTER := "res://ui/pages/PageCharacter.tscn"
const PAGE_SKILLS := "res://ui/pages/PageSkills.tscn"
const PAGE_MAP := "res://ui/pages/PageMap.tscn"
const PAGE_DEX_HOME := "res://ui/pages/PageDexHome.tscn"
const PAGE_MONSTER_DEX := "res://ui/pages/PageMonsterDex.tscn"
const PAGE_EQUIP_DEX := "res://ui/pages/PageEquipDex.tscn"
const PAGE_DUNGEON := "res://ui/pages/PageDungeon.tscn"

@onready var _title: Label = $RootVBox/Header/HeaderRow/Title
@onready var _hint: Label = $RootVBox/Header/HeaderRow/Hint
@onready var _btn_back: Button = $RootVBox/Header/HeaderRow/BtnBack
@onready var _btn_tab_monster: Button = $RootVBox/DexTabs/BtnTabMonster
@onready var _btn_tab_item: Button = $RootVBox/DexTabs/BtnTabItem
@onready var _btn_tab_equip: Button = $RootVBox/DexTabs/BtnTabEquip
@onready var _txt_search: LineEdit = $RootVBox/FilterBar/TxtSearch
@onready var _opt_unlock: OptionButton = $RootVBox/FilterBar/OptUnlock
@onready var _opt_rarity: OptionButton = $RootVBox/FilterBar/OptRarity
@onready var _opt_type: OptionButton = $RootVBox/FilterBar/OptType
@onready var _opt_sort: OptionButton = $RootVBox/FilterBar/OptSort
@onready var _btn_clear: Button = $RootVBox/FilterBar/BtnClear
@onready var _list: VBoxContainer = $RootVBox/ListScroll/ListContainer
@onready var _detail_title: Label = $RootVBox/DetailPanel/DetailVBox/DetailTitle
@onready var _detail_text: RichTextLabel = $RootVBox/DetailPanel/DetailVBox/DetailScroll/DetailText
@onready var _btn_claim: Button = $RootVBox/DetailPanel/DetailVBox/BtnClaim
@onready var _btn_farm: Button = $RootVBox/DetailPanel/DetailVBox/BtnFarm
@onready var _btn_nav_character: Button = $RootVBox/BottomNav/BtnNavCharacter
@onready var _btn_nav_skills: Button = $RootVBox/BottomNav/BtnNavSkills
@onready var _btn_nav_battle: Button = $RootVBox/BottomNav/BtnNavBattle
@onready var _btn_nav_bag: Button = $RootVBox/BottomNav/BtnNavBag
@onready var _btn_nav_dex: Button = $RootVBox/BottomNav/BtnNavDex
@onready var _btn_nav_map: Button = $RootVBox/BottomNav/BtnNavMap
@onready var _badge_char: Node = $RootVBox/BottomNav/BtnNavCharacter/Badge
@onready var _badge_skill: Node = $RootVBox/BottomNav/BtnNavSkills/Badge

var _rows: Array[Dictionary] = []
var _view_rows: Array[Dictionary] = []
var _selected_idx := -1
var _icon_cache: Dictionary = {}
var _selected_farm_targets: Array[Dictionary] = []

var _query := ""
var _filter_unlock := "all"
var _filter_rarity := "all"
var _filter_type := "all"
var _sort_mode := "default"

func _ready() -> void:
	_apply_i18n()
	_setup_filters()
	_connect_signals()
	_reload_all("")
	_refresh_badges()

func _apply_i18n() -> void:
	_title.text = "材料图鉴"
	_hint.text = "获得后自动解锁，奖励手动领取"
	_btn_back.text = "返回"
	_btn_tab_monster.text = "怪物"
	_btn_tab_item.text = "材料"
	_btn_tab_equip.text = "装备"
	_btn_tab_item.disabled = true
	_btn_clear.text = "清除"
	_detail_title.text = "材料详情"
	_btn_claim.text = "未解锁不可领取"
	_btn_farm.text = "前往刷图"
	_btn_farm.disabled = true
	_btn_nav_character.text = I18nService.t("ui.nav.character", "人物")
	_btn_nav_skills.text = I18nService.t("ui.nav.skills", "技能")
	_btn_nav_battle.text = I18nService.t("ui.nav.battle", "战斗")
	_btn_nav_bag.text = I18nService.t("ui.nav.bag", "背包")
	_btn_nav_dex.text = I18nService.t("ui.nav.dex", "图鉴")
	_btn_nav_map.text = I18nService.t("ui.nav.dungeon", "副本")
	_btn_nav_dex.disabled = true
	_btn_nav_bag.disabled = false
	_btn_nav_character.disabled = false
	_btn_nav_skills.disabled = false
	_btn_nav_battle.disabled = false
	_btn_nav_map.disabled = false

func _setup_filters() -> void:
	_opt_unlock.clear()
	_opt_unlock.add_item("全部")
	_opt_unlock.add_item("已解锁")
	_opt_unlock.add_item("未解锁")
	_opt_unlock.select(0)

	_opt_rarity.clear()
	_opt_rarity.add_item("全部稀有度")
	for rarity in ["white", "blue", "gold", "purple", "orange"]:
		_opt_rarity.add_item(_rarity_name(rarity))
	_opt_rarity.select(0)

	_opt_type.clear()
	_opt_type.add_item("全部类别")
	_opt_type.add_item("材料")
	_opt_type.add_item("宝石")
	_opt_type.select(0)

	_opt_sort.clear()
	_opt_sort.add_item("默认")
	_opt_sort.add_item("稀有度")
	_opt_sort.add_item("名称")
	_opt_sort.add_item("解锁状态")
	_opt_sort.select(0)

	_txt_search.placeholder_text = "搜索（名称/ID）"

func _connect_signals() -> void:
	if not EventBus.inventory_updated.is_connected(_on_model_changed):
		EventBus.inventory_updated.connect(_on_model_changed)
	if not _btn_back.pressed.is_connected(_on_back_pressed):
		_btn_back.pressed.connect(_on_back_pressed)
	if not _btn_tab_monster.pressed.is_connected(_on_tab_monster_pressed):
		_btn_tab_monster.pressed.connect(_on_tab_monster_pressed)
	if not _btn_tab_equip.pressed.is_connected(_on_tab_equip_pressed):
		_btn_tab_equip.pressed.connect(_on_tab_equip_pressed)
	if not _txt_search.text_changed.is_connected(_on_filter_changed):
		_txt_search.text_changed.connect(_on_filter_changed)
	if not _opt_unlock.item_selected.is_connected(_on_filter_changed_idx):
		_opt_unlock.item_selected.connect(_on_filter_changed_idx)
	if not _opt_rarity.item_selected.is_connected(_on_filter_changed_idx):
		_opt_rarity.item_selected.connect(_on_filter_changed_idx)
	if not _opt_type.item_selected.is_connected(_on_filter_changed_idx):
		_opt_type.item_selected.connect(_on_filter_changed_idx)
	if not _opt_sort.item_selected.is_connected(_on_filter_changed_idx):
		_opt_sort.item_selected.connect(_on_filter_changed_idx)
	if not _btn_clear.pressed.is_connected(_on_clear_pressed):
		_btn_clear.pressed.connect(_on_clear_pressed)
	if not _btn_claim.pressed.is_connected(_on_claim_pressed):
		_btn_claim.pressed.connect(_on_claim_pressed)
	if not _btn_farm.pressed.is_connected(_on_farm_pressed):
		_btn_farm.pressed.connect(_on_farm_pressed)
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

func _reload_all(keep_selected_id: String) -> void:
	_load_items()
	_apply_filters()
	_rebuild_list()
	if _view_rows.is_empty():
		_selected_idx = -1
		_refresh_detail()
		return
	if not keep_selected_id.is_empty():
		_selected_idx = _find_view_index(keep_selected_id)
	if _selected_idx < 0 or _selected_idx >= _view_rows.size():
		_selected_idx = 0
	_refresh_detail()

func _load_items() -> void:
	_rows.clear()
	var cfg: Dictionary = ConfigService.get_cfg()
	var db_any = cfg.get("items_db", {})
	if not (db_any is Dictionary):
		return
	var items_any = (db_any as Dictionary).get("items", [])
	if not (items_any is Array):
		return
	var idx := 0
	for item_any in items_any:
		if not (item_any is Dictionary):
			continue
		var item_def: Dictionary = item_any
		var item_id := str(item_def.get("id", "")).strip_edges()
		if item_id.is_empty():
			continue
		var item_type := str(item_def.get("type", "item")).strip_edges().to_lower()
		if item_type == "equip":
			continue
		var row := item_def.duplicate(true)
		row["_order"] = idx
		row["_type"] = item_type
		_rows.append(row)
		idx += 1

func _apply_filters() -> void:
	_query = _txt_search.text.strip_edges().to_lower()
	var unlock_modes := ["all", "unlocked", "locked"]
	_filter_unlock = unlock_modes[clampi(_opt_unlock.selected, 0, unlock_modes.size() - 1)]
	var rarity_modes := ["all", "white", "blue", "gold", "purple", "orange"]
	_filter_rarity = rarity_modes[clampi(_opt_rarity.selected, 0, rarity_modes.size() - 1)]
	var type_modes := ["all", "item", "gem"]
	_filter_type = type_modes[clampi(_opt_type.selected, 0, type_modes.size() - 1)]
	var sort_modes := ["default", "rarity", "name", "unlock"]
	_sort_mode = sort_modes[clampi(_opt_sort.selected, 0, sort_modes.size() - 1)]

	_view_rows.clear()
	for row in _rows:
		var item_id := str(row.get("id", "")).strip_edges()
		if item_id.is_empty():
			continue
		var unlocked := ItemDexModel.is_unlocked(item_id)
		if _filter_unlock == "unlocked" and not unlocked:
			continue
		if _filter_unlock == "locked" and unlocked:
			continue
		var rarity := str(row.get("rarity", "white")).strip_edges().to_lower()
		if _filter_rarity != "all" and rarity != _filter_rarity:
			continue
		var item_type := str(row.get("_type", "item")).strip_edges().to_lower()
		if _filter_type != "all" and item_type != _filter_type:
			continue
		if not _query.is_empty():
			var name := str(row.get("name", item_id)).to_lower()
			var qid := item_id.to_lower()
			if name.find(_query) == -1 and qid.find(_query) == -1:
				continue
		_view_rows.append(row)

	match _sort_mode:
		"rarity":
			_view_rows.sort_custom(func(a: Dictionary, b: Dictionary) -> bool:
				var ra := _rarity_rank(str(a.get("rarity", "white")))
				var rb := _rarity_rank(str(b.get("rarity", "white")))
				if ra != rb:
					return ra > rb
				return str(a.get("name", a.get("id", ""))) < str(b.get("name", b.get("id", "")))
			)
		"name":
			_view_rows.sort_custom(func(a: Dictionary, b: Dictionary) -> bool:
				return str(a.get("name", a.get("id", ""))) < str(b.get("name", b.get("id", "")))
			)
		"unlock":
			_view_rows.sort_custom(func(a: Dictionary, b: Dictionary) -> bool:
				var ua := ItemDexModel.is_unlocked(str(a.get("id", "")))
				var ub := ItemDexModel.is_unlocked(str(b.get("id", "")))
				if ua != ub:
					return ua and not ub
				var ra := _rarity_rank(str(a.get("rarity", "white")))
				var rb := _rarity_rank(str(b.get("rarity", "white")))
				if ra != rb:
					return ra > rb
				return str(a.get("name", a.get("id", ""))) < str(b.get("name", b.get("id", "")))
			)
		_:
			_view_rows.sort_custom(func(a: Dictionary, b: Dictionary) -> bool:
				return int(a.get("_order", 0)) < int(b.get("_order", 0))
			)

func _rebuild_list() -> void:
	for child in _list.get_children():
		_list.remove_child(child)
		child.queue_free()

	if _view_rows.is_empty():
		var empty := Label.new()
		empty.custom_minimum_size = Vector2(0, 72)
		empty.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
		empty.vertical_alignment = VERTICAL_ALIGNMENT_CENTER
		empty.add_theme_font_size_override("font_size", 24)
		empty.text = "暂无材料条目"
		_list.add_child(empty)
		return

	for i in range(_view_rows.size()):
		var row: Dictionary = _view_rows[i]
		var item_id := str(row.get("id", ""))
		var unlocked := ItemDexModel.is_unlocked(item_id)
		var panel := PanelContainer.new()
		panel.size_flags_horizontal = Control.SIZE_EXPAND_FILL
		var line := HBoxContainer.new()
		line.custom_minimum_size = Vector2(0, 84)
		line.size_flags_horizontal = Control.SIZE_EXPAND_FILL
		line.add_theme_constant_override("separation", 10)
		panel.add_child(line)

		var icon := TextureRect.new()
		icon.custom_minimum_size = Vector2(44, 44)
		icon.expand_mode = TextureRect.EXPAND_IGNORE_SIZE
		icon.stretch_mode = TextureRect.STRETCH_KEEP_ASPECT_CENTERED
		icon.texture = _load_icon(str(row.get("icon", "")))
		line.add_child(icon)

		var name_label := Label.new()
		name_label.size_flags_horizontal = Control.SIZE_EXPAND_FILL
		name_label.vertical_alignment = VERTICAL_ALIGNMENT_CENTER
		name_label.add_theme_font_size_override("font_size", 24)
		name_label.text = str(row.get("name", item_id))
		name_label.modulate = Color(1, 1, 1, 1) if unlocked else Color(0.75, 0.75, 0.75, 1)
		line.add_child(name_label)

		var rarity_label := Label.new()
		rarity_label.custom_minimum_size = Vector2(90, 0)
		rarity_label.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
		rarity_label.vertical_alignment = VERTICAL_ALIGNMENT_CENTER
		rarity_label.add_theme_font_size_override("font_size", 20)
		rarity_label.text = _rarity_name(str(row.get("rarity", "white")))
		line.add_child(rarity_label)

		var status := Label.new()
		status.custom_minimum_size = Vector2(120, 0)
		status.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
		status.vertical_alignment = VERTICAL_ALIGNMENT_CENTER
		status.add_theme_font_size_override("font_size", 18)
		status.text = "已解锁" if unlocked else "未解锁"
		line.add_child(status)

		var claim_btn := Button.new()
		claim_btn.custom_minimum_size = Vector2(86, 44)
		claim_btn.add_theme_font_size_override("font_size", 18)
		if ItemDexModel.can_claim(item_id):
			claim_btn.disabled = false
			claim_btn.text = "领取"
			claim_btn.pressed.connect(_on_claim_row_pressed.bind(i))
		elif unlocked:
			claim_btn.disabled = true
			claim_btn.text = "已领"
		else:
			claim_btn.disabled = true
			claim_btn.text = "未解锁"
		line.add_child(claim_btn)

		var btn := Button.new()
		btn.custom_minimum_size = Vector2(86, 44)
		btn.add_theme_font_size_override("font_size", 20)
		btn.text = "查看"
		btn.pressed.connect(_on_select_pressed.bind(i))
		line.add_child(btn)
		_list.add_child(panel)

func _refresh_detail() -> void:
	if _selected_idx < 0 or _selected_idx >= _view_rows.size():
		_detail_text.text = "请选择条目"
		_btn_claim.disabled = true
		_btn_claim.text = "未解锁不可领取"
		_selected_farm_targets.clear()
		_btn_farm.disabled = true
		return
	var row: Dictionary = _view_rows[_selected_idx]
	var item_id := str(row.get("id", ""))
	var name := str(row.get("name", item_id))
	var rarity := str(row.get("rarity", "white"))
	var item_type := str(row.get("_type", "item"))
	var unlocked := ItemDexModel.is_unlocked(item_id)
	var claimed := ItemDexModel.is_reward_claimed(item_id)
	var reward_gold := _item_reward_gold(row)

	var lines: Array[String] = []
	lines.append("%s（%s）" % [name, "已解锁" if unlocked else "未解锁"])
	lines.append("ID：%s" % item_id)
	lines.append("稀有度：%s" % _rarity_name(rarity))
	lines.append("类别：%s" % _item_type_name(item_type))
	var trait_text := str(row.get("trait", "")).strip_edges()
	if trait_text.is_empty():
		trait_text = str(row.get("desc", "")).strip_edges()
	if not trait_text.is_empty():
		lines.append("描述：%s" % trait_text)
	lines.append("奖励：金币%d（%s）" % [reward_gold, "已领取" if claimed else "未领取（点击领取）"])
	lines.append("")
	lines.append("掉落来源")
	var source_lines: Array[String] = SourceGuideService.get_item_drop_lines(item_id, 8)
	if source_lines.is_empty():
		lines.append("暂无掉落来源")
	else:
		for line in source_lines:
			lines.append("- %s" % line)

	lines.append("")
	lines.append("推荐刷图")
	_selected_farm_targets = SourceGuideService.get_item_farm_targets(item_id, 3)
	if _selected_farm_targets.is_empty():
		lines.append("暂无推荐刷图信息")
		_btn_farm.disabled = true
	else:
		for row_any in _selected_farm_targets:
			if not (row_any is Dictionary):
				continue
			var target_row: Dictionary = row_any
			var summary := str(target_row.get("summary_line", "")).strip_edges()
			if summary.is_empty():
				continue
			lines.append("- %s" % summary)
		_btn_farm.disabled = false
	_detail_text.text = "\n".join(lines)

	if ItemDexModel.can_claim(item_id):
		_btn_claim.disabled = false
		_btn_claim.text = "领取奖励（金币+%d）" % reward_gold
	elif unlocked:
		_btn_claim.disabled = true
		_btn_claim.text = "奖励已领取"
	else:
		_btn_claim.disabled = true
		_btn_claim.text = "未解锁不可领取"

func _on_farm_pressed() -> void:
	if _selected_farm_targets.is_empty():
		return
	var row_any = _selected_farm_targets[0]
	if not (row_any is Dictionary):
		return
	var row: Dictionary = row_any
	var stage_id := str(row.get("stage_id", "")).strip_edges()
	if stage_id.is_empty():
		return
	var diff_index := int(row.get("difficulty_index", -1))
	if has_node("/root/MapNavTargetModel"):
		MapNavTargetModel.set_target(stage_id, diff_index, "item_dex")
	get_tree().change_scene_to_file(PAGE_MAP)

func _find_view_index(item_id: String) -> int:
	for i in range(_view_rows.size()):
		if str(_view_rows[i].get("id", "")) == item_id:
			return i
	return -1

func _load_icon(path: String) -> Texture2D:
	if path.is_empty():
		return null
	if _icon_cache.has(path):
		var cached = _icon_cache[path]
		if cached is Texture2D:
			return cached
	var loaded := load(path)
	if loaded is Texture2D:
		_icon_cache[path] = loaded
		return loaded
	return null

func _item_reward_gold(item_def: Dictionary) -> int:
	if item_def.has("dex_gold"):
		return maxi(0, int(item_def.get("dex_gold", 0)))
	var rarity := str(item_def.get("rarity", "white")).to_lower()
	match rarity:
		"gold":
			return 12
		"blue":
			return 8
		_:
			return 5

func _rarity_rank(rarity: String) -> int:
	match rarity.to_lower():
		"orange":
			return 5
		"purple":
			return 4
		"gold":
			return 3
		"blue":
			return 2
		_:
			return 1

func _rarity_name(rarity: String) -> String:
	match rarity.to_lower():
		"orange":
			return "橙色"
		"purple":
			return "紫色"
		"gold":
			return "金色"
		"blue":
			return "蓝色"
		_:
			return "白色"

func _item_type_name(item_type: String) -> String:
	match item_type.to_lower():
		"gem":
			return "宝石"
		"item":
			return "材料"
		_:
			return item_type

func _on_filter_changed(_t: String) -> void:
	var keep_id := ""
	if _selected_idx >= 0 and _selected_idx < _view_rows.size():
		keep_id = str(_view_rows[_selected_idx].get("id", ""))
	_apply_filters()
	_rebuild_list()
	if _view_rows.is_empty():
		_selected_idx = -1
	else:
		_selected_idx = _find_view_index(keep_id) if not keep_id.is_empty() else 0
		if _selected_idx < 0:
			_selected_idx = 0
	_refresh_detail()

func _on_filter_changed_idx(_idx: int) -> void:
	_on_filter_changed("")

func _on_clear_pressed() -> void:
	_txt_search.text = ""
	_opt_unlock.select(0)
	_opt_rarity.select(0)
	_opt_type.select(0)
	_opt_sort.select(0)
	_on_filter_changed("")

func _on_select_pressed(idx: int) -> void:
	if idx < 0 or idx >= _view_rows.size():
		return
	_selected_idx = idx
	_refresh_detail()

func _on_claim_row_pressed(idx: int) -> void:
	if idx < 0 or idx >= _view_rows.size():
		return
	var row: Dictionary = _view_rows[idx]
	var ret: Dictionary = ItemDexModel.claim_reward(row)
	if bool(ret.get("ok", false)):
		_selected_idx = idx
		_on_model_changed()

func _on_claim_pressed() -> void:
	if _selected_idx < 0 or _selected_idx >= _view_rows.size():
		return
	var row: Dictionary = _view_rows[_selected_idx]
	var ret: Dictionary = ItemDexModel.claim_reward(row)
	if bool(ret.get("ok", false)):
		_on_model_changed()

func _on_model_changed() -> void:
	var keep_id := ""
	if _selected_idx >= 0 and _selected_idx < _view_rows.size():
		keep_id = str(_view_rows[_selected_idx].get("id", ""))
	SourceGuideService.rebuild_indexes()
	_reload_all(keep_id)
	_refresh_badges()

func _on_back_pressed() -> void:
	get_tree().change_scene_to_file(PAGE_DEX_HOME)

func _on_tab_monster_pressed() -> void:
	get_tree().change_scene_to_file(PAGE_MONSTER_DEX)

func _on_tab_equip_pressed() -> void:
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
