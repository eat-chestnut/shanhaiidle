extends Control

signal opened()
signal closed()

const SLOT_COUNT := 100
const GRID_COLUMNS := 5
const GRID_SEPARATION := 10
const GRID_SIDE_PADDING := 8.0
const GRID_CELL_MIN := 96.0
const GRID_CELL_MAX := 128.0
const ITEM_SLOT_SCENE := preload("res://ui/components/ItemSlot.tscn")
const SLOT_KEYS := [
	"weapon",
	"helm",
	"armor",
	"pants",
	"shoes",
	"cloak",
	"ring1",
	"ring2",
	"bracelet1",
	"bracelet2",
]

@onready var _dim_bg: ColorRect = $DimBG
@onready var _title: Label = $Panel/VBox/Header/Title
@onready var _btn_close: BaseButton = $Panel/VBox/Header/BtnClose
@onready var _btn_tab_items: BaseButton = $Panel/VBox/Tabs/TabItems/BtnTabItems
@onready var _btn_tab_equip: BaseButton = $Panel/VBox/Tabs/TabEquip/BtnTabEquip
@onready var _btn_tab_gem: BaseButton = $Panel/VBox/Tabs/TabGem/BtnTabGem
@onready var _btn_bulk_salvage: Button = $Panel/VBox/Tabs/BtnBulkSalvage
@onready var _filter_bar: HBoxContainer = $Panel/VBox/FilterBar
@onready var _opt_rarity: OptionButton = $Panel/VBox/FilterBar/OptRarity
@onready var _opt_state: OptionButton = $Panel/VBox/FilterBar/OptState
@onready var _opt_set: OptionButton = $Panel/VBox/FilterBar/OptSet
@onready var _opt_sort: OptionButton = $Panel/VBox/FilterBar/OptSort
@onready var _txt_search: LineEdit = $Panel/VBox/FilterBar/TxtSearch
@onready var _btn_clear: Button = $Panel/VBox/FilterBar/BtnClear
@onready var _lbl_tab_items: Label = $Panel/VBox/Tabs/TabItems/LblTabItems
@onready var _lbl_tab_equip: Label = $Panel/VBox/Tabs/TabEquip/LblTabEquip
@onready var _lbl_tab_gem: Label = $Panel/VBox/Tabs/TabGem/LblTabGem
@onready var _equip_title: Label = $Panel/VBox/TopSection/EquipPanel/VBox/EquipTitle
@onready var _stats_title: Label = $Panel/VBox/TopSection/StatsPanel/VBox/StatsTitle
@onready var _stats_text: RichTextLabel = $Panel/VBox/TopSection/StatsPanel/VBox/StatsText
@onready var _btn_add_point: Button = $Panel/VBox/TopSection/StatsPanel/VBox/BtnAddPoint
@onready var _detail_title: Label = $Panel/VBox/TopSection/StatsPanel/VBox/DetailTitle
@onready var _detail_text: RichTextLabel = $Panel/VBox/TopSection/StatsPanel/VBox/DetailText
@onready var _gem_title: Label = $Panel/VBox/TopSection/StatsPanel/VBox/GemSlotsTitle
@onready var _gem_row: HBoxContainer = $Panel/VBox/TopSection/StatsPanel/VBox/GemSlotsRow
@onready var _bottom_scroll: ScrollContainer = $Panel/VBox/BottomScroll
@onready var _grid: GridContainer = $Panel/VBox/BottomScroll/BottomGrid
@onready var _equip_popup: Node = $EquipPopup
@onready var _attr_popup: Node = $AttributePopup
@onready var _gem_popup: Node = $GemPopup
@onready var _gem_select_popup: Node = $GemSelectPopup
@onready var _bulk_popup: Node = $BulkSalvagePopup

var _slots: Array[Control] = []
var _slot_rows: Array = []
var _tab := "items"
var _f_rarity := "all"
var _f_state := "all"
var _f_set := "all"
var _f_sort := "default"
var _f_query := ""
var _equip_slot_ui: Dictionary = {}
var _gem_slot_ui: Dictionary = {}
var _tex_cache: Dictionary = {}

