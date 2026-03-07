extends Control

@onready var _dim_bg: ColorRect = $DimBG
@onready var _lbl_title: Label = $Panel/VBox/TopBar/LblTitle
@onready var _btn_close: Button = $Panel/VBox/TopBar/BtnClose
@onready var _rich_detail: RichTextLabel = $Panel/VBox/RichDetail
@onready var _btn_ok: Button = $Panel/VBox/BtnOK

func _ready() -> void:
	if not _btn_close.pressed.is_connected(close):
		_btn_close.pressed.connect(close)
	if not _btn_ok.pressed.is_connected(close):
		_btn_ok.pressed.connect(close)
	if not _dim_bg.gui_input.is_connected(_on_dim_bg_gui_input):
		_dim_bg.gui_input.connect(_on_dim_bg_gui_input)
	_btn_close.text = I18nService.t("ui.btn.close", "关闭")
	_btn_ok.text = "确定"

func open(item_row: Dictionary) -> void:
	visible = true
	var item_name := str(item_row.get("name", item_row.get("id", "")))
	if item_name.is_empty():
		item_name = I18nService.t("ui.tab.gem", "宝石")
	_lbl_title.text = item_name

	var rarity := str(item_row.get("rarity", "white"))
	var trait_text := str(item_row.get("trait", ""))
	var rarity_name := I18nService.t("rarity.%s" % rarity, rarity)
	var trait_title := I18nService.t("ui.gem_trait", "特性")
	var none_text := I18nService.t("ui.none", "暂无")

	var lines: Array[String] = []
	lines.append("%s：%s" % [I18nService.t("ui.popup.rarity", "稀有度"), rarity_name])
	lines.append("")
	if trait_text.is_empty():
		lines.append("%s：%s" % [trait_title, none_text])
	else:
		lines.append("%s：" % trait_title)
		lines.append(trait_text)
	_rich_detail.text = "\n".join(lines)

func close() -> void:
	visible = false

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
