extends Control

signal opened()
signal closed()

const SLOT_COUNT := 40
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
@onready var _lbl_tab_items: Label = $Panel/VBox/Tabs/TabItems/LblTabItems
@onready var _lbl_tab_equip: Label = $Panel/VBox/Tabs/TabEquip/LblTabEquip
@onready var _equip_title: Label = $Panel/VBox/TopSection/EquipPanel/VBox/EquipTitle
@onready var _stats_title: Label = $Panel/VBox/TopSection/StatsPanel/VBox/StatsTitle
@onready var _detail_title: Label = $Panel/VBox/TopSection/StatsPanel/VBox/DetailTitle
@onready var _stats_text: RichTextLabel = $Panel/VBox/TopSection/StatsPanel/VBox/StatsText
@onready var _detail_text: RichTextLabel = $Panel/VBox/TopSection/StatsPanel/VBox/DetailText
@onready var _grid: GridContainer = $Panel/VBox/BottomGrid/Grid
@onready var _btn_equip: Button = $Panel/VBox/ActionBar/BtnEquip
@onready var _btn_unequip: Button = $Panel/VBox/ActionBar/BtnUnequip
@onready var _equip_popup: Node = $EquipPopup

var _slots: Array[Control] = []
var _slot_rows: Array[Dictionary] = []
var _tab := "items"
var _selected_bag_uid := 0
var _selected_slot_key := ""
var _selected_slot_uid := 0
var _equip_slot_labels: Dictionary = {}

func _ready() -> void:
	visible = false
	_ensure_slots()
	_cache_equip_slot_labels()
	_apply_i18n()

	if not _btn_close.pressed.is_connected(close):
		_btn_close.pressed.connect(close)
	if not _btn_tab_items.pressed.is_connected(_on_tab_items_pressed):
		_btn_tab_items.pressed.connect(_on_tab_items_pressed)
	if not _btn_tab_equip.pressed.is_connected(_on_tab_equip_pressed):
		_btn_tab_equip.pressed.connect(_on_tab_equip_pressed)
	if not _btn_equip.pressed.is_connected(_on_btn_equip_pressed):
		_btn_equip.pressed.connect(_on_btn_equip_pressed)
	if not _btn_unequip.pressed.is_connected(_on_btn_unequip_pressed):
		_btn_unequip.pressed.connect(_on_btn_unequip_pressed)
	if not _dim_bg.gui_input.is_connected(_on_dim_bg_gui_input):
		_dim_bg.gui_input.connect(_on_dim_bg_gui_input)
	if not EventBus.inventory_updated.is_connected(_on_inventory_updated):
		EventBus.inventory_updated.connect(_on_inventory_updated)

	_refresh_tab_visual()
	_refresh_stats_panel()
	_refresh_detail_default()
	_update_action_buttons()

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
	closed.emit()

func refresh() -> void:
	var rarity_colors := _get_rarity_colors()
	var rows: Array[Dictionary] = []
	if _tab == "equip":
		rows = EquipmentModel.list_bag_sorted()
	else:
		rows = InventoryModel.list_items_sorted()

	_slot_rows.clear()
	_slot_rows.resize(_slots.size())
	for i in range(_slots.size()):
		var slot := _slots[i]
		if i < rows.size():
			var row: Dictionary = rows[i]
			var item_id := str(row.get("id", row.get("template_id", "")))
			var item_name := str(row.get("name", item_id))
			var rarity := str(row.get("rarity", "white"))
			var count := int(row.get("count", 1))
			slot.call("set_item", item_id, item_name, rarity, count, _resolve_rarity_color(rarity, rarity_colors))
			_slot_rows[i] = row
		else:
			slot.call("clear")
			_slot_rows[i] = {}

	if _tab != "equip":
		_selected_bag_uid = 0
	elif _selected_bag_uid != 0 and not _bag_contains_uid(_selected_bag_uid, rows):
		_selected_bag_uid = 0

	_refresh_equip_slots(rarity_colors)
	_refresh_stats_panel()
	if _selected_slot_key.is_empty() and _selected_bag_uid == 0:
		_refresh_detail_default()
	_refresh_tab_visual()
	_update_action_buttons()