func _ready() -> void:
	visible = false
	_ensure_slots()
	_cache_equip_slot_nodes()
	_cache_gem_slot_nodes()
	_apply_i18n()
	_setup_filter_controls()

	if not _btn_close.pressed.is_connected(close):
		_btn_close.pressed.connect(close)
	if not _btn_tab_items.pressed.is_connected(_on_tab_items_pressed):
		_btn_tab_items.pressed.connect(_on_tab_items_pressed)
	if not _btn_tab_equip.pressed.is_connected(_on_tab_equip_pressed):
		_btn_tab_equip.pressed.connect(_on_tab_equip_pressed)
	if not _btn_tab_gem.pressed.is_connected(_on_tab_gem_pressed):
		_btn_tab_gem.pressed.connect(_on_tab_gem_pressed)
	if not _btn_bulk_salvage.pressed.is_connected(_on_bulk_salvage_pressed):
		_btn_bulk_salvage.pressed.connect(_on_bulk_salvage_pressed)
	if not _opt_rarity.item_selected.is_connected(_on_filter_changed):
		_opt_rarity.item_selected.connect(_on_filter_changed)
	if not _opt_state.item_selected.is_connected(_on_filter_changed):
		_opt_state.item_selected.connect(_on_filter_changed)
	if not _opt_set.item_selected.is_connected(_on_filter_changed):
		_opt_set.item_selected.connect(_on_filter_changed)
	if not _opt_sort.item_selected.is_connected(_on_filter_changed):
		_opt_sort.item_selected.connect(_on_filter_changed)
	if not _txt_search.text_changed.is_connected(_on_search_changed):
		_txt_search.text_changed.connect(_on_search_changed)
	if not _btn_clear.pressed.is_connected(_on_filter_clear):
		_btn_clear.pressed.connect(_on_filter_clear)
	if not _btn_add_point.pressed.is_connected(_on_add_point_pressed):
		_btn_add_point.pressed.connect(_on_add_point_pressed)
	if not _dim_bg.gui_input.is_connected(_on_dim_bg_gui_input):
		_dim_bg.gui_input.connect(_on_dim_bg_gui_input)
	if not EventBus.inventory_updated.is_connected(_on_inventory_updated):
		EventBus.inventory_updated.connect(_on_inventory_updated)
	if not resized.is_connected(_on_layout_resized):
		resized.connect(_on_layout_resized)
	if not _bottom_scroll.resized.is_connected(_on_layout_resized):
		_bottom_scroll.resized.connect(_on_layout_resized)

	_refresh_tab_visual()
	_refresh_stats_panel()
	_set_detail_tip()
	_refresh_equip_slots()
	_refresh_gem_slots()
	_update_cell_size()

func open() -> void:
	if visible:
		refresh()
		return
	visible = true
	opened.emit()
	refresh()

func close() -> void:
	if not visible:
		return
	visible = false
	if _equip_popup != null and _equip_popup.has_method("close"):
		_equip_popup.call("close")
	if _attr_popup != null and _attr_popup.has_method("close"):
		_attr_popup.call("close")
	if _gem_popup != null and _gem_popup.has_method("close"):
		_gem_popup.call("close")
	if _gem_select_popup != null and _gem_select_popup.has_method("close"):
		_gem_select_popup.call("close")
	if _bulk_popup != null and _bulk_popup.has_method("close"):
		_bulk_popup.call("close")
	closed.emit()

func refresh() -> void:
	var raw_rows: Array = []
	if _tab == "equip":
		raw_rows = EquipmentModel.list_bag_sorted()
	elif _tab == "gem":
		raw_rows = InventoryModel.list_items_sorted("gem")
	else:
		raw_rows = InventoryModel.list_items_sorted("item")
	var rows := _apply_filters_and_sort(raw_rows)

	_slot_rows.clear()
	_slot_rows.resize(_slots.size())
	for i in range(_slots.size()):
		var slot: Control = _slots[i]
		if i < rows.size() and rows[i] is Dictionary:
			var row: Dictionary = rows[i]
			var item_id := str(row.get("id", row.get("template_id", "")))
			var item_name := str(row.get("name", item_id))
			var rarity := str(row.get("rarity", "white"))
			var count := int(row.get("count", 1))
			var icon_path := str(row.get("icon", ""))
			var unidentified := false
			if _tab == "equip":
				unidentified = not bool(row.get("identified", true))
				if unidentified:
					var base_slot := str(row.get("slot", ""))
					var slot_name := I18nService.t("slot.%s" % base_slot, base_slot)
					item_name = "未鉴定%s" % slot_name
			slot.call("set_item", item_id, item_name, rarity, count, icon_path)
			if slot.has_method("set_locked"):
				if _tab == "equip":
					slot.call("set_locked", bool(row.get("locked", false)))
				else:
					slot.call("set_locked", false)
			if slot.has_method("set_score_text"):
				if _tab == "equip":
					if unidentified:
						slot.call("set_score_text", "??")
					else:
						slot.call("set_score_text", str(EquipmentModel.calc_score(row)))
				else:
					slot.call("set_score_text", "")
			if slot.has_method("set_tag"):
				if _tab == "equip" and unidentified:
					slot.call("set_tag", "未鉴定")
				else:
					slot.call("clear_tag")
			_slot_rows[i] = row
		else:
			slot.call("clear")
			if slot.has_method("set_locked"):
				slot.call("set_locked", false)
			if slot.has_method("set_score_text"):
				slot.call("set_score_text", "")
			if slot.has_method("clear_tag"):
				slot.call("clear_tag")
			_slot_rows[i] = {}

	_refresh_equip_slots()
	_refresh_gem_slots()
	_refresh_stats_panel()
	_refresh_tab_visual()
	_set_detail_tip()
	_update_cell_size()

