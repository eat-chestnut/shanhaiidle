extends Control

const SLOT_COUNT := 25
const ITEM_SLOT_SCENE := preload("res://ui/components/ItemSlot.tscn")

@onready var _dim_bg: ColorRect = $DimBG
@onready var _title: Label = $Panel/VBox/TopBar/Title
@onready var _btn_close: Button = $Panel/VBox/TopBar/BtnClose
@onready var _grid: GridContainer = $Panel/VBox/Scroll/Grid
@onready var _tip: Label = $Panel/VBox/Tip

var _target_slot_idx := -1
var _target_uid := 0
var _target_socket_idx := -1
var _mode := ""
var _slots: Array[Control] = []
var _rows: Array = []

func _ready() -> void:
	_ensure_slots()
	if not _btn_close.pressed.is_connected(close):
		_btn_close.pressed.connect(close)
	if not _dim_bg.gui_input.is_connected(_on_dim_bg_gui_input):
		_dim_bg.gui_input.connect(_on_dim_bg_gui_input)
	_title.text = "选择宝石"
	_btn_close.text = I18nService.t("ui.btn.close", "关闭")
	_tip.text = "点击宝石即可镶嵌"

func open(slot_idx: int) -> void:
	_mode = "gem_slot"
	_target_slot_idx = slot_idx
	_target_uid = 0
	_target_socket_idx = -1
	visible = true
	refresh()

func open_for_equip_socket(uid: int, socket_idx: int) -> void:
	_mode = "equip_socket"
	_target_uid = uid
	_target_socket_idx = socket_idx
	_target_slot_idx = -1
	visible = true
	refresh()

func close() -> void:
	visible = false
	_mode = ""
	_target_slot_idx = -1
	_target_uid = 0
	_target_socket_idx = -1

func refresh() -> void:
	var rows: Array = []
	for row_any in InventoryModel.list_items_sorted("gem"):
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		if int(row.get("count", 0)) <= 0:
			continue
		rows.append(row)

	_rows.clear()
	_rows.resize(_slots.size())
	for i in range(_slots.size()):
		var slot := _slots[i]
		if i < rows.size():
			var row: Dictionary = rows[i]
			var item_id := str(row.get("id", ""))
			var item_name := str(row.get("name", item_id))
			var effect_summary := EquipmentModel.describe_gem_effect(row)
			if not effect_summary.is_empty():
				item_name = "%s（%s）" % [item_name, effect_summary]
			var rarity := str(row.get("rarity", "white"))
			var count := int(row.get("count", 1))
			var icon_path := str(row.get("icon", ""))
			slot.call("set_item", item_id, item_name, rarity, count, icon_path)
			_rows[i] = row
		else:
			slot.call("clear")
			_rows[i] = {}

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
	if index < 0 or index >= _rows.size():
		return
	var row_any = _rows[index]
	if not (row_any is Dictionary):
		return
	var row: Dictionary = row_any
	if row.is_empty():
		return
	var gem_id := str(row.get("id", ""))
	if gem_id.is_empty():
		return
	if _mode == "equip_socket":
		if _target_uid <= 0 or _target_socket_idx < 0:
			return
		if EquipmentModel.set_socket_gem(_target_uid, _target_socket_idx, gem_id):
			close()
		return
	if _target_slot_idx < 0:
		return
	if GemSlotModel.insert(_target_slot_idx, gem_id):
		close()

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
