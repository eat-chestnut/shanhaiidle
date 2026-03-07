extends Control

@onready var _dim_bg: ColorRect = $DimBG
@onready var _panel_title: Label = $Panel/VBox/Title
@onready var _lbl_points: Label = $Panel/VBox/LblPoints
@onready var _btn_close: Button = $Panel/VBox/BtnClose

var _rows: Dictionary = {}
const ATTR_ROWS := [
	{"id": "strength", "node": "str"},
	{"id": "physique", "node": "vit"},
	{"id": "agility", "node": "agi"},
	{"id": "spirit", "node": "spi"},
	{"id": "true_energy", "node": "qi"},
	{"id": "fortune", "node": "luck"},
]

func _ready() -> void:
	visible = false
	_bind_rows()
	_apply_i18n()

	if not _btn_close.pressed.is_connected(close):
		_btn_close.pressed.connect(close)
	if not _dim_bg.gui_input.is_connected(_on_dim_bg_gui_input):
		_dim_bg.gui_input.connect(_on_dim_bg_gui_input)
	if not EventBus.inventory_updated.is_connected(_on_inventory_updated):
		EventBus.inventory_updated.connect(_on_inventory_updated)

func open() -> void:
	visible = true
	refresh()

func close() -> void:
	visible = false

func refresh() -> void:
	_lbl_points.text = "%s：%d" % [
		I18nService.t("ui.attr_points", "属性点"),
		ProgressModel.free_attr_points,
	]
	var has_points := ProgressModel.free_attr_points > 0
	for row_cfg in ATTR_ROWS:
		var key := str(row_cfg.get("id", ""))
		var row_any = _rows.get(key)
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		var name_label_any = row.get("name")
		var value_label_any = row.get("value")
		var btn_any = row.get("btn")
		if not (name_label_any is Label) or not (value_label_any is Label) or not (btn_any is Button):
			continue
		var name_label: Label = name_label_any
		var value_label: Label = value_label_any
		var plus_btn: Button = btn_any

		name_label.text = I18nService.t("attr.%s" % key, key)
		value_label.text = str(int(ProgressModel.attrs.get(key, 0)))
		plus_btn.text = "+"
		plus_btn.disabled = not has_points

func _bind_rows() -> void:
	_rows.clear()
	for row_cfg in ATTR_ROWS:
		var key := str(row_cfg.get("id", ""))
		var node_suffix := str(row_cfg.get("node", ""))
		var row_path := "Panel/VBox/Rows/Row_%s" % node_suffix
		var row_node = get_node_or_null(row_path)
		if not (row_node is HBoxContainer):
			continue
		var name_label = row_node.get_node_or_null("Name")
		var value_label = row_node.get_node_or_null("Value")
		var btn = row_node.get_node_or_null("BtnPlus")
		if not (name_label is Label) or not (value_label is Label) or not (btn is Button):
			continue
		if not btn.pressed.is_connected(_on_plus_pressed.bind(key)):
			btn.pressed.connect(_on_plus_pressed.bind(key))
		_rows[key] = {
			"name": name_label,
			"value": value_label,
			"btn": btn,
		}

func _apply_i18n() -> void:
	_panel_title.text = I18nService.t("ui.btn.add_point", "加点")
	_btn_close.text = I18nService.t("ui.btn.close", "关闭")

func _on_plus_pressed(key: String) -> void:
	if ProgressModel.spend_attr(key):
		refresh()

func _on_inventory_updated() -> void:
	if visible:
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
