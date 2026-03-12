extends Control

const PAGE_MAP := "res://ui/pages/PageMap.tscn"
const PLACEHOLDER_ICON := "res://assets/icons/stage_node_placeholder.png"

var _summary_label: Label
var _list: VBoxContainer
var _btn_gold: Button
var _btn_crystal: Button
var _btn_contribution: Button
var _tab := "gold"

func _ready() -> void:
	_build_ui()
	_connect_signals()
	_refresh()

func _build_ui() -> void:
	var bg := ColorRect.new()
	bg.anchors_preset = PRESET_FULL_RECT
	bg.anchor_right = 1.0
	bg.anchor_bottom = 1.0
	bg.color = Color(0.08, 0.08, 0.1, 1.0)
	add_child(bg)

	var root := VBoxContainer.new()
	root.anchors_preset = PRESET_FULL_RECT
	root.anchor_right = 1.0
	root.anchor_bottom = 1.0
	root.offset_left = 16.0
	root.offset_top = 16.0
	root.offset_right = -16.0
	root.offset_bottom = -16.0
	root.add_theme_constant_override("separation", 10)
	add_child(root)

	var header := HBoxContainer.new()
	header.custom_minimum_size = Vector2(0, 64)
	root.add_child(header)

	var btn_back := Button.new()
	btn_back.custom_minimum_size = Vector2(92, 44)
	btn_back.text = "返回"
	btn_back.pressed.connect(func() -> void:
		get_tree().change_scene_to_file(PAGE_MAP)
	)
	header.add_child(btn_back)

	var title := Label.new()
	title.text = "宝库"
	title.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	title.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	title.vertical_alignment = VERTICAL_ALIGNMENT_CENTER
	title.add_theme_font_size_override("font_size", 34)
	header.add_child(title)

	var spacer := Control.new()
	spacer.custom_minimum_size = Vector2(92, 0)
	header.add_child(spacer)

	_summary_label = Label.new()
	_summary_label.autowrap_mode = TextServer.AUTOWRAP_WORD_SMART
	_summary_label.add_theme_font_size_override("font_size", 20)
	root.add_child(_summary_label)

	var tabs := HBoxContainer.new()
	tabs.add_theme_constant_override("separation", 8)
	root.add_child(tabs)

	_btn_gold = _make_tab_button("金币商城", "gold")
	_btn_crystal = _make_tab_button("晶石商城", "crystal")
	_btn_contribution = _make_tab_button("贡献商城", "contribution")
	tabs.add_child(_btn_gold)
	tabs.add_child(_btn_crystal)
	tabs.add_child(_btn_contribution)

	var scroll := ScrollContainer.new()
	scroll.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	scroll.size_flags_vertical = Control.SIZE_EXPAND_FILL
	root.add_child(scroll)

	_list = VBoxContainer.new()
	_list.add_theme_constant_override("separation", 10)
	_list.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	scroll.add_child(_list)

func _make_tab_button(text: String, tab_key: String) -> Button:
	var btn := Button.new()
	btn.text = text
	btn.custom_minimum_size = Vector2(0, 46)
	btn.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	btn.pressed.connect(func() -> void:
		_set_tab(tab_key)
	)
	return btn

func _connect_signals() -> void:
	var event_bus := _event_bus()
	var shop_service := _shop_service()
	if event_bus != null and not event_bus.inventory_updated.is_connected(_on_data_changed):
		event_bus.inventory_updated.connect(_on_data_changed)
	if shop_service != null and not shop_service.data_changed.is_connected(_on_data_changed):
		shop_service.data_changed.connect(_on_data_changed)

func _set_tab(tab_key: String) -> void:
	_tab = tab_key
	_refresh_tabs()
	_rebuild_list()

func _refresh() -> void:
	_refresh_tabs()
	_refresh_summary()
	_rebuild_list()

func _refresh_tabs() -> void:
	var active_color := Color(1.0, 0.88, 0.3, 1.0)
	var normal_color := Color(0.85, 0.85, 0.88, 1.0)
	if _btn_gold != null:
		_btn_gold.modulate = active_color if _tab == "gold" else normal_color
	if _btn_crystal != null:
		_btn_crystal.modulate = active_color if _tab == "crystal" else normal_color
	if _btn_contribution != null:
		_btn_contribution.modulate = active_color if _tab == "contribution" else normal_color

func _refresh_summary() -> void:
	var player_model := _player_model()
	if player_model == null:
		_summary_label.text = "金币：0｜晶石：0｜宗门贡献：0"
		return
	_summary_label.text = "金币：%d｜晶石：%d｜宗门贡献：%d" % [
		int(player_model.gold),
		int(player_model.spirit_stone),
		int(player_model.sect_contribution),
	]

func _rebuild_list() -> void:
	for child in _list.get_children():
		_list.remove_child(child)
		child.queue_free()

	var rows: Array[Dictionary] = []
	if _shop_service() == null:
		rows = []
	else:
		rows = _shop_service().get_rows(_tab)
	if rows.is_empty():
		var empty := Label.new()
		empty.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
		empty.text = "当前暂无可显示商品"
		_list.add_child(empty)
		return

	for row in rows:
		_list.add_child(_build_goods_card(row))