func _ensure_slots() -> void:
	for child in _grid.get_children():
		_grid.remove_child(child)
		child.queue_free()

	_slots.clear()
	for i in range(SLOT_COUNT):
		var slot_any = ITEM_SLOT_SCENE.instantiate()
		if not (slot_any is Control):
			continue
		var slot: Control = slot_any
		_grid.add_child(slot)
		_slots.append(slot)
		slot.mouse_filter = Control.MOUSE_FILTER_STOP
		if not slot.gui_input.is_connected(_on_slot_gui_input.bind(i)):
			slot.gui_input.connect(_on_slot_gui_input.bind(i))

func _cache_equip_slot_nodes() -> void:
	_equip_slot_ui.clear()
	for slot_key in SLOT_KEYS:
		var btn_path := "Panel/VBox/TopSection/EquipPanel/VBox/EquipGrid/Slot_%s" % slot_key
		var btn_any = get_node_or_null(btn_path)
		if not (btn_any is BaseButton):
			continue
		var btn: BaseButton = btn_any
		if not btn.pressed.is_connected(_on_equip_slot_pressed.bind(slot_key)):
			btn.pressed.connect(_on_equip_slot_pressed.bind(slot_key))
		if btn is TextureButton:
			var tex_btn: TextureButton = btn
			tex_btn.texture_normal = null
			tex_btn.texture_hover = null
			tex_btn.texture_pressed = null
			tex_btn.texture_disabled = null

		var bg := _ensure_slot_bg(btn)
		var icon := _ensure_slot_icon(btn)
		var text_label := _ensure_slot_text(btn)
		_equip_slot_ui[slot_key] = {
			"button": btn,
			"bg": bg,
			"icon": icon,
			"text": text_label,
		}

func _cache_gem_slot_nodes() -> void:
	_gem_slot_ui.clear()
	for i in range(4):
		var btn_any = _gem_row.get_node_or_null("GemSlot_%d" % i)
		if not (btn_any is BaseButton):
			continue
		var btn: BaseButton = btn_any
		if not btn.pressed.is_connected(_on_gem_slot_pressed.bind(i)):
			btn.pressed.connect(_on_gem_slot_pressed.bind(i))
		if btn is TextureButton:
			var tex_btn: TextureButton = btn
			tex_btn.texture_normal = null
			tex_btn.texture_hover = null
			tex_btn.texture_pressed = null
			tex_btn.texture_disabled = null
		var bg := _ensure_slot_bg(btn)
		var icon := _ensure_slot_icon(btn)
		_gem_slot_ui[i] = {
			"button": btn,
			"bg": bg,
			"icon": icon,
		}

func _ensure_slot_bg(btn: BaseButton) -> TextureRect:
	var bg_any = btn.get_node_or_null("Bg")
	if bg_any is TextureRect:
		return bg_any
	var bg := TextureRect.new()
	bg.name = "Bg"
	bg.layout_mode = 1
	bg.anchor_right = 1.0
	bg.anchor_bottom = 1.0
	bg.grow_horizontal = Control.GROW_DIRECTION_BOTH
	bg.grow_vertical = Control.GROW_DIRECTION_BOTH
	bg.mouse_filter = Control.MOUSE_FILTER_IGNORE
	bg.expand_mode = TextureRect.EXPAND_IGNORE_SIZE
	bg.stretch_mode = TextureRect.STRETCH_SCALE
	btn.add_child(bg)
	btn.move_child(bg, 0)
	return bg

func _ensure_slot_icon(btn: BaseButton) -> TextureRect:
	var icon_any = btn.get_node_or_null("Icon")
	if icon_any is TextureRect:
		return icon_any
	var icon := TextureRect.new()
	icon.name = "Icon"
	icon.layout_mode = 1
	icon.anchor_left = 0.5
	icon.anchor_top = 0.5
	icon.anchor_right = 0.5
	icon.anchor_bottom = 0.5
	icon.offset_left = -28.0
	icon.offset_top = -28.0
	icon.offset_right = 28.0
	icon.offset_bottom = 28.0
	icon.grow_horizontal = Control.GROW_DIRECTION_BOTH
	icon.grow_vertical = Control.GROW_DIRECTION_BOTH
	icon.mouse_filter = Control.MOUSE_FILTER_IGNORE
	icon.expand_mode = TextureRect.EXPAND_IGNORE_SIZE
	icon.stretch_mode = TextureRect.STRETCH_KEEP_ASPECT_CENTERED
	btn.add_child(icon)
	btn.move_child(icon, 1)
	return icon

