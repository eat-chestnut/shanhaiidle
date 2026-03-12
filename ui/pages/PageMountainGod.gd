extends Control

const PAGE_MAP := "res://ui/pages/PageMap.tscn"
const GOD_ICON_PATH := "res://assets/icons/stage_node_placeholder.png"

var _summary_label: Label
var _god_header: Label
var _god_desc: Label
var _list: VBoxContainer

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
	title.text = "山神殿"
	title.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	title.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	title.vertical_alignment = VERTICAL_ALIGNMENT_CENTER
	title.add_theme_font_size_override("font_size", 34)
	header.add_child(title)

	var spacer := Control.new()
	spacer.custom_minimum_size = Vector2(92, 0)
	header.add_child(spacer)

	var hero_panel := PanelContainer.new()
	root.add_child(hero_panel)

	var hero_row := HBoxContainer.new()
	hero_row.add_theme_constant_override("separation", 12)
	hero_panel.add_child(hero_row)

	var icon := TextureRect.new()
	icon.custom_minimum_size = Vector2(96, 96)
	icon.expand_mode = TextureRect.EXPAND_IGNORE_SIZE
	icon.stretch_mode = TextureRect.STRETCH_KEEP_ASPECT_CENTERED
	var texture: Variant = load(GOD_ICON_PATH)
	if texture is Texture2D:
		icon.texture = texture
	hero_row.add_child(icon)

	var hero_text := VBoxContainer.new()
	hero_text.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	hero_text.add_theme_constant_override("separation", 6)
	hero_row.add_child(hero_text)

	_god_header = Label.new()
	_god_header.add_theme_font_size_override("font_size", 28)
	hero_text.add_child(_god_header)

	_god_desc = Label.new()
	_god_desc.autowrap_mode = TextServer.AUTOWRAP_WORD_SMART
	_god_desc.add_theme_font_size_override("font_size", 18)
	_god_desc.modulate = Color(0.86, 0.86, 0.9, 1.0)
	hero_text.add_child(_god_desc)

	_summary_label = Label.new()
	_summary_label.autowrap_mode = TextServer.AUTOWRAP_WORD_SMART
	_summary_label.add_theme_font_size_override("font_size", 20)
	root.add_child(_summary_label)

	var scroll := ScrollContainer.new()
	scroll.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	scroll.size_flags_vertical = Control.SIZE_EXPAND_FILL
	root.add_child(scroll)

	_list = VBoxContainer.new()
	_list.add_theme_constant_override("separation", 10)
	scroll.add_child(_list)

func _connect_signals() -> void:
	if not EventBus.inventory_updated.is_connected(_on_data_changed):
		EventBus.inventory_updated.connect(_on_data_changed)
	if not EventBus.mountain_god_updated.is_connected(_on_data_changed):
		EventBus.mountain_god_updated.connect(_on_data_changed)

func _refresh() -> void:
	var summary := MountainGodService.get_summary()
	_god_header.text = "%s｜%s" % [
		str(summary.get("name", "南山山神")),
		"已苏醒" if bool(summary.get("is_unlocked", false)) else "尚未唤醒",
	]
	_god_desc.text = str(summary.get("description", "南山山神每日只受一轮轻量供奉。可先兑换祭品，再进行供奉。"))
	if not bool(summary.get("is_unlocked", false)):
		_summary_label.text = "尚未唤醒%s，需通关%s。" % [
			str(summary.get("name", "南山山神")),
			str(summary.get("unlock_stage_name", "指定主线")),
		]
	else:
		_summary_label.text = "%s已苏醒，可进行每日轻量供奉。今日可供奉项：%d，剩余供奉次数：%d。金币：%d｜灵石：%d｜宗门贡献：%d" % [
			str(summary.get("name", "南山山神")),
			int(summary.get("can_offer_count", 0)),
			int(summary.get("remaining_total", 0)),
			PlayerModel.gold,
			PlayerModel.spirit_stone,
			PlayerModel.sect_contribution,
		]
	_rebuild_rows()

