extends Control

signal equip_selected(uid: int)

@onready var _dim_bg: ColorRect = $DimBG
@onready var _title: Label = $Panel/VBox/TopBar/Title
@onready var _btn_close: Button = $Panel/VBox/TopBar/BtnClose
@onready var _list: ItemList = $Panel/VBox/Body/EquipList
@onready var _detail: RichTextLabel = $Panel/VBox/Body/Detail
@onready var _btn_confirm: Button = $Panel/VBox/ActionBar/BtnConfirm
@onready var _btn_cancel: Button = $Panel/VBox/ActionBar/BtnCancel

var _rows: Array[Dictionary] = []
var _selected_idx := -1

func _ready() -> void:
	visible = false
	_detail.bbcode_enabled = true
	_title.text = "选择基础装备"
	_btn_close.text = I18nService.t("ui.btn.close", "关闭")
	_btn_confirm.text = "确认选择"
	_btn_cancel.text = I18nService.t("ui.btn.cancel", "取消")
	if not _btn_close.pressed.is_connected(close):
		_btn_close.pressed.connect(close)
	if not _btn_cancel.pressed.is_connected(close):
		_btn_cancel.pressed.connect(close)
	if not _btn_confirm.pressed.is_connected(_on_confirm_pressed):
		_btn_confirm.pressed.connect(_on_confirm_pressed)
	if not _list.item_selected.is_connected(_on_item_selected):
		_list.item_selected.connect(_on_item_selected)
	if not _list.item_activated.is_connected(_on_item_activated):
		_list.item_activated.connect(_on_item_activated)
	if not _dim_bg.gui_input.is_connected(_on_dim_bg_gui_input):
		_dim_bg.gui_input.connect(_on_dim_bg_gui_input)

func open_with_candidates(rows: Array[Dictionary], selected_uid: int = 0) -> void:
	_rows.clear()
	for row_any in rows:
		if row_any is Dictionary:
			_rows.append((row_any as Dictionary).duplicate(true))
	visible = true
	_rebuild_list(selected_uid)
	_refresh_detail()

func close() -> void:
	visible = false
	_rows.clear()
	_selected_idx = -1
	_list.clear()
	_detail.text = ""
	_btn_confirm.disabled = true

func _rebuild_list(selected_uid: int) -> void:
	_list.clear()
	_selected_idx = -1
	for i in range(_rows.size()):
		var row: Dictionary = _rows[i]
		var name := str(row.get("name", row.get("template_id", "装备")))
		var star := int(row.get("star_level", 0))
		var sockets := int(row.get("sockets", 0))
		var equipped_tag := "[已穿戴] " if bool(row.get("is_equipped", false)) else ""
		var locked_tag := " [锁定]" if bool(row.get("locked", false)) else ""
		var text := "%s%s +%d  孔%d%s" % [equipped_tag, name, star, sockets, locked_tag]
		_list.add_item(text)
		if int(row.get("uid", 0)) == selected_uid and _selected_idx < 0:
			_selected_idx = i
	if _selected_idx < 0 and not _rows.is_empty():
		_selected_idx = 0
	if _selected_idx >= 0:
		_list.select(_selected_idx)

func _refresh_detail() -> void:
	if _selected_idx < 0 or _selected_idx >= _rows.size():
		_detail.text = "请选择基础装备"
		_btn_confirm.disabled = true
		return
	var row: Dictionary = _rows[_selected_idx]
	var slot_text := I18nService.t("slot.%s" % str(row.get("slot", "")), str(row.get("slot", "")))
	var gems := _socket_gem_summary(row)
	var lines: Array[String] = []
	lines.append("[b]%s[/b]" % str(row.get("name", row.get("template_id", "装备"))) )
	lines.append("UID：%d" % int(row.get("uid", 0)))
	lines.append("槽位：%s" % slot_text)
	lines.append("星级：+%d" % int(row.get("star_level", 0)))
	lines.append("孔位：%d" % int(row.get("sockets", 0)))
	lines.append("宝石：%s" % gems)
	lines.append("状态：%s" % ("已穿戴" if bool(row.get("is_equipped", false)) else "背包中"))
	lines.append("锁定：%s" % ("是" if bool(row.get("locked", false)) else "否"))
	_detail.text = "\n".join(lines)
	_btn_confirm.disabled = false

func _socket_gem_summary(row: Dictionary) -> String:
	var sockets := maxi(0, int(row.get("sockets", 0)))
	var gems_any = row.get("socket_gems", [])
	var gems: Array = gems_any if gems_any is Array else []
	if sockets <= 0:
		return "无"
	var parts: Array[String] = []
	for i in range(sockets):
		var gem_id := ""
		if i < gems.size():
			gem_id = str(gems[i]).strip_edges()
		parts.append(gem_id if not gem_id.is_empty() else "空")
	return " / ".join(parts)

func _on_item_selected(index: int) -> void:
	_selected_idx = index
	_refresh_detail()

func _on_item_activated(index: int) -> void:
	_selected_idx = index
	_refresh_detail()
	_on_confirm_pressed()

func _on_confirm_pressed() -> void:
	if _selected_idx < 0 or _selected_idx >= _rows.size():
		return
	var row: Dictionary = _rows[_selected_idx]
	var uid := int(row.get("uid", 0))
	if uid <= 0:
		return
	emit_signal("equip_selected", uid)
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