func _ensure_slot_text(btn: BaseButton) -> Label:
	var text_any = btn.get_node_or_null("Text")
	if text_any is Label:
		var existing: Label = text_any
		existing.mouse_filter = Control.MOUSE_FILTER_IGNORE
		existing.z_index = 2
		return existing
	var label := Label.new()
	label.name = "Text"
	label.layout_mode = 1
	label.anchors_preset = PRESET_FULL_RECT
	label.anchor_right = 1.0
	label.anchor_bottom = 1.0
	label.grow_horizontal = Control.GROW_DIRECTION_BOTH
	label.grow_vertical = Control.GROW_DIRECTION_BOTH
	label.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	label.vertical_alignment = VERTICAL_ALIGNMENT_CENTER
	label.mouse_filter = Control.MOUSE_FILTER_IGNORE
	label.z_index = 2
	btn.add_child(label)
	return label

func _apply_i18n() -> void:
	_title.text = I18nService.t("ui.bag.title")
	_btn_close.tooltip_text = I18nService.t("ui.btn.close")
	_lbl_tab_items.text = I18nService.t("ui.tab.items")
	_lbl_tab_equip.text = I18nService.t("ui.tab.equip")
	_lbl_tab_gem.text = I18nService.t("ui.tab.gem", "宝石")
	_btn_bulk_salvage.text = I18nService.t("ui.btn.bulk_salvage", "一键分解")
	_btn_clear.text = "清除"
	_equip_title.text = I18nService.t("ui.panel.equip_slots", "装备栏")
	_stats_title.text = I18nService.t("ui.panel.stats", "属性")
	_btn_add_point.text = I18nService.t("ui.btn.add_point", "加点")
	_detail_title.text = I18nService.t("ui.panel.detail", "详情")
	_gem_title.text = I18nService.t("ui.tab.gem", "宝石") + "槽"

func _setup_filter_controls() -> void:
	_opt_rarity.clear()
	_opt_rarity.add_item("全部")
	_opt_rarity.add_item("白")
	_opt_rarity.add_item("蓝")
	_opt_rarity.add_item("金")
	_opt_rarity.select(0)

	_opt_state.clear()
	_opt_state.add_item("全部")
	_opt_state.add_item("已鉴定")
	_opt_state.add_item("未鉴定")
	_opt_state.add_item("已锁定")
	_opt_state.select(0)

	_opt_sort.clear()
	_opt_sort.add_item("默认")
	_opt_sort.add_item("稀有度↓")
	_opt_sort.add_item("主属性↓")
	_opt_sort.add_item("孔位↓")
	_opt_sort.add_item("评分↓")
	_opt_sort.select(0)

	_rebuild_set_options()
	_sync_filter_state_from_ui()

func _rebuild_set_options() -> void:
	var keep_set := _f_set
	_opt_set.clear()
	_opt_set.add_item("套装：全部")
	_opt_set.add_item("套装：无套装")

	var cfg := ConfigService.get_cfg()
	var db_any = cfg.get("equipment_sets_db", {})
	var sets_any: Variant = []
	if db_any is Dictionary:
		sets_any = (db_any as Dictionary).get("equipment_sets", [])
	if sets_any is Array:
		for s_any in (sets_any as Array):
			if not (s_any is Dictionary):
				continue
			var s: Dictionary = s_any
			var sid := str(s.get("id", "")).strip_edges()
			if sid.is_empty():
				continue
			var name := str(s.get("name", sid))
			var idx := _opt_set.get_item_count()
			_opt_set.add_item("套装：" + name)
			_opt_set.set_item_metadata(idx, sid)

	var target_idx := 0
	if keep_set == "none":
		target_idx = 1
	elif keep_set != "all":
		for i in range(2, _opt_set.get_item_count()):
			if str(_opt_set.get_item_metadata(i)) == keep_set:
				target_idx = i
				break
	_opt_set.select(target_idx)
	if target_idx == 0:
		_f_set = "all"
	elif target_idx == 1:
		_f_set = "none"
	else:
		_f_set = str(_opt_set.get_item_metadata(target_idx))