func _apply_i18n() -> void:
	_title.text = I18nService.t("ui.bag.title")
	_btn_close.tooltip_text = I18nService.t("ui.btn.close")
	_lbl_tab_items.text = I18nService.t("ui.tab.items")
	_lbl_tab_equip.text = I18nService.t("ui.tab.equip")
	_equip_title.text = I18nService.t("ui.panel.equip_slots")
	_stats_title.text = I18nService.t("ui.panel.stats")
	_detail_title.text = I18nService.t("ui.panel.detail")
	_btn_equip.text = I18nService.t("ui.btn.equip")
	_btn_unequip.text = I18nService.t("ui.btn.unequip")

func _ensure_slots() -> void:
	if not _slots.is_empty():
		return
	for i in range(SLOT_COUNT):
		var slot_any = ITEM_SLOT_SCENE.instantiate()
		if slot_any is Control:
			var slot: Control = slot_any
			_grid.add_child(slot)
			_slots.append(slot)
			slot.mouse_filter = Control.MOUSE_FILTER_STOP
			if not slot.gui_input.is_connected(_on_slot_gui_input.bind(i)):
				slot.gui_input.connect(_on_slot_gui_input.bind(i))

func _cache_equip_slot_labels() -> void:
	_equip_slot_labels.clear()
	for slot_key in SLOT_KEYS:
		var btn_path := "Panel/VBox/TopSection/EquipPanel/VBox/EquipGrid/Slot_%s" % slot_key
		var btn_any = get_node_or_null(btn_path)
		if btn_any is BaseButton:
			var btn: BaseButton = btn_any
			if not btn.pressed.is_connected(_on_equip_slot_pressed.bind(slot_key)):
				btn.pressed.connect(_on_equip_slot_pressed.bind(slot_key))
			var label_any = btn.get_node_or_null("Text")
			if label_any is Label:
				_equip_slot_labels[slot_key] = label_any

func _refresh_tab_visual() -> void:
	var active := Color(1.0, 1.0, 1.0, 1.0)
	var inactive := Color(0.68, 0.68, 0.68, 1.0)
	_lbl_tab_items.modulate = active if _tab == "items" else inactive
	_lbl_tab_equip.modulate = active if _tab == "equip" else inactive

func _refresh_stats_panel() -> void:
	var stats: Dictionary = EquipmentModel.get_total_stats()
	_stats_text.text = "HP %d\nATK %d\nDEF %d\nCRIT %d" % [
		int(stats.get("HP", 0)),
		int(stats.get("ATK", 0)),
		int(stats.get("DEF", 0)),
		int(stats.get("CRIT", 0)),
	]

func _refresh_detail_default() -> void:
	if _tab == "equip":
		_detail_text.text = I18nService.t("ui.tip.select_equip")
	else:
		_detail_text.text = I18nService.t("ui.tip.select_slot")

func _refresh_equip_slots(rarity_colors: Dictionary) -> void:
	for slot_key in SLOT_KEYS:
		var label_any = _equip_slot_labels.get(slot_key)
		if not (label_any is Label):
			continue
		var label: Label = label_any
		var inst: Dictionary = EquipmentModel.get_equipped_instance(slot_key)
		var slot_name := I18nService.t("slot.%s" % slot_key, slot_key)
		if inst.is_empty():
			label.text = "%s\n—" % slot_name
			label.add_theme_color_override("font_color", Color(0.85, 0.85, 0.85, 0.95))
			continue
		var rarity := str(inst.get("rarity", "white"))
		var equip_name := str(inst.get("name", ""))
		label.text = "%s\n%s" % [slot_name, equip_name if not equip_name.is_empty() else "—"]
		label.add_theme_color_override("font_color", _resolve_rarity_color(rarity, rarity_colors))

func _update_action_buttons() -> void:
	_btn_equip.disabled = _selected_bag_uid <= 0
	_btn_unequip.disabled = _selected_slot_key.is_empty() or _selected_slot_uid <= 0

func _on_tab_items_pressed() -> void:
	_tab = "items"
	_selected_bag_uid = 0
	_selected_slot_key = ""
	_selected_slot_uid = 0
	refresh()

