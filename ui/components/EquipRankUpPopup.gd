extends Control

const SHOW_STATS_ORDER := ["HP", "ATK", "DEF", "CRIT_PERCENT", "LOOT_BONUS_PERCENT", "QI"]

@onready var _dim_bg: ColorRect = $DimBG
@onready var _title: Label = $Panel/VBox/TopBar/Title
@onready var _btn_close: Button = $Panel/VBox/TopBar/BtnClose
@onready var _left_icon: TextureRect = $Panel/VBox/ContentScroll/Content/EquipRow/CurrentCard/CurrentVBox/CurrentIcon
@onready var _left_name: Label = $Panel/VBox/ContentScroll/Content/EquipRow/CurrentCard/CurrentVBox/CurrentName
@onready var _left_meta: Label = $Panel/VBox/ContentScroll/Content/EquipRow/CurrentCard/CurrentVBox/CurrentMeta
@onready var _right_icon: TextureRect = $Panel/VBox/ContentScroll/Content/EquipRow/TargetCard/TargetVBox/TargetIcon
@onready var _right_name: Label = $Panel/VBox/ContentScroll/Content/EquipRow/TargetCard/TargetVBox/TargetName
@onready var _right_meta: Label = $Panel/VBox/ContentScroll/Content/EquipRow/TargetCard/TargetVBox/TargetMeta
@onready var _stats_now: RichTextLabel = $Panel/VBox/ContentScroll/Content/StatsRow/NowBox/NowText
@onready var _stats_next: RichTextLabel = $Panel/VBox/ContentScroll/Content/StatsRow/NextBox/NextText
@onready var _inherit_note: Label = $Panel/VBox/ContentScroll/Content/InheritNote
@onready var _material_flow: HFlowContainer = $Panel/VBox/ContentScroll/Content/MaterialSection/MaterialFlow
@onready var _status_label: Label = $Panel/VBox/ContentScroll/Content/StatusLabel
@onready var _btn_back: Button = $Panel/VBox/ActionBar/BtnBack
@onready var _btn_rank_up: Button = $Panel/VBox/ActionBar/BtnRankUp

var _uid := 0
var _preview: Dictionary = {}
var _icon_cache: Dictionary = {}

func _ready() -> void:
	visible = false
	_stats_now.bbcode_enabled = true
	_stats_next.bbcode_enabled = true
	_title.text = "装备升阶"
	_btn_close.text = I18nService.t("ui.btn.close", "关闭")
	_btn_back.text = "返回"
	_btn_rank_up.text = "开始升阶"
	_inherit_note.text = "升阶将继承：星级、宝石、蓝词条、洗练、锁定与穿戴状态"
	if not _btn_close.pressed.is_connected(close):
		_btn_close.pressed.connect(close)
	if not _btn_back.pressed.is_connected(close):
		_btn_back.pressed.connect(close)
	if not _btn_rank_up.pressed.is_connected(_on_rank_up_pressed):
		_btn_rank_up.pressed.connect(_on_rank_up_pressed)
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
	_preview = {}

func refresh() -> void:
	if _uid <= 0:
		close()
		return
	var inst := EquipmentModel.get_instance(_uid)
	if inst.is_empty():
		close()
		return
	_preview = EquipmentRankUpService.build_rank_up_preview(_uid)
	_render_cards(inst)
	_render_stats()
	_render_materials()
	_refresh_status()