func _sync_filter_state_from_ui() -> void:
	var rarity_map := ["all", "white", "blue", "gold"]
	var state_map := ["all", "identified", "unidentified", "locked"]
	var sort_map := ["default", "rarity_desc", "main_desc", "sockets_desc", "score_desc"]
	var rarity_idx := clampi(_opt_rarity.selected, 0, rarity_map.size() - 1)
	var state_idx := clampi(_opt_state.selected, 0, state_map.size() - 1)
	var sort_idx := clampi(_opt_sort.selected, 0, sort_map.size() - 1)
	_f_rarity = rarity_map[rarity_idx]
	_f_state = state_map[state_idx]
	_f_sort = sort_map[sort_idx]

	var set_idx := _opt_set.selected
	if set_idx <= 0:
		_f_set = "all"
	elif set_idx == 1:
		_f_set = "none"
	else:
		_f_set = str(_opt_set.get_item_metadata(set_idx))

func _sanitize_filters_for_tab() -> void:
	if _tab == "equip":
		_rebuild_set_options()
		return

	_f_set = "all"
	if _opt_set.get_item_count() > 0:
		_opt_set.select(0)

	if _f_state != "all":
		_f_state = "all"
		_opt_state.select(0)

func _apply_filters_and_sort(raw_rows: Array) -> Array:
	var out: Array = []
	for row_any in raw_rows:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any

		if _f_rarity != "all":
			if str(row.get("rarity", "white")) != _f_rarity:
				continue

		if _f_state != "all":
			if _tab == "equip":
				var identified := bool(row.get("identified", true))
				var locked := bool(row.get("locked", false))
				if _f_state == "identified" and not identified:
					continue
				if _f_state == "unidentified" and identified:
					continue
				if _f_state == "locked" and not locked:
					continue
			else:
				continue

		if _tab == "equip" and _f_set != "all":
			var sid := str(row.get("set_id", "")).strip_edges()
			if _f_set == "none":
				if not sid.is_empty():
					continue
			elif sid != _f_set:
				continue

		if not _f_query.is_empty():
			var q := _f_query.to_lower()
			var nm := str(row.get("name", "")).to_lower()
			var item_id := str(row.get("id", row.get("template_id", ""))).to_lower()
			if nm.find(q) == -1 and item_id.find(q) == -1:
				continue

		out.append(row)

	match _f_sort:
		"rarity_desc":
			out.sort_custom(func(a, b) -> bool:
				var ra := _rarity_rank(str(a.get("rarity", "white")))
				var rb := _rarity_rank(str(b.get("rarity", "white")))
				if ra != rb:
					return ra > rb
				return str(a.get("name", a.get("id", ""))) < str(b.get("name", b.get("id", "")))
			)
		"main_desc":
			out.sort_custom(func(a, b) -> bool:
				var va := int(a.get("main_val", 0))
				var vb := int(b.get("main_val", 0))
				if va != vb:
					return va > vb
				return _rarity_rank(str(a.get("rarity", "white"))) > _rarity_rank(str(b.get("rarity", "white")))
			)
		"sockets_desc":
			out.sort_custom(func(a, b) -> bool:
				var va := int(a.get("sockets", a.get("socket_count", 0)))
				var vb := int(b.get("sockets", b.get("socket_count", 0)))
				if va != vb:
					return va > vb
				return _rarity_rank(str(a.get("rarity", "white"))) > _rarity_rank(str(b.get("rarity", "white")))
			)
		"score_desc":
			if _tab == "equip":
				out.sort_custom(func(a, b) -> bool:
					var sa := EquipmentModel.calc_score(a)
					var sb := EquipmentModel.calc_score(b)
					if sa != sb:
						return sa > sb
					return _rarity_rank(str(a.get("rarity", "white"))) > _rarity_rank(str(b.get("rarity", "white")))
				)
		_:
			pass
	return out

func _set_detail_tip() -> void:
	_detail_text.text = I18nService.t(
		"ui.tip.select_slot_and_equip",
		"点击装备栏槽位或下方装备查看详情"
	)

func _show_item_detail(row: Dictionary) -> void:
	var item_id := str(row.get("id", "")).strip_edges()
	var item_name := str(row.get("name", item_id)).strip_edges()
	var rarity := str(row.get("rarity", "white")).strip_edges().to_lower()
	var count := maxi(0, int(row.get("count", 0)))
	var item_type := str(row.get("type", "item")).strip_edges()

	var lines: Array[String] = []
	lines.append(item_name if not item_name.is_empty() else "未命名物品")
	if not item_id.is_empty():
		lines.append("ID：%s" % item_id)
	lines.append("稀有度：%s" % _rarity_name_cn(rarity))
	if not item_type.is_empty():
		lines.append("类型：%s" % item_type)
	lines.append("数量：%d" % count)
	lines.append("")
	lines.append("获取途径")

	var source_lines: Array[String] = []
	if has_node("/root/SourceGuideService"):
		source_lines = SourceGuideService.get_item_drop_lines(item_id, 8)
	if source_lines.is_empty():
		lines.append("暂无掉落来源")
	else:
		for line in source_lines:
			lines.append("• %s" % line)
	_detail_text.text = "\n".join(lines)

