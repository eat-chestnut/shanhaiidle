extends Control

const PAGE_BATTLE := "res://ui/pages/PageBattle.tscn"
const PAGE_CHARACTER := "res://ui/pages/PageCharacter.tscn"
const PAGE_SKILLS := "res://ui/pages/PageSkills.tscn"
const PAGE_MAP := "res://ui/pages/PageMap.tscn"
const PAGE_DEX_HOME := "res://ui/pages/PageDexHome.tscn"
const PAGE_MONSTER_DEX := "res://ui/pages/PageMonsterDex.tscn"
const PAGE_ITEM_DEX := "res://ui/pages/PageItemDex.tscn"
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
@onready var _opt_slot: OptionButton = $RootVBox/FilterBar/OptSlot
@onready var _opt_set: OptionButton = $RootVBox/FilterBar/OptSet
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
var _set_name_map: Dictionary = {}
var _selected_farm_targets: Array[Dictionary] = []

var _query := ""
var _filter_unlock := "all"
var _filter_rarity := "all"
var _filter_slot := "all"
var _filter_set := "all"
var _sort_mode := "default"

func _ready() -> void:
	_apply_i18n()
	_setup_filters()
	_connect_signals()
	_reload_all("")
	_refresh_badges()

func _apply_i18n() -> void:
	_title.text = "装备图鉴"
	_hint.text = "获得后自动解锁，奖励手动领取"
	_btn_back.text = "返回"
	_btn_tab_monster.text = "怪物"
	_btn_tab_item.text = "材料"
	_btn_tab_equip.text = "装备"
	_btn_tab_equip.disabled = true
	_btn_clear.text = "清除"
	_detail_title.text = "装备详情"
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

	_opt_slot.clear()
	_opt_slot.add_item("全部槽位")
	_opt_slot.add_item("武器")
	_opt_slot.add_item("头盔")
	_opt_slot.add_item("盔甲")
	_opt_slot.add_item("护腿")
	_opt_slot.add_item("鞋子")
	_opt_slot.add_item("披风")
	_opt_slot.add_item("戒指")
	_opt_slot.add_item("手镯")
	_opt_slot.select(0)

	_opt_set.clear()
	_opt_set.add_item("全部套装")
	_opt_set.add_item("无套装")
	_opt_set.select(0)

	_opt_sort.clear()
	_opt_sort.add_item("默认")
	_opt_sort.add_item("稀有度")
	_opt_sort.add_item("名称")
	_opt_sort.add_item("槽位")
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
	if not _btn_tab_item.pressed.is_connected(_on_tab_item_pressed):
		_btn_tab_item.pressed.connect(_on_tab_item_pressed)
	if not _txt_search.text_changed.is_connected(_on_filter_changed):
		_txt_search.text_changed.connect(_on_filter_changed)
	if not _opt_unlock.item_selected.is_connected(_on_filter_changed_idx):
		_opt_unlock.item_selected.connect(_on_filter_changed_idx)
	if not _opt_rarity.item_selected.is_connected(_on_filter_changed_idx):
		_opt_rarity.item_selected.connect(_on_filter_changed_idx)
	if not _opt_slot.item_selected.is_connected(_on_filter_changed_idx):
		_opt_slot.item_selected.connect(_on_filter_changed_idx)
	if not _opt_set.item_selected.is_connected(_on_filter_changed_idx):
		_opt_set.item_selected.connect(_on_filter_changed_idx)
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
	_load_sets()
	_load_templates()
	_rebuild_set_filter_options()
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

func _load_sets() -> void:
	_set_name_map.clear()
	var cfg: Dictionary = ConfigService.get_cfg()
	var db_any = cfg.get("equipment_sets_db", {})
	if not (db_any is Dictionary):
		return
	var sets_any = (db_any as Dictionary).get("equipment_sets", [])
	if not (sets_any is Array):
		return
	for set_any in sets_any:
		if not (set_any is Dictionary):
			continue
		var set_def: Dictionary = set_any
		var set_id := str(set_def.get("id", "")).strip_edges()
		if set_id.is_empty():
			continue
		_set_name_map[set_id] = str(set_def.get("name", set_id))

