extends Control

signal opened()
signal closed()

const SLOT_COUNT := 40
const ITEM_SLOT_SCENE := preload("res://ui/components/ItemSlot.tscn")

@onready var _dim_bg: ColorRect = $DimBG
@onready var _btn_close: BaseButton = $Panel/VBox/TopBar/BtnClose
@onready var _grid: GridContainer = $Panel/VBox/Grid

var _slots: Array[Control] = []

func _ready() -> void:
	visible = false
	_ensure_slots()
	if not _btn_close.pressed.is_connected(close):
		_btn_close.pressed.connect(close)
	if not _dim_bg.gui_input.is_connected(_on_dim_bg_gui_input):
		_dim_bg.gui_input.connect(_on_dim_bg_gui_input)
	if not EventBus.inventory_updated.is_connected(_on_inventory_updated):
		EventBus.inventory_updated.connect(_on_inventory_updated)

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
	if _slots.is_empty():
		_ensure_slots()
	var rows: Array[Dictionary] = InventoryModel.list_items_sorted()
	var rarity_colors := _get_rarity_colors()

	for i in _slots.size():
		var slot := _slots[i]
		if i < rows.size():
			var row: Dictionary = rows[i]
			var item_id := str(row.get("id", ""))
			var item_name := str(row.get("name", item_id))
			var rarity := str(row.get("rarity", "white"))
			var count := int(row.get("count", 1))
			slot.call("set_item", item_id, item_name, rarity, count, _resolve_rarity_color(rarity, rarity_colors))
		else:
			slot.call("clear")

func _ensure_slots() -> void:
	if not _slots.is_empty():
		return
	for _i in range(SLOT_COUNT):
		var slot_any = ITEM_SLOT_SCENE.instantiate()
		if slot_any is Control:
			var slot: Control = slot_any
			_grid.add_child(slot)
			_slots.append(slot)

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