func _refresh_tab_visual() -> void:
	var active := Color(1.0, 1.0, 1.0, 1.0)
	var inactive := Color(0.68, 0.68, 0.68, 1.0)
	_lbl_tab_items.modulate = active if _tab == "items" else inactive
	_lbl_tab_equip.modulate = active if _tab == "equip" else inactive
	_lbl_tab_gem.modulate = active if _tab == "gem" else inactive
	_filter_bar.visible = true
	_opt_set.visible = _tab == "equip"
	_btn_bulk_salvage.visible = _tab == "equip"

func _refresh_stats_panel() -> void:
	var stats: Dictionary = EquipmentModel.get_total_stats()
	var lv: int = ProgressModel.level
	var exp_now: int = ProgressModel.exp
	var exp_need: int = ProgressModel.exp_to_next(lv)
	var attrs: Dictionary = ProgressModel.attrs
	var lines: Array[String] = []
	lines.append("%s：Lv %d" % [I18nService.t("ui.level", "等级"), lv])
	lines.append("%s：%d/%d" % [I18nService.t("ui.exp", "经验"), exp_now, exp_need])
	lines.append("%s：%d" % [I18nService.t("ui.attr_points", "属性点"), ProgressModel.free_attr_points])
	lines.append("")
	lines.append("%s：%d" % [I18nService.t("attr.strength", "力道"), int(attrs.get("strength", 0))])
	lines.append("%s：%d" % [I18nService.t("attr.physique", "体魄"), int(attrs.get("physique", 0))])
	lines.append("%s：%d" % [I18nService.t("attr.agility", "身法"), int(attrs.get("agility", 0))])
	lines.append("%s：%d" % [I18nService.t("attr.spirit", "神识"), int(attrs.get("spirit", 0))])
	lines.append("%s：%d" % [I18nService.t("attr.true_energy", "真元"), int(attrs.get("true_energy", 0))])
	lines.append("%s：%d" % [I18nService.t("attr.fortune", "运势"), int(attrs.get("fortune", 0))])
	lines.append("")
	lines.append("%s" % I18nService.t("ui.panel.stats_total", "总属性"))
	lines.append("%s %s" % [I18nService.stat("HP"), _format_stat_value(stats.get("HP", 0))])
	lines.append("%s %s" % [I18nService.stat("ATK"), _format_stat_value(stats.get("ATK", 0))])
	lines.append("%s %s" % [I18nService.stat("DEF"), _format_stat_value(stats.get("DEF", 0))])
	lines.append("%s %s%%" % [I18nService.stat("CRIT_PERCENT"), _format_stat_value(stats.get("CRIT_PERCENT", stats.get("CRIT", 0)))])
	lines.append("%s %s" % [I18nService.stat("QI"), _format_stat_value(stats.get("QI", 10))])
	lines.append("%s %s%%" % [
		I18nService.stat("LOOT_BONUS_PERCENT"),
		_format_stat_value(stats.get("LOOT_BONUS_PERCENT", stats.get("DROP", 0))),
	])

	lines.append("")
	lines.append("套装效果")
	var set_info: Dictionary = EquipmentModel.get_active_set_bonuses()
	var set_lines_any: Variant = set_info.get("lines", [])
	var set_lines: Array = set_lines_any if set_lines_any is Array else []
	if not set_lines.is_empty():
		var max_show := mini(6, set_lines.size())
		for i in range(max_show):
			lines.append(str(set_lines[i]))
		if set_lines.size() > max_show:
			lines.append("...（共%d条）" % set_lines.size())
	else:
		lines.append("暂无")
	_stats_text.text = "\n".join(lines)

