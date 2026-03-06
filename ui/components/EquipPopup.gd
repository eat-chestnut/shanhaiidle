extends Control

@onready var _dim_bg: ColorRect = $DimBG
@onready var _lbl_title: Label = $Panel/VBox/TopBar/LblTitle
@onready var _btn_close: Button = $Panel/VBox/TopBar/BtnClose
@onready var _rich_detail: RichTextLabel = $Panel/VBox/RichDetail
@onready var _btn_equip: Button = $Panel/VBox/ActionBar/BtnEquip
@onready var _btn_unequip: Button = $Panel/VBox/ActionBar/BtnUnequip
@onready var _btn_cancel: Button = $Panel/VBox/ActionBar/BtnCancel

var _mode := ""
var _uid := 0
var _slot_key := ""
var _inst: Dictionary = {}

func _ready() -> void:
	visible = false
	_btn_close.text = I18nService.t("ui.btn.close")
	_btn_close.tooltip_text = I18nService.t("ui.btn.close")
	_btn_equip.text = I18nService.t("ui.btn.equip")
	_btn_unequip.text = I18nService.t("ui.btn.unequip")
	_btn_cancel.text = I18nService.t("ui.btn.cancel")

	if not _btn_close.pressed.is_connected(close):
		_btn_close.pressed.connect(close)
	if not _btn_cancel.pressed.is_connected(close):
		_btn_cancel.pressed.connect(close)
	if not _btn_equip.pressed.is_connected(_on_btn_equip_pressed):
		_btn_equip.pressed.connect(_on_btn_equip_pressed)
	if not _btn_unequip.pressed.is_connected(_on_btn_unequip_pressed):
		_btn_unequip.pressed.connect(_on_btn_unequip_pressed)
	if not _dim_bg.gui_input.is_connected(_on_dim_bg_gui_input):
		_dim_bg.gui_input.connect(_on_dim_bg_gui_input)

func open_for_bag(uid: int) -> void:
	var inst := _find_bag_instance(uid)
	if inst.is_empty():
		return
	_mode = "bag"
	_uid = uid
	_slot_key = ""
	_inst = inst
	_refresh_ui()
	visible = true

func open_for_slot(slot_key: String, uid: int) -> void:
	var inst := EquipmentModel.get_equipped_instance(slot_key)
	if inst.is_empty():
		return
	var real_uid := int(inst.get("uid", 0))
	if uid > 0 and real_uid != uid:
		return
	_mode = "slot"
	_uid = real_uid
	_slot_key = slot_key
	_inst = inst
	_refresh_ui()
	visible = true

func close() -> void:
	visible = false
	_mode = ""
	_uid = 0
	_slot_key = ""
	_inst = {}

func _refresh_ui() -> void:
	if _inst.is_empty():
		_lbl_title.text = I18nService.t("ui.panel.detail")
		_rich_detail.text = I18nService.t("ui.tip.select_equip")
		_btn_equip.disabled = true
		_btn_unequip.disabled = true
		return

	var name := str(_inst.get("name", ""))
	_lbl_title.text = name if not name.is_empty() else I18nService.t("ui.panel.detail")

	var rarity := str(_inst.get("rarity", "white"))
	var rarity_text := I18nService.t("rarity.%s" % rarity, rarity)
	var base_slot := str(_inst.get("slot", ""))
	var slot_key_text := _slot_key if _mode == "slot" and not _slot_key.is_empty() else _slot_key_from_base(base_slot)
	var slot_name := I18nService.t("slot.%s" % slot_key_text, slot_key_text)
	var main_stat := str(_inst.get("main_stat", ""))
	var main_val := int(_inst.get("main_val", 0))
	var equip_state := I18nService.t("ui.popup.equip_state", "未装备")
	var equip_slot_text := I18nService.t("ui.popup.equip_slot", "—")
	if _mode == "slot":
		equip_state = I18nService.t("ui.popup.equip_state_on", "已装备")
		equip_slot_text = slot_name

	_rich_detail.text = "%s: %s\n%s: %s\n%s: %s +%d\n%s: %s\n%s: %s" % [
		I18nService.t("ui.panel.detail", "详情"),
		name,
		I18nService.t("ui.popup.rarity", "稀有度"),
		rarity_text,
		I18nService.t("ui.popup.main_stat", "主属性"),
		main_stat,
		main_val,
		I18nService.t("ui.popup.equipped", "状态"),
		equip_state,
		I18nService.t("ui.popup.slot", "槽位"),
		equip_slot_text
	]

	_btn_equip.disabled = _mode != "bag"
	_btn_unequip.disabled = _mode != "slot"

func _on_btn_equip_pressed() -> void:
	if _mode != "bag" or _uid <= 0:
		return
	EquipmentModel.equip_uid(_uid)
	close()

func _on_btn_unequip_pressed() -> void:
	if _mode != "slot" or _slot_key.is_empty():
		return
	EquipmentModel.unequip(_slot_key)
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

func _find_bag_instance(uid: int) -> Dictionary:
	if uid <= 0:
		return {}
	var rows: Array[Dictionary] = EquipmentModel.list_bag_sorted()
	for row in rows:
		if int(row.get("uid", 0)) == uid:
			return row
	return {}

func _slot_key_from_base(base_slot: String) -> String:
	match base_slot:
		"ring":
			return "ring1"
		"bracelet":
			return "bracelet1"
		_:
			return base_slot
