extends Control

const SHOW_STATS_ORDER := ["HP", "ATK", "DEF", "CRIT_PERCENT", "LOOT_BONUS_PERCENT", "QI"]

@onready var _dim_bg: ColorRect = $DimBG
@onready var _title: Label = $Panel/VBox/TopBar/Title
@onready var _btn_close: Button = $Panel/VBox/TopBar/BtnClose
@onready var _equip_icon: TextureRect = $Panel/VBox/EquipHead/EquipIcon
@onready var _equip_info: Label = $Panel/VBox/EquipHead/EquipInfo
@onready var _stats_now: RichTextLabel = $Panel/VBox/StatsRow/NowBox/NowText
@onready var _stats_next: RichTextLabel = $Panel/VBox/StatsRow/NextBox/NextText
@onready var _materials: VBoxContainer = $Panel/VBox/MaterialScroll/MaterialList
@onready var _gold_line: Label = $Panel/VBox/GoldLine
@onready var _hint: Label = $Panel/VBox/Hint
@onready var _btn_auto: Button = $Panel/VBox/ActionBar/BtnAuto
@onready var _btn_star: Button = $Panel/VBox/ActionBar/BtnStar
@onready var _btn_cancel: Button = $Panel/VBox/ActionBar/BtnCancel

var _uid := 0
var _preview: Dictionary = {}
var _icon_cache: Dictionary = {}

func _ready() -> void:
	visible = false
	_stats_now.bbcode_enabled = true
	_stats_next.bbcode_enabled = true
	_title.text = "升星"
	_btn_close.text = I18nService.t("ui.btn.close", "关闭")
	_btn_auto.text = "自动选材"
	_btn_star.text = "升星"
	_btn_cancel.text = I18nService.t("ui.btn.cancel", "取消")
	if not _btn_close.pressed.is_connected(close):
		_btn_close.pressed.connect(close)
	if not _btn_cancel.pressed.is_connected(close):
		_btn_cancel.pressed.connect(close)
	if not _btn_auto.pressed.is_connected(_on_auto_pressed):
		_btn_auto.pressed.connect(_on_auto_pressed)
	if not _btn_star.pressed.is_connected(_on_star_pressed):
		_btn_star.pressed.connect(_on_star_pressed)
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

	_preview = EquipmentModel.get_star_up_preview(_uid)
	var current_star := int(_preview.get("current_star", 0))
	var target_star := int(_preview.get("target_star", current_star))
	var star_max := int(_preview.get("star_max", 10))
	var rarity := str(inst.get("rarity", "white"))
	var slot := str(inst.get("slot", ""))
	var icon_path := str(inst.get("icon", ""))
	_equip_icon.texture = _load_icon(icon_path)
	var level_text := ""
	if inst.has("level") or inst.has("require_level"):
		level_text = "\n等级：%d" % int(inst.get("level", inst.get("require_level", 0)))
	_equip_info.text = "%s  [%s]  %s%s\n星级：+%d/%d" % [
		str(inst.get("name", str(inst.get("template_id", "装备")))),
		_rarity_name(rarity),
		I18nService.t("slot.%s" % slot, slot),
		level_text,
		current_star,
		star_max,
	]

	var current_stats_any = _preview.get("current_stats", {})
	var next_stats_any = _preview.get("next_stats", {})
	var current_stats: Dictionary = current_stats_any if current_stats_any is Dictionary else {}
	var next_stats: Dictionary = next_stats_any if next_stats_any is Dictionary else {}
	_stats_now.text = _format_stats_block(current_stats)
	_stats_next.text = _format_stats_block(next_stats)

	var material_options_any = _preview.get("material_options", [])
	var owned_materials_any = _preview.get("owned_materials", {})
	var material_options: Array = material_options_any if material_options_any is Array else []
	var owned_materials: Dictionary = owned_materials_any if owned_materials_any is Dictionary else {}
	_rebuild_material_list(material_options, owned_materials)

	var need_gold := maxi(0, int(_preview.get("required_gold", 0)))
	var have_gold := maxi(0, int(PlayerModel.gold))
	_gold_line.text = "金币：需要 %d / 拥有 %d" % [need_gold, have_gold]
	_gold_line.modulate = Color(0.8, 1.0, 0.8, 1.0) if have_gold >= need_gold else Color(1.0, 0.65, 0.65, 1.0)

	var can_star := bool(_preview.get("ok", false))
	_btn_star.disabled = not can_star
	_btn_auto.disabled = material_options.is_empty()
	if can_star:
		_hint.text = "升星 100% 成功"
		_hint.modulate = Color(0.8, 0.9, 1.0, 1.0)
	elif current_star >= star_max:
		_hint.text = "已达到最大星级"
		_hint.modulate = Color(0.85, 0.85, 0.85, 1.0)
	else:
		var reason := str(_preview.get("reason", ""))
		if reason == "no_gold":
			_hint.text = "金币不足"
		elif reason == "no_material":
			_hint.text = "材料不足"
		elif reason == "no_rule":
			_hint.text = "未配置升星规则"
		elif reason == "no_material_option":
			_hint.text = "未配置升星材料"
		else:
			_hint.text = "当前不可升星"
		_hint.modulate = Color(1.0, 0.65, 0.65, 1.0)

	if target_star > current_star:
		_btn_star.text = "升至 +%d" % target_star
	else:
		_btn_star.text = "升星"