func _refresh_equip_slots() -> void:
	var cfg: Dictionary = ConfigService.get_cfg()
	var ui_frames_any = cfg.get("ui_frames", {})
	var ui_frames: Dictionary = ui_frames_any if ui_frames_any is Dictionary else {}

	for slot_key in SLOT_KEYS:
		var ui_any = _equip_slot_ui.get(slot_key)
		if not (ui_any is Dictionary):
			continue
		var ui: Dictionary = ui_any
		var btn_any = ui.get("button")
		var bg_any = ui.get("bg")
		var icon_any = ui.get("icon")
		var text_any = ui.get("text")
		if not (btn_any is BaseButton):
			continue
		if not (bg_any is TextureRect):
			continue
		if not (icon_any is TextureRect):
			continue
		if not (text_any is Label):
			continue

		var btn: BaseButton = btn_any
		var bg: TextureRect = bg_any
		var icon: TextureRect = icon_any
		var text_label: Label = text_any
		var slot_name := I18nService.t("slot.%s" % slot_key, slot_key)
		var inst: Dictionary = EquipmentModel.get_equipped_instance(slot_key)
		if inst.is_empty():
			text_label.text = "%s\n—" % slot_name
			text_label.add_theme_color_override("font_color", Color(0.85, 0.85, 0.85, 0.95))
			bg.texture = _load_tex(str(ui_frames.get("empty", "")))
			icon.texture = null
			icon.visible = false
		else:
			var rarity := str(inst.get("rarity", "white"))
			var equip_name := str(inst.get("name", "—"))
			text_label.text = "%s\n%s" % [slot_name, equip_name]
			text_label.add_theme_color_override("font_color", Color(1.0, 1.0, 1.0, 1.0))
			var frame_path := str(ui_frames.get(rarity, ui_frames.get("white", "")))
			bg.texture = _load_tex(frame_path)
			icon.texture = _load_tex(str(inst.get("icon", "")))
			icon.visible = icon.texture != null
		_layout_slot_icon(btn, icon)

func _refresh_gem_slots() -> void:
	var cfg: Dictionary = ConfigService.get_cfg()
	var ui_frames_any = cfg.get("ui_frames", {})
	var ui_frames: Dictionary = ui_frames_any if ui_frames_any is Dictionary else {}
	var item_defs := _build_items_def_map()
	var slots := GemSlotModel.get_slots()

	for i in range(4):
		var ui_any = _gem_slot_ui.get(i)
		if not (ui_any is Dictionary):
			continue
		var ui: Dictionary = ui_any
		var btn_any = ui.get("button")
		var bg_any = ui.get("bg")
		var icon_any = ui.get("icon")
		if not (btn_any is BaseButton) or not (bg_any is TextureRect) or not (icon_any is TextureRect):
			continue
		var btn: BaseButton = btn_any
		var bg: TextureRect = bg_any
		var icon: TextureRect = icon_any
		var gem_id := ""
		if i < slots.size():
			gem_id = str(slots[i])
		if gem_id.is_empty():
			bg.texture = _load_tex(str(ui_frames.get("empty", "")))
			icon.texture = null
			icon.visible = false
		else:
			var item_def: Dictionary = item_defs.get(gem_id, {})
			var rarity := str(item_def.get("rarity", "white"))
			var frame_path := str(ui_frames.get(rarity, ui_frames.get("white", "")))
			bg.texture = _load_tex(frame_path)
			icon.texture = _load_tex(str(item_def.get("icon", "")))
			icon.visible = icon.texture != null
		_layout_slot_icon(btn, icon)

func _layout_slot_icon(btn: BaseButton, icon: TextureRect) -> void:
	var side := minf(btn.size.x, btn.size.y)
	var icon_side := int(clampf(side * 0.58, 42.0, 72.0))
	icon.custom_minimum_size = Vector2(icon_side, icon_side)
	icon.offset_left = -icon_side * 0.5
	icon.offset_top = -icon_side * 0.5
	icon.offset_right = icon_side * 0.5
	icon.offset_bottom = icon_side * 0.5

func _update_cell_size() -> void:
	if _slots.is_empty():
		return
	var usable_w := _bottom_scroll.size.x - GRID_SIDE_PADDING * 2.0 - GRID_SEPARATION * (GRID_COLUMNS - 1)
	if usable_w <= 0.0:
		return
	var cell: float = floor(usable_w / GRID_COLUMNS)
	cell = clampf(cell, GRID_CELL_MIN, GRID_CELL_MAX)
	var cell_size := Vector2(cell, cell)
	for slot in _slots:
		slot.custom_minimum_size = cell_size

func _load_tex(path: String) -> Texture2D:
	if path.is_empty():
		return null
	if _tex_cache.has(path):
		var cached = _tex_cache[path]
		if cached is Texture2D:
			return cached
	var loaded := load(path)
	if loaded is Texture2D:
		var tex: Texture2D = loaded
		_tex_cache[path] = tex
		return tex
	return null

func _build_items_def_map() -> Dictionary:
	var defs: Dictionary = {}
	var cfg: Dictionary = ConfigService.get_cfg()
	var items_db_any = cfg.get("items_db", {})
	if not (items_db_any is Dictionary):
		return defs
	var items_any = (items_db_any as Dictionary).get("items", [])
	if not (items_any is Array):
		return defs
	for item_any in items_any:
		if not (item_any is Dictionary):
			continue
		var item_def: Dictionary = item_any
		var item_id := str(item_def.get("id", ""))
		if item_id.is_empty():
			continue
		defs[item_id] = item_def
	return defs