func _render_cards(inst: Dictionary) -> void:
	var current_tpl_any = _preview.get("current_template", {})
	var current_tpl: Dictionary = current_tpl_any if current_tpl_any is Dictionary else {}
	var target_tpl_any = _preview.get("target_template", {})
	var target_tpl: Dictionary = target_tpl_any if target_tpl_any is Dictionary else {}
	var current_rank := int(_preview.get("current_rank_order", 0))
	var target_rank := int(_preview.get("target_rank_order", 0))
	var current_level := int(_preview.get("current_rank_level", current_tpl.get("required_level", 0)))
	var target_level := int(_preview.get("target_rank_level", target_tpl.get("required_level", 0)))

	_left_icon.texture = _load_icon(str(current_tpl.get("icon", inst.get("icon", ""))))
	_left_name.text = str(current_tpl.get("name", inst.get("name", "当前装备")))
	_left_meta.text = "当前：%s  %s阶\n等级段：%d\n星级：+%d" % [
		_slot_name(str(current_tpl.get("slot", inst.get("slot", "")))),
		current_rank,
		current_level,
		int(inst.get("star_level", 0)),
	]

	if target_tpl.is_empty():
		_right_icon.texture = null
		_right_name.text = "暂无下一阶"
		_right_meta.text = "当前装备已满阶或未配置目标模板"
		return
	_right_icon.texture = _load_icon(str(target_tpl.get("icon", "")))
	_right_name.text = str(target_tpl.get("name", "目标装备"))
	_right_meta.text = "目标：%s  %s阶\n等级段：%d\n继承星级：+%d" % [
		_slot_name(str(target_tpl.get("slot", ""))),
		target_rank,
		target_level,
		int(inst.get("star_level", 0)),
	]

func _render_stats() -> void:
	var current_stats_any = _preview.get("current_stats", {})
	var target_stats_any = _preview.get("target_stats", {})
	var current_stats: Dictionary = current_stats_any if current_stats_any is Dictionary else {}
	var target_stats: Dictionary = target_stats_any if target_stats_any is Dictionary else {}
	_stats_now.text = _format_stats_block(current_stats, current_stats, false)
	_stats_next.text = _format_stats_block(target_stats, current_stats, true)

func _render_materials() -> void:
	for child in _material_flow.get_children():
		_material_flow.remove_child(child)
		child.queue_free()

	var costs_any = _preview.get("material_costs", [])
	var costs: Array = costs_any if costs_any is Array else []
	var gold_cost := maxi(0, int(_preview.get("gold_cost", 0)))
	var gold_have := maxi(0, int(PlayerModel.gold))
	_material_flow.add_child(_build_cost_card("金币", "", gold_cost, gold_have, gold_have >= gold_cost))

	for row_any in costs:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		var item_name := str(row.get("name", row.get("item_id", "")))
		var icon_path := str(row.get("icon", ""))
		var need := maxi(0, int(row.get("need", 0)))
		var owned := maxi(0, int(row.get("owned", 0)))
		_material_flow.add_child(_build_cost_card(item_name, icon_path, need, owned, bool(row.get("enough", false))))

func _build_cost_card(name: String, icon_path: String, need: int, owned: int, enough: bool) -> Control:
	var panel := PanelContainer.new()
	panel.custom_minimum_size = Vector2(120, 132)
	panel.size_flags_horizontal = Control.SIZE_SHRINK_CENTER

	var box := VBoxContainer.new()
	box.alignment = BoxContainer.ALIGNMENT_CENTER
	box.add_theme_constant_override("separation", 6)
	panel.add_child(box)

	var icon := TextureRect.new()
	icon.custom_minimum_size = Vector2(54, 54)
	icon.expand_mode = TextureRect.EXPAND_IGNORE_SIZE
	icon.stretch_mode = TextureRect.STRETCH_KEEP_ASPECT_CENTERED
	icon.texture = _load_icon(icon_path)
	box.add_child(icon)

	var name_label := Label.new()
	name_label.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	name_label.vertical_alignment = VERTICAL_ALIGNMENT_CENTER
	name_label.autowrap_mode = TextServer.AUTOWRAP_WORD_SMART
	name_label.add_theme_font_size_override("font_size", 18)
	name_label.text = name
	box.add_child(name_label)

	var count_label := Label.new()
	count_label.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	count_label.add_theme_font_size_override("font_size", 18)
	count_label.text = "%d / %d" % [owned, need]
	count_label.modulate = Color(0.75, 1.0, 0.75, 1.0) if enough else Color(1.0, 0.55, 0.55, 1.0)
	box.add_child(count_label)
	return panel

