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
@onready var _btn_close: BaseButton = $Panel/VBox/Header/BtnClose
@onready var _btn_tab_items: BaseButton = $Panel/VBox/Tabs/TabItems/BtnTabItems
@onready var _btn_tab_equip: BaseButton = $Panel/VBox/Tabs/TabEquip/BtnTabEquip
@onready var _lbl_tab_items: Label = $Panel/VBox/Tabs/TabItems/LblTabItems
@onready var _lbl_tab_equip: Label = $Panel/VBox/Tabs/TabEquip/LblTabEquip
@onready var _grid: GridContainer = $Panel/VBox/BottomInventoryGrid/Grid
@onready var _stats_text: RichTextLabel = $Panel/VBox/TopSection/StatsPanel/VBox/StatsText
@onready var _detail_text: RichTextLabel = $Panel/VBox/TopSection/StatsPanel/VBox/DetailText
@onready var _btn_equip: Button = $Panel/VBox/ActionBar/BtnEquip
@onready var _btn_unequip: Button = $Panel/VBox/ActionBar/BtnUnequip

var _slots: Array[Control] = []
var _slot_rows: Array[Dictionary] = []
var _tab := "items"
var _selected_bag_uid := 0
var _selected_slot_key := ""
var _equip_slot_labels: Dictionary = {}

func _ready() -> void:
	visible = false
	_ensure_slots()
	_cache_equip_slot_labels()

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
	_update_action_buttons()
	_refresh_stats_panel()

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
	_refresh_detail()
	_refresh_tab_visual()
	_update_action_buttons()

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
		var btn_path := "Panel/VBox/TopSection/EquipPanel/EquipGrid/Slot_%s" % slot_key
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

func _refresh_equip_slots(rarity_colors: Dictionary) -> void:
	for slot_key in SLOT_KEYS:
		var label_any = _equip_slot_labels.get(slot_key)
		if not (label_any is Label):
			continue
		var label: Label = label_any
		var inst: Dictionary = EquipmentModel.get_equipped_instance(slot_key)
		if inst.is_empty():
			label.text = "%s: —" % slot_key
			label.add_theme_color_override("font_color", Color(0.85, 0.85, 0.85, 0.95))
			continue
		var rarity := str(inst.get("rarity", "white"))
		label.text = "%s: %s" % [slot_key, str(inst.get("name", "未知装备"))]
		label.add_theme_color_override("font_color", _resolve_rarity_color(rarity, rarity_colors))

func _refresh_detail() -> void:
	if _selected_bag_uid > 0:
		var bag_row := _find_bag_row_by_uid(_selected_bag_uid)
		if not bag_row.is_empty():
			_show_bag_detail(bag_row)
			return
		_selected_bag_uid = 0

	if not _selected_slot_key.is_empty():
		var equipped_row: Dictionary = EquipmentModel.get_equipped_instance(_selected_slot_key)
		if equipped_row.is_empty():
			_detail_text.text = "栏位：%s\n当前为空" % _selected_slot_key
		else:
			_detail_text.text = "栏位：%s\n名称：%s\n稀有度：%s\n主属性：%s +%d" % [
				_selected_slot_key,
				str(equipped_row.get("name", "未知装备")),
				str(equipped_row.get("rarity", "white")),
				str(equipped_row.get("main_stat", "")),
				int(equipped_row.get("main_val", 0)),
			]
		return

	if _tab == "items":
		_detail_text.text = "物品页签：用于查看材料/杂物。"
	else:
		_detail_text.text = "装备页签：选择装备并穿戴，或点槽位卸下。"

func _show_bag_detail(row: Dictionary) -> void:
	_detail_text.text = "名称：%s\n稀有度：%s\n槽位：%s\n主属性：%s +%d\nUID：%d" % [
		str(row.get("name", "未知装备")),
		str(row.get("rarity", "white")),
		str(row.get("slot", "")),
		str(row.get("main_stat", "")),
		int(row.get("main_val", 0)),
		int(row.get("uid", 0)),
	]

func _update_action_buttons() -> void:
	_btn_equip.disabled = not (_tab == "equip" and _selected_bag_uid > 0)
	var can_unequip := false
	if not _selected_slot_key.is_empty():
		can_unequip = EquipmentModel.get_equipped_uid(_selected_slot_key) != 0
	_btn_unequip.disabled = not can_unequip

func _on_tab_items_pressed() -> void:
	_tab = "items"
	_selected_bag_uid = 0
	_selected_slot_key = ""
	refresh()

func _on_tab_equip_pressed() -> void:
	_tab = "equip"
	_selected_bag_uid = 0
	_selected_slot_key = ""
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
	if _tab == "equip":
		_selected_bag_uid = int(row.get("uid", 0))
		_show_bag_detail(row)
	else:
		_selected_bag_uid = 0
		_detail_text.text = "物品：%s\n数量：%d\n稀有度：%s" % [
			str(row.get("name", str(row.get("id", "")))),
			int(row.get("count", 1)),
			str(row.get("rarity", "white")),
		]
	_update_action_buttons()

func _on_equip_slot_pressed(slot_key: String) -> void:
	_selected_slot_key = slot_key
	_selected_bag_uid = 0
	_refresh_detail()
	_update_action_buttons()

func _on_btn_equip_pressed() -> void:
	if _selected_bag_uid <= 0:
		return
	if EquipmentModel.equip_uid(_selected_bag_uid):
		_selected_bag_uid = 0
		refresh()

func _on_btn_unequip_pressed() -> void:
	if _selected_slot_key.is_empty():
		return
	EquipmentModel.unequip(_selected_slot_key)
	refresh()

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

func _find_bag_row_by_uid(uid: int) -> Dictionary:
	if uid <= 0:
		return {}
	var rows: Array[Dictionary] = EquipmentModel.list_bag_sorted()
	for row in rows:
		if int(row.get("uid", 0)) == uid:
			return row
	return {}

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