func _build_goods_card(row: Dictionary) -> Control:
	var panel := PanelContainer.new()
	panel.size_flags_horizontal = Control.SIZE_EXPAND_FILL

	var root := HBoxContainer.new()
	root.custom_minimum_size = Vector2(0, 168)
	root.add_theme_constant_override("separation", 10)
	panel.add_child(root)

	var icon := TextureRect.new()
	icon.custom_minimum_size = Vector2(104, 104)
	icon.expand_mode = TextureRect.EXPAND_IGNORE_SIZE
	icon.stretch_mode = TextureRect.STRETCH_KEEP_ASPECT_CENTERED
	var icon_path := str(row.get("reward_icon", PLACEHOLDER_ICON)).strip_edges()
	if icon_path.is_empty() or not ResourceLoader.exists(icon_path):
		icon_path = PLACEHOLDER_ICON
	var loaded: Variant = load(icon_path)
	if loaded is Texture2D:
		icon.texture = loaded
	root.add_child(icon)

	var center := VBoxContainer.new()
	center.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	center.add_theme_constant_override("separation", 5)
	root.add_child(center)

	var title := Label.new()
	title.add_theme_font_size_override("font_size", 24)
	title.text = str(row.get("title", "商城商品"))
	center.add_child(title)

	var subtitle := str(row.get("subtitle", "")).strip_edges()
	if not subtitle.is_empty():
		var sub := Label.new()
		sub.text = subtitle
		sub.modulate = Color(0.90, 0.86, 0.72, 1.0)
		center.add_child(sub)

	var desc := Label.new()
	desc.autowrap_mode = TextServer.AUTOWRAP_WORD_SMART
	desc.text = str(row.get("desc", ""))
	desc.modulate = Color(0.86, 0.86, 0.9, 1.0)
	center.add_child(desc)

	var reward := Label.new()
	reward.text = "奖励：%s x%d" % [str(row.get("reward_item_name", "奖励物品")), maxi(1, int(row.get("reward_count", 1)))]
	reward.modulate = Color(0.92, 0.84, 0.62, 1.0)
	center.add_child(reward)

	var price := Label.new()
	price.text = "价格：%s %d｜开放等级：Lv%d" % [
		_currency_name(str(row.get("cost_currency_type", "gold"))),
		maxi(0, int(row.get("cost_amount", 0))),
		maxi(1, int(row.get("unlock_level", 1))),
	]
	center.add_child(price)

	var limits := Label.new()
	limits.autowrap_mode = TextServer.AUTOWRAP_WORD_SMART
	limits.text = "限购：%s" % str(row.get("limit_text", "不限购"))
	center.add_child(limits)

	var right := VBoxContainer.new()
	right.custom_minimum_size = Vector2(180, 0)
	right.alignment = BoxContainer.ALIGNMENT_CENTER
	right.add_theme_constant_override("separation", 8)
	root.add_child(right)

	var state := Label.new()
	state.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	state.autowrap_mode = TextServer.AUTOWRAP_WORD_SMART
	state.text = str(row.get("status_text", "可购买"))
	state.modulate = _state_color(str(row.get("status_code", "can_buy")))
	right.add_child(state)

	var buy_btn := Button.new()
	buy_btn.custom_minimum_size = Vector2(150, 48)
	buy_btn.text = "购买" if bool(row.get("can_buy", false)) else str(row.get("status_text", "不可购买"))
	buy_btn.disabled = not bool(row.get("can_buy", false))
	if bool(row.get("can_buy", false)):
		buy_btn.pressed.connect(_on_buy_pressed.bind(str(row.get("goods_id", ""))))
	right.add_child(buy_btn)

	return panel

func _on_buy_pressed(goods_id: String) -> void:
	var shop_service := _shop_service()
	var event_bus := _event_bus()
	if shop_service == null:
		if event_bus != null:
			event_bus.add_log("商城服务未初始化")
		return
	shop_service.purchase(goods_id, func(result: Dictionary) -> void:
		if event_bus != null:
			event_bus.add_log(shop_service.purchase_result_text(result))
		_refresh()
	)

func _currency_name(currency_type: String) -> String:
	match currency_type:
		"gold":
			return "金币"
		"crystal":
			return "晶石"
		"contribution":
			return "宗门贡献"
		_:
			return currency_type

func _state_color(state_code: String) -> Color:
	match state_code:
		"can_buy":
			return Color(0.78, 1.0, 0.78, 1.0)
		"no_currency":
			return Color(1.0, 0.78, 0.65, 1.0)
		"locked", "daily_sold_out", "weekly_sold_out", "lifetime_sold_out":
			return Color(1.0, 0.7, 0.7, 1.0)
		_:
			return Color(0.9, 0.9, 0.9, 1.0)

func _on_data_changed() -> void:
	_refresh()

func _event_bus() -> Node:
	return get_node_or_null("/root/EventBus")

func _shop_service() -> Node:
	return get_node_or_null("/root/ShopService")

func _player_model() -> Node:
	return get_node_or_null("/root/PlayerModel")