func _rebuild_rows() -> void:
	for child in _list.get_children():
		_list.remove_child(child)
		child.queue_free()
	var rows := MountainGodService.get_rows()
	if rows.is_empty():
		var empty := Label.new()
		empty.text = "暂无供奉项"
		empty.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
		_list.add_child(empty)
		return
	for row in rows:
		_list.add_child(_build_row(row))

func _build_row(row: Dictionary) -> Control:
	var panel := PanelContainer.new()
	panel.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	var vbox := VBoxContainer.new()
	vbox.add_theme_constant_override("separation", 6)
	panel.add_child(vbox)

	var top := HBoxContainer.new()
	top.add_theme_constant_override("separation", 8)
	vbox.add_child(top)

	var title := Label.new()
	title.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	title.add_theme_font_size_override("font_size", 24)
	title.text = str(row.get("name", "供奉项"))
	top.add_child(title)

	var owned := Label.new()
	owned.text = "持有：%d" % int(row.get("owned_count", 0))
	top.add_child(owned)

	var desc := Label.new()
	desc.autowrap_mode = TextServer.AUTOWRAP_WORD_SMART
	desc.text = "祭品：%s｜今日剩余：%d/%d" % [
		str(row.get("offering_item_name", "")),
		int(row.get("remaining", 0)),
		int(row.get("daily_limit", 1)),
	]
	vbox.add_child(desc)

	var exchange_any = row.get("exchange_cost", {})
	var exchange: Dictionary = exchange_any if exchange_any is Dictionary else {}
	var exchange_label := Label.new()
	exchange_label.text = "兑换消耗：金币%d｜宗门贡献%d" % [
		int(exchange.get("gold", 0)),
		int(exchange.get("sect_contribution", 0)),
	]
	vbox.add_child(exchange_label)

	var reward := Label.new()
	reward.autowrap_mode = TextServer.AUTOWRAP_WORD_SMART
	reward.text = "供奉回报：%s" % str(row.get("reward_summary", "—"))
	reward.modulate = Color(0.88, 0.85, 0.62, 1.0)
	vbox.add_child(reward)

	var actions := HBoxContainer.new()
	actions.add_theme_constant_override("separation", 8)
	vbox.add_child(actions)

	var btn_exchange := Button.new()
	btn_exchange.text = "兑换祭品"
	btn_exchange.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	btn_exchange.disabled = not bool(row.get("can_exchange", false))
	btn_exchange.pressed.connect(_on_exchange_pressed.bind(str(row.get("offering_id", ""))))
	actions.add_child(btn_exchange)

	var btn_offer := Button.new()
	btn_offer.text = "立即供奉"
	btn_offer.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	btn_offer.disabled = not bool(row.get("can_offer", false))
	btn_offer.pressed.connect(_on_offer_pressed.bind(str(row.get("offering_id", ""))))
	actions.add_child(btn_offer)

	return panel

func _on_exchange_pressed(offering_id: String) -> void:
	var ret := MountainGodService.exchange_offering(offering_id)
	if bool(ret.get("ok", false)):
		_refresh()
		return
	match str(ret.get("reason", "")):
		"locked":
			EventBus.add_log("山神尚未唤醒")
		"no_gold":
			EventBus.add_log("金币不足，无法兑换祭品")
		"no_contribution":
			EventBus.add_log("宗门贡献不足，无法兑换祭品")
		_:
			EventBus.add_log("当前无法兑换该祭品")

func _on_offer_pressed(offering_id: String) -> void:
	var ret := MountainGodService.offer(offering_id)
	if bool(ret.get("ok", false)):
		_refresh()
		return
	match str(ret.get("reason", "")):
		"locked":
			EventBus.add_log("山神尚未唤醒")
		"no_item":
			EventBus.add_log("缺少祭品：%s" % str(ret.get("item_name", "祭品")))
		"daily_limit":
			EventBus.add_log("今日该供奉次数已用尽")
		_:
			EventBus.add_log("当前无法进行供奉")

func _on_data_changed() -> void:
	_refresh()