func _refresh_status() -> void:
	var ok := bool(_preview.get("ok", false))
	var reason := str(_preview.get("reason", "")).strip_edges()
	_btn_rank_up.disabled = not ok
	_btn_rank_up.text = "开始升阶" if ok else "不可升阶"
	match reason:
		"":
			_status_label.text = "升阶将替换为下一阶模板，并保留成长状态。"
			_status_label.modulate = Color(0.8, 0.92, 1.0, 1.0)
		"blue_gear":
			_status_label.text = "蓝装不可升阶"
			_status_label.modulate = Color(1.0, 0.6, 0.6, 1.0)
		"no_target":
			_status_label.text = "已满阶或未配置下一阶模板"
			_status_label.modulate = Color(1.0, 0.6, 0.6, 1.0)
		"no_recipe":
			_status_label.text = "缺少升阶配置"
			_status_label.modulate = Color(1.0, 0.6, 0.6, 1.0)
		"level_low":
			_status_label.text = "等级不足：需 Lv%d" % int(_preview.get("required_level", 0))
			_status_label.modulate = Color(1.0, 0.6, 0.6, 1.0)
		"no_gold":
			_status_label.text = "金币不足"
			_status_label.modulate = Color(1.0, 0.6, 0.6, 1.0)
		"no_material":
			_status_label.text = "升阶材料不足"
			_status_label.modulate = Color(1.0, 0.6, 0.6, 1.0)
		_:
			_status_label.text = "当前不可升阶"
			_status_label.modulate = Color(1.0, 0.6, 0.6, 1.0)

func _format_stats_block(target_stats: Dictionary, base_stats: Dictionary, compare: bool) -> String:
	var lines: Array[String] = []
	for stat in SHOW_STATS_ORDER:
		var target := int(target_stats.get(stat, 0))
		var base := int(base_stats.get(stat, 0))
		if target == 0 and base == 0:
			continue
		lines.append(_format_stat_line(stat, base, target, compare))
	for stat_any in target_stats.keys():
		var stat := str(stat_any)
		if SHOW_STATS_ORDER.find(stat) != -1:
			continue
		var target := int(target_stats.get(stat, 0))
		var base := int(base_stats.get(stat, 0))
		if target == 0 and base == 0:
			continue
		lines.append(_format_stat_line(stat, base, target, compare))
	if lines.is_empty():
		return "—"
	return "\n".join(lines)

func _format_stat_line(stat: String, base_value: int, target_value: int, compare: bool) -> String:
	var is_percent := stat == "CRIT_PERCENT" or stat == "LOOT_BONUS_PERCENT"
	var label := I18nService.stat(stat)
	var suffix := "%" if is_percent else ""
	if not compare:
		return "%s：%d%s" % [label, target_value, suffix]
	if target_value > base_value:
		return "%s：%d%s [color=#7CFF8A]-> %d%s[/color]" % [label, base_value, suffix, target_value, suffix]
	return "%s：%d%s -> %d%s" % [label, base_value, suffix, target_value, suffix]

func _slot_name(slot: String) -> String:
	return I18nService.t("slot.%s" % slot, slot)

func _load_icon(path: String) -> Texture2D:
	if path.is_empty():
		return null
	if _icon_cache.has(path):
		var cached = _icon_cache[path]
		if cached is Texture2D:
			return cached
	var loaded := load(path)
	if loaded is Texture2D:
		_icon_cache[path] = loaded
		return loaded
	return null

func _on_rank_up_pressed() -> void:
	if _uid <= 0:
		return
	var ret := EquipmentRankUpService.perform_rank_up(_uid)
	if not bool(ret.get("ok", false)):
		refresh()
		return
	refresh()
	close()

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
