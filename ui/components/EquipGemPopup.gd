extends Control

const MAX_SOCKETS := 4

@onready var _dim_bg: ColorRect = $DimBG
@onready var _title: Label = $Panel/VBox/TopBar/Title
@onready var _btn_close: Button = $Panel/VBox/TopBar/BtnClose
@onready var _rows: VBoxContainer = $Panel/VBox/Scroll/Rows
@onready var _tip: Label = $Panel/VBox/Tip
@onready var _gem_select_popup: Node = $"../GemSelectPopup"

var _uid := 0

func _ready() -> void:
	visible = false
	_title.text = "宝石镶嵌"
	_btn_close.text = I18nService.t("ui.btn.close", "关闭")
	_tip.text = "点击孔位可镶嵌或更换宝石"
	if not _btn_close.pressed.is_connected(close):
		_btn_close.pressed.connect(close)
	if not _dim_bg.gui_input.is_connected(_on_dim_bg_gui_input):
		_dim_bg.gui_input.connect(_on_dim_bg_gui_input)
	if not EventBus.inventory_updated.is_connected(_on_inventory_updated):
		EventBus.inventory_updated.connect(_on_inventory_updated)

func open_for_equip(uid: int) -> void:
	if uid <= 0:
		return
	_uid = uid
	visible = true
	refresh()

func close() -> void:
	visible = false
	_uid = 0

func refresh() -> void:
	if _uid <= 0:
		close()
		return
	var inst := _find_instance(_uid)
	if inst.is_empty():
		close()
		return
	_title.text = "宝石镶嵌：%s" % str(inst.get("name", "装备"))

	for child in _rows.get_children():
		_rows.remove_child(child)
		child.queue_free()

	var sockets := clampi(int(inst.get("sockets", 0)), 0, MAX_SOCKETS)
	var socket_gems := _normalize_socket_gems(inst.get("socket_gems", []), sockets)
	for i in range(MAX_SOCKETS):
		var line := HBoxContainer.new()
		line.custom_minimum_size = Vector2(0, 58)
		line.size_flags_horizontal = Control.SIZE_EXPAND_FILL
		line.add_theme_constant_override("separation", 10)

		var btn := Button.new()
		btn.custom_minimum_size = Vector2(220, 48)
		btn.add_theme_font_size_override("font_size", 20)
		btn.size_flags_horizontal = Control.SIZE_FILL
		btn.disabled = i >= sockets
		if i >= sockets:
			btn.text = "孔%d [未解锁]" % (i + 1)
		else:
			var gem_id := socket_gems[i]
			btn.text = "孔%d [%s]" % [i + 1, "空" if gem_id.is_empty() else gem_id]
			btn.pressed.connect(_on_socket_pressed.bind(i))
		line.add_child(btn)

		var desc := Label.new()
		desc.size_flags_horizontal = Control.SIZE_EXPAND_FILL
		desc.vertical_alignment = VERTICAL_ALIGNMENT_CENTER
		desc.add_theme_font_size_override("font_size", 18)
		desc.modulate = Color(0.85, 0.85, 0.85, 1.0)
		desc.text = _socket_desc_text(i, sockets, socket_gems)
		line.add_child(desc)

		_rows.add_child(line)

func _socket_desc_text(idx: int, sockets: int, socket_gems: Array[String]) -> String:
	if idx >= sockets:
		return "—"
	var gem_id := socket_gems[idx]
	if gem_id.is_empty():
		return "可镶嵌"
	var item_def := _find_item_def(gem_id)
	var effect_any = item_def.get("gem_effect", {})
	if effect_any is Dictionary:
		var effect: Dictionary = effect_any
		var stat := str(effect.get("stat", "")).strip_edges()
		var val := int(effect.get("val", 0))
		if not stat.is_empty() and val != 0:
			var percent := stat == "LOOT_BONUS_PERCENT" or stat == "CRIT_PERCENT"
			return "%s+%d%s" % [I18nService.stat(stat), val, "%" if percent else ""]
	return "属性未知"

func _find_item_def(item_id: String) -> Dictionary:
	if item_id.is_empty():
		return {}
	var cfg: Dictionary = ConfigService.get_cfg()
	var db_any = cfg.get("items_db", {})
	if not (db_any is Dictionary):
		return {}
	var items_any = (db_any as Dictionary).get("items", [])
	if not (items_any is Array):
		return {}
	for row_any in items_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		if str(row.get("id", "")).strip_edges() == item_id:
			return row
	return {}

func _find_instance(uid: int) -> Dictionary:
	if uid <= 0:
		return {}
	for row_any in EquipmentModel.list_bag_sorted():
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		if int(row.get("uid", 0)) == uid:
			return row
	for slot_key in ["weapon", "helm", "armor", "pants", "shoes", "cloak", "ring1", "ring2", "bracelet1", "bracelet2"]:
		var row := EquipmentModel.get_equipped_instance(slot_key)
		if row.is_empty():
			continue
		if int(row.get("uid", 0)) == uid:
			return row
	return {}

func _normalize_socket_gems(raw_any: Variant, sockets: int) -> Array[String]:
	var out: Array[String] = []
	out.resize(sockets)
	for i in range(sockets):
		out[i] = ""
	if raw_any is Array:
		var raw: Array = raw_any
		for i in range(mini(sockets, raw.size())):
			out[i] = str(raw[i]).strip_edges()
	return out

func _on_socket_pressed(socket_idx: int) -> void:
	if _uid <= 0:
		return
	if _gem_select_popup != null and _gem_select_popup.has_method("open_for_equip_socket"):
		_gem_select_popup.call("open_for_equip_socket", _uid, socket_idx)

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