func _load_templates() -> void:
	_rows.clear()
	var cfg: Dictionary = ConfigService.get_cfg()
	var db_any = cfg.get("equip_db", {})
	if not (db_any is Dictionary):
		return
	var arr_any = (db_any as Dictionary).get("equip_templates", [])
	if not (arr_any is Array):
		return
	var idx := 0
	for tpl_any in arr_any:
		if not (tpl_any is Dictionary):
			continue
		var tpl: Dictionary = tpl_any
		var template_id := str(tpl.get("id", "")).strip_edges()
		if template_id.is_empty():
			continue
		var row := tpl.duplicate(true)
		row["_order"] = idx
		row["_set_name"] = _set_name(str(row.get("set_id", "")))
		_rows.append(row)
		idx += 1

func _rebuild_set_filter_options() -> void:
	var keep := _filter_set
	_opt_set.clear()
	_opt_set.add_item("全部套装")
	_opt_set.add_item("无套装")
	for set_id_any in _set_name_map.keys():
		var set_id := str(set_id_any)
		var idx := _opt_set.get_item_count()
		_opt_set.add_item("套装：" + str(_set_name_map.get(set_id, set_id)))
		_opt_set.set_item_metadata(idx, set_id)
	var target := 0
	if keep == "none":
		target = 1
	elif keep != "all":
		for i in range(2, _opt_set.get_item_count()):
			if str(_opt_set.get_item_metadata(i)) == keep:
				target = i
				break
	_opt_set.select(target)