func _rebuild_material_list(material_options: Array, owned_materials: Dictionary) -> void:
	for child in _materials.get_children():
		_materials.remove_child(child)
		child.queue_free()

	if material_options.is_empty():
		var empty := Label.new()
		empty.text = "无可用材料配置"
		empty.modulate = Color(0.85, 0.85, 0.85, 1.0)
		empty.add_theme_font_size_override("font_size", 18)
		_materials.add_child(empty)
		return

	for i in range(material_options.size()):
		var option_any = material_options[i]
		if not (option_any is Dictionary):
			continue
		var option: Dictionary = option_any
		var item_id := str(option.get("item_id", "")).strip_edges()
		var need := maxi(0, int(option.get("count", 0)))
		if item_id.is_empty() or need <= 0:
			continue
		var have := maxi(0, int(owned_materials.get(item_id, InventoryModel.get_count(item_id))))
		var item_def := _find_item_def(item_id)
		var item_name := str(item_def.get("name", item_id))
		var icon_path := str(item_def.get("icon", ""))

		var row := HBoxContainer.new()
		row.custom_minimum_size = Vector2(0, 44)
		row.size_flags_horizontal = Control.SIZE_EXPAND_FILL
		row.add_theme_constant_override("separation", 8)

		var icon := TextureRect.new()
		icon.custom_minimum_size = Vector2(32, 32)
		icon.expand_mode = TextureRect.EXPAND_IGNORE_SIZE
		icon.stretch_mode = TextureRect.STRETCH_KEEP_ASPECT_CENTERED
		icon.texture = _load_icon(icon_path)
		row.add_child(icon)

		var name := Label.new()
		name.size_flags_horizontal = Control.SIZE_EXPAND_FILL
		name.vertical_alignment = VERTICAL_ALIGNMENT_CENTER
		name.add_theme_font_size_override("font_size", 18)
		name.text = "%s  x%d" % [item_name, need]
		row.add_child(name)

		var own := Label.new()
		own.custom_minimum_size = Vector2(160, 0)
		own.horizontal_alignment = HORIZONTAL_ALIGNMENT_RIGHT
		own.vertical_alignment = VERTICAL_ALIGNMENT_CENTER
		own.add_theme_font_size_override("font_size", 18)
		own.text = "拥有 %d" % have
		own.modulate = Color(0.75, 1.0, 0.75, 1.0) if have >= need else Color(1.0, 0.65, 0.65, 1.0)
		row.add_child(own)

		_materials.add_child(row)

func _format_stats_block(stats: Dictionary) -> String:
	var lines: Array[String] = []
	for key in SHOW_STATS_ORDER:
		var stat := str(key)
		if not stats.has(stat):
			continue
		var val := int(stats.get(stat, 0))
		if val == 0:
			continue
		lines.append(_format_stat_line(stat, val))
	for key_any in stats.keys():
		var stat := str(key_any)
		if SHOW_STATS_ORDER.find(stat) != -1:
			continue
		var val := int(stats.get(key_any, 0))
		if val == 0:
			continue
		lines.append(_format_stat_line(stat, val))
	if lines.is_empty():
		return "—"
	return "\n".join(lines)

func _format_stat_line(stat: String, val: int) -> String:
	var is_percent := stat == "CRIT_PERCENT" or stat == "LOOT_BONUS_PERCENT"
	return "%s +%d%s" % [I18nService.stat(stat), val, "%" if is_percent else ""]

func _find_item_def(item_id: String) -> Dictionary:
	if item_id.is_empty():
		return {}
	var cfg: Dictionary = ConfigService.get_cfg()
	var db_any = cfg.get("items_db", {})
	if not (db_any is Dictionary):
		return {}
	var rows_any = (db_any as Dictionary).get("items", [])
	if not (rows_any is Array):
		return {}
	for row_any in rows_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		if str(row.get("id", "")).strip_edges() == item_id:
			return row
	return {}

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

func _on_auto_pressed() -> void:
	if _uid <= 0:
		return
	if _preview.is_empty():
		refresh()
	if bool(_preview.get("ok", false)):
		EventBus.add_log("已自动选择可用升星材料")
	else:
		EventBus.add_log("当前无可用自动选材")

func _on_star_pressed() -> void:
	if _uid <= 0:
		return
	var ret: Dictionary = EquipmentModel.do_star_up(_uid)
	if bool(ret.get("ok", false)):
		var from_star := int(ret.get("current_star", 0))
		var to_star := int(ret.get("target_star", from_star))
		var gold := maxi(0, int(ret.get("required_gold", 0)))
		var selected_any = ret.get("selected_option", {})
		var selected: Dictionary = selected_any if selected_any is Dictionary else {}
		var item_id := str(selected.get("item_id", "")).strip_edges()
		var count := maxi(0, int(selected.get("count", 0)))
		var line := "升星成功：+%d→+%d（金币-%d" % [from_star, to_star, gold]
		if not item_id.is_empty() and count > 0:
			var item_name := str(_find_item_def(item_id).get("name", item_id))
			line += "，%s-%d" % [item_name, count]
		line += "）"
		EventBus.add_log(line)
		refresh()
		return
	var reason := str(ret.get("reason", ""))
	if reason == "no_gold":
		EventBus.add_log("金币不足")
	elif reason == "no_material":
		EventBus.add_log("升星材料不足")
	elif reason == "max":
		EventBus.add_log("已达到最大星级")
	else:
		EventBus.add_log("升星失败")

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

func _rarity_name(rarity: String) -> String:
	match rarity.to_lower():
		"orange":
			return "橙色"
		"purple":
			return "紫色"
		"gold":
			return "金色"
		"blue":
			return "蓝色"
		_:
			return "白色"