func _on_tab_equip_pressed() -> void:
	_tab = "equip"
	_selected_bag_uid = 0
	_selected_slot_key = ""
	_selected_slot_uid = 0
	refresh()

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
	var row: Dictionary = _slot_rows[index]
	if row.is_empty():
		return

	_selected_slot_key = ""
	_selected_slot_uid = 0

	if _tab == "equip":
		_selected_bag_uid = int(row.get("uid", 0))
		_detail_text.text = _build_equip_detail_text(row, false, "")
		if _selected_bag_uid > 0 and _equip_popup != null and _equip_popup.has_method("open_for_bag"):
			_equip_popup.call("open_for_bag", _selected_bag_uid)
	else:
		_selected_bag_uid = 0
		_detail_text.text = "%s：%s\n%s：%d\n%s：%s" % [
			I18nService.t("ui.popup.item", "物品"),
			str(row.get("name", str(row.get("id", "")))),
			I18nService.t("ui.popup.count", "数量"),
			int(row.get("count", 1)),
			I18nService.t("ui.popup.rarity", "稀有度"),
			str(row.get("rarity", "white")),
		]
	_update_action_buttons()

func _on_equip_slot_pressed(slot_key: String) -> void:
	_selected_bag_uid = 0
	_selected_slot_key = slot_key
	_selected_slot_uid = EquipmentModel.get_equipped_uid(slot_key)
	if _selected_slot_uid <= 0:
		_detail_text.text = I18nService.t("ui.popup.empty_slot", "—")
		_update_action_buttons()
		return

	var inst: Dictionary = EquipmentModel.get_equipped_instance(slot_key)
	_detail_text.text = _build_equip_detail_text(inst, true, slot_key)
	if _equip_popup != null and _equip_popup.has_method("open_for_slot"):
		_equip_popup.call("open_for_slot", slot_key, _selected_slot_uid)
	_update_action_buttons()

func _on_btn_equip_pressed() -> void:
	if _selected_bag_uid <= 0:
		return
	if _equip_popup != null and _equip_popup.has_method("open_for_bag"):
		_equip_popup.call("open_for_bag", _selected_bag_uid)

func _on_btn_unequip_pressed() -> void:
	if _selected_slot_key.is_empty() or _selected_slot_uid <= 0:
		return
	if _equip_popup != null and _equip_popup.has_method("open_for_slot"):
		_equip_popup.call("open_for_slot", _selected_slot_key, _selected_slot_uid)

func _build_equip_detail_text(inst: Dictionary, equipped: bool, slot_key: String) -> String:
	if inst.is_empty():
		return "—"
	var rarity := str(inst.get("rarity", "white"))
	var rarity_text := I18nService.t("rarity.%s" % rarity, rarity)
	var slot_label := ""
	if equipped and not slot_key.is_empty():
		slot_label = I18nService.t("slot.%s" % slot_key, slot_key)
	else:
		var base_slot := str(inst.get("slot", ""))
		var display_slot := _slot_key_from_base(base_slot)
		slot_label = I18nService.t("slot.%s" % display_slot, display_slot)
	return "%s\n%s %s\n%s %s +%d\n%s %s" % [
		str(inst.get("name", "—")),
		I18nService.t("ui.popup.rarity", "稀有度:"),
		rarity_text,
		I18nService.t("ui.popup.main_stat", "主属性:"),
		str(inst.get("main_stat", "")),
		int(inst.get("main_val", 0)),
		I18nService.t("ui.popup.slot", "槽位:"),
		slot_label,
	]

func _slot_key_from_base(base_slot: String) -> String:
	match base_slot:
		"ring":
			return "ring1"
		"bracelet":
			return "bracelet1"
		_:
			return base_slot

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

func _bag_contains_uid(uid: int, rows: Array[Dictionary]) -> bool:
	if uid <= 0:
		return false
	for row in rows:
		if int(row.get("uid", 0)) == uid:
			return true
	return false

func _get_rarity_colors() -> Dictionary:
	var cfg: Dictionary = ConfigService.get_cfg()
	var items_db_any = cfg.get("items_db", {})
	if items_db_any is Dictionary:
		var items_db: Dictionary = items_db_any
		var colors_any = items_db.get("rarity_colors", {})
		if colors_any is Dictionary:
			return colors_any
	return {}

func _resolve_rarity_color(rarity: String, colors_cfg: Dictionary) -> Color:
	var color_any = colors_cfg.get(rarity, {})
	if color_any is Dictionary:
		var c: Dictionary = color_any
		return Color(
			float(c.get("r", 1.0)),
			float(c.get("g", 1.0)),
			float(c.get("b", 1.0)),
			float(c.get("a", 1.0))
		)
	match rarity:
		"blue":
			return Color(0.35, 0.65, 1.0, 1.0)
		"gold":
			return Color(1.0, 0.82, 0.35, 1.0)
		_:
			return Color(1.0, 1.0, 1.0, 1.0)