func _apply_filters() -> void:
	_query = _txt_search.text.strip_edges().to_lower()
	var unlock_modes := ["all", "unlocked", "locked"]
	_filter_unlock = unlock_modes[clampi(_opt_unlock.selected, 0, unlock_modes.size() - 1)]
	var rarity_modes := ["all", "white", "blue", "gold", "purple", "orange"]
	_filter_rarity = rarity_modes[clampi(_opt_rarity.selected, 0, rarity_modes.size() - 1)]
	var slot_modes := ["all", "weapon", "helm", "armor", "pants", "shoes", "cloak", "ring", "bracelet"]
	_filter_slot = slot_modes[clampi(_opt_slot.selected, 0, slot_modes.size() - 1)]
	var sort_modes := ["default", "rarity", "name", "slot", "unlock"]
	_sort_mode = sort_modes[clampi(_opt_sort.selected, 0, sort_modes.size() - 1)]
	if _opt_set.selected <= 0:
		_filter_set = "all"
	elif _opt_set.selected == 1:
		_filter_set = "none"
	else:
		_filter_set = str(_opt_set.get_item_metadata(_opt_set.selected))

	_view_rows.clear()
	for row in _rows:
		var template_id := str(row.get("id", "")).strip_edges()
		if template_id.is_empty():
			continue
		var unlocked := EquipDexModel.is_unlocked(template_id)
		if _filter_unlock == "unlocked" and not unlocked:
			continue
		if _filter_unlock == "locked" and unlocked:
			continue
		var rarity := str(row.get("rarity", "white")).strip_edges().to_lower()
		if _filter_rarity != "all" and rarity != _filter_rarity:
			continue
		var slot := str(row.get("slot", "")).strip_edges().to_lower()
		if _filter_slot != "all" and slot != _filter_slot:
			continue
		var set_id := str(row.get("set_id", "")).strip_edges()
		if _filter_set == "none" and not set_id.is_empty():
			continue
		if _filter_set != "all" and _filter_set != "none" and set_id != _filter_set:
			continue
		if not _query.is_empty():
			var name := str(row.get("name", template_id)).to_lower()
			var id_low := template_id.to_lower()
			if name.find(_query) == -1 and id_low.find(_query) == -1:
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
		"slot":
			_view_rows.sort_custom(func(a: Dictionary, b: Dictionary) -> bool:
				var sa := _slot_sort_value(str(a.get("slot", "")))
				var sb := _slot_sort_value(str(b.get("slot", "")))
				if sa != sb:
					return sa < sb
				return _rarity_rank(str(a.get("rarity", "white"))) > _rarity_rank(str(b.get("rarity", "white")))
			)
		"unlock":
			_view_rows.sort_custom(func(a: Dictionary, b: Dictionary) -> bool:
				var ua := EquipDexModel.is_unlocked(str(a.get("id", "")))
				var ub := EquipDexModel.is_unlocked(str(b.get("id", "")))
				if ua != ub:
					return ua and not ub
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
		empty.text = "暂无装备条目"
		_list.add_child(empty)
		return

	for i in range(_view_rows.size()):
		var row: Dictionary = _view_rows[i]
		var template_id := str(row.get("id", ""))
		var unlocked := EquipDexModel.is_unlocked(template_id)

		var panel := PanelContainer.new()
		panel.size_flags_horizontal = Control.SIZE_EXPAND_FILL
		var line := HBoxContainer.new()
		line.custom_minimum_size = Vector2(0, 90)
		line.size_flags_horizontal = Control.SIZE_EXPAND_FILL
		line.add_theme_constant_override("separation", 8)
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
		name_label.add_theme_font_size_override("font_size", 23)
		name_label.text = str(row.get("name", template_id))
		name_label.modulate = Color(1, 1, 1, 1) if unlocked else Color(0.75, 0.75, 0.75, 1)
		line.add_child(name_label)

		var slot_label := Label.new()
		slot_label.custom_minimum_size = Vector2(100, 0)
		slot_label.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
		slot_label.vertical_alignment = VERTICAL_ALIGNMENT_CENTER
		slot_label.add_theme_font_size_override("font_size", 19)
		slot_label.text = _slot_name(str(row.get("slot", "")))
		line.add_child(slot_label)

		var rarity_label := Label.new()
		rarity_label.custom_minimum_size = Vector2(80, 0)
		rarity_label.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
		rarity_label.vertical_alignment = VERTICAL_ALIGNMENT_CENTER
		rarity_label.add_theme_font_size_override("font_size", 19)
		rarity_label.text = _rarity_name(str(row.get("rarity", "white")))
		line.add_child(rarity_label)

		var status := Label.new()
		status.custom_minimum_size = Vector2(110, 0)
		status.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
		status.vertical_alignment = VERTICAL_ALIGNMENT_CENTER
		status.add_theme_font_size_override("font_size", 18)
		status.text = "已解锁" if unlocked else "未解锁"
		line.add_child(status)

		var claim_btn := Button.new()
		claim_btn.custom_minimum_size = Vector2(86, 44)
		claim_btn.add_theme_font_size_override("font_size", 18)
		if EquipDexModel.can_claim(template_id):
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
		_detail_text.text = "请选择装备模板"
		_btn_claim.disabled = true
		_btn_claim.text = "未解锁不可领取"
		_selected_farm_targets.clear()
		_btn_farm.disabled = true
		return
	var row: Dictionary = _view_rows[_selected_idx]
	var template_id := str(row.get("id", ""))
	var unlocked := EquipDexModel.is_unlocked(template_id)
	var claimed := EquipDexModel.is_reward_claimed(template_id)
	var reward_gold := _reward_gold(row)
	var set_id := str(row.get("set_id", "")).strip_edges()

	var lines: Array[String] = []
	lines.append("%s（%s）" % [str(row.get("name", template_id)), "已解锁" if unlocked else "未解锁"])
	lines.append("模板ID：%s" % template_id)
	lines.append("稀有度：%s" % _rarity_name(str(row.get("rarity", "white"))))
	lines.append("部位：%s" % _slot_name(str(row.get("slot", ""))))
	var white_stats_any = row.get("white_stats", [])
	var growth_any = row.get("star_growth", [])
	var star_enabled := bool(row.get("star_enabled", true))
	var star_cap := maxi(0, int(row.get("star_cap", 10)))
	lines.append("星级：%s（上限 +%d）" % ["可升星" if star_enabled else "不可升星", star_cap])
	lines.append("孔位规则：%s" % _socket_rule_name(str(row.get("socket_rule_ref", ""))))
	var white_stat_lines := _format_stat_rows(white_stats_any)
	if not white_stat_lines.is_empty():
		lines.append("白色基础属性：")
		for line in white_stat_lines:
			lines.append("- %s" % line)
	var growth_lines := _format_stat_rows(growth_any)
	if not growth_lines.is_empty():
		lines.append("每星成长：")
		for line in growth_lines:
			lines.append("- %s" % line)
	lines.append("套装：%s" % ("无" if set_id.is_empty() else _set_name(set_id)))
	lines.append("奖励：金币%d（%s）" % [reward_gold, "已领取" if claimed else "未领取（点击领取）"])
	lines.append("")
	lines.append("推荐刷取")
	var source_lines: Array[String] = SourceGuideService.get_equip_template_lines(template_id, 8)
	if source_lines.is_empty():
		lines.append("暂无推荐刷取信息")
	else:
		for line in source_lines:
			lines.append("- %s" % line)

	lines.append("")
	lines.append("推荐刷图")
	_selected_farm_targets = SourceGuideService.get_equip_farm_targets(template_id, 3)
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

	if EquipDexModel.can_claim(template_id):
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
		MapNavTargetModel.set_target(stage_id, diff_index, "equip_dex")
	get_tree().change_scene_to_file(PAGE_MAP)

func _format_stat_rows(rows_any: Variant) -> Array[String]:
	var out: Array[String] = []
	if not (rows_any is Array):
		return out
	var stats: Dictionary = {}
	for row_any in rows_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		var stat := str(row.get("stat", "")).strip_edges()
		if stat.is_empty():
			continue
		stats[stat] = int(row.get("value", 0))
	var ordered := ["HP", "ATK", "DEF", "CRIT_RATE", "CRIT_DMG", "QI"]
	for stat in ordered:
		if not stats.has(stat):
			continue
		var val := int(stats.get(stat, 0))
		if val == 0:
			continue
		var suffix := "%" if stat == "CRIT_RATE" else ""
		out.append("%s +%d%s" % [I18nService.stat(stat), val, suffix])
	for key_any in stats.keys():
		var stat := str(key_any)
		if ordered.find(stat) != -1:
			continue
		var val := int(stats.get(key_any, 0))
		if val == 0:
			continue
		var suffix := "%" if stat == "CRIT_RATE" else ""
		out.append("%s +%d%s" % [I18nService.stat(stat), val, suffix])
	return out

func _socket_rule_name(rule_ref: String) -> String:
	match rule_ref.strip_edges():
		"fixed_star_3_6_8_10", "":
			return "固定开孔（3/6/8/10星）"
		_:
			return rule_ref

func _find_view_index(template_id: String) -> int:
	for i in range(_view_rows.size()):
		if str(_view_rows[i].get("id", "")) == template_id:
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

func _reward_gold(tpl: Dictionary) -> int:
	if tpl.has("dex_gold"):
		return maxi(0, int(tpl.get("dex_gold", 0)))
	var rarity := str(tpl.get("rarity", "white")).to_lower()
	match rarity:
		"gold":
			return 35
		"blue":
			return 20
		_:
			return 12

func _slot_sort_value(slot: String) -> int:
	match slot.to_lower():
		"weapon":
			return 0
		"helm":
			return 1
		"armor":
			return 2
		"pants":
			return 3
		"shoes":
			return 4
		"cloak":
			return 5
		"ring":
			return 6
		"bracelet":
			return 7
		_:
			return 99

func _slot_name(slot: String) -> String:
	var key := slot.strip_edges().to_lower()
	if key.is_empty():
		return "未知"
	return I18nService.t("slot.%s" % key, key)

func _set_name(set_id: String) -> String:
	var sid := set_id.strip_edges()
	if sid.is_empty():
		return "无"
	return str(_set_name_map.get(sid, sid))

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
	_opt_slot.select(0)
	_opt_set.select(0)
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
	var ret: Dictionary = EquipDexModel.claim_reward(row)
	if bool(ret.get("ok", false)):
		_selected_idx = idx
		_on_model_changed()

func _on_claim_pressed() -> void:
	if _selected_idx < 0 or _selected_idx >= _view_rows.size():
		return
	var row: Dictionary = _view_rows[_selected_idx]
	var ret: Dictionary = EquipDexModel.claim_reward(row)
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

func _on_tab_item_pressed() -> void:
	get_tree().change_scene_to_file(PAGE_ITEM_DEX)

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