func _on_tab_items_pressed() -> void:
	_tab = "items"
	_sanitize_filters_for_tab()
	refresh()

func _on_tab_equip_pressed() -> void:
	_tab = "equip"
	_sanitize_filters_for_tab()
	refresh()

func _on_tab_gem_pressed() -> void:
	_tab = "gem"
	_sanitize_filters_for_tab()
	refresh()

func _on_filter_changed(_index: int = -1) -> void:
	_sync_filter_state_from_ui()
	refresh()

func _on_filter_clear() -> void:
	_f_rarity = "all"
	_f_state = "all"
	_f_set = "all"
	_f_sort = "default"
	_f_query = ""
	_opt_rarity.select(0)
	_opt_state.select(0)
	_rebuild_set_options()
	_opt_sort.select(0)
	_txt_search.text = ""
	_sync_filter_state_from_ui()
	refresh()

func _on_search_changed(t: String) -> void:
	_f_query = t.strip_edges()
	refresh()

func _on_bulk_salvage_pressed() -> void:
	if _tab != "equip":
		return
	if _bulk_popup != null and _bulk_popup.has_method("open"):
		_bulk_popup.call("open")

func _on_slot_gui_input(event: InputEvent, index: int) -> void:
	if event is InputEventMouseButton:
		var mb: InputEventMouseButton = event
		if mb.button_index == MOUSE_BUTTON_LEFT and mb.pressed:
			_on_slot_clicked(index)
			accept_event()
			return
	if event is InputEventScreenTouch:
		var touch: InputEventScreenTouch = event
		if touch.pressed:
			_on_slot_clicked(index)
			accept_event()

func _on_slot_clicked(index: int) -> void:
	if index < 0 or index >= _slot_rows.size():
		return
	var row_any = _slot_rows[index]
	if not (row_any is Dictionary):
		return
	var row: Dictionary = row_any
	if row.is_empty():
		return

	if _tab == "gem":
		if _gem_popup != null and _gem_popup.has_method("open"):
			_gem_popup.call("open", row)
		return

	if _tab == "items":
		_show_item_detail(row)
		return

	if _tab != "equip":
		return

	var uid := int(row.get("uid", 0))
	if uid > 0 and _equip_popup != null and _equip_popup.has_method("open_for_bag"):
		_equip_popup.call("open_for_bag", uid)

func _on_equip_slot_pressed(slot_key: String) -> void:
	var uid: int = int(EquipmentModel.get_equipped_uid(slot_key))
	if uid <= 0:
		_set_detail_tip()
		return
	if _equip_popup != null and _equip_popup.has_method("open_for_slot"):
		_equip_popup.call("open_for_slot", slot_key, uid)

func _on_gem_slot_pressed(slot_idx: int) -> void:
	var slots := GemSlotModel.get_slots()
	var gem_id := ""
	if slot_idx >= 0 and slot_idx < slots.size():
		gem_id = str(slots[slot_idx])
	if gem_id.is_empty():
		if _gem_select_popup != null and _gem_select_popup.has_method("open"):
			_gem_select_popup.call("open", slot_idx)
		return
	if GemSlotModel.remove(slot_idx):
		EventBus.add_log("已卸下宝石：%s" % gem_id)

func _on_dim_bg_gui_input(event: InputEvent) -> void:
	if event is InputEventMouseButton:
		var mb: InputEventMouseButton = event
		if mb.button_index == MOUSE_BUTTON_LEFT and mb.pressed:
			close()
			accept_event()
			return
	if event is InputEventScreenTouch:
		var touch: InputEventScreenTouch = event
		if touch.pressed:
			close()
			accept_event()

func _on_inventory_updated() -> void:
	if visible:
		refresh()

func _on_layout_resized() -> void:
	_update_cell_size()
	_refresh_equip_slots()
	_refresh_gem_slots()

func _on_add_point_pressed() -> void:
	if _attr_popup != null and _attr_popup.has_method("open"):
		_attr_popup.call("open")

func _format_stat_value(v: Variant) -> String:
	if v is float:
		return str(int(round(float(v))))
	return str(int(v))

func _rarity_rank(r: String) -> int:
	match r:
		"gold":
			return 3
		"blue":
			return 2
		_:
			return 1

func _rarity_name_cn(rarity: String) -> String:
	match rarity:
		"blue":
			return "蓝色"
		"gold":
			return "金色"
		"purple":
			return "紫色"
		"orange":
			return "橙色"
		_:
			return "白色"
