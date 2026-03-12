extends Control

const PAGE_BATTLE := "res://ui/pages/PageBattle.tscn"

@onready var _dim_bg: ColorRect = $DimBG
@onready var _title: Label = $Panel/VBox/TopBar/Title
@onready var _btn_close: Button = $Panel/VBox/TopBar/BtnClose
@onready var _stage_name: Label = $Panel/VBox/StageName
@onready var _power_line: Label = $Panel/VBox/PowerLine
@onready var _difficulty_list: VBoxContainer = $Panel/VBox/ListScroll/DifficultyList
@onready var _upgrade_box: VBoxContainer = $Panel/VBox/UpgradeBox
@onready var _btn_cancel: Button = $Panel/VBox/BottomButtons/BtnCancel

var _stage_def: Dictionary = {}
var _stage_id: String = ""
var _preferred_diff_index := -1

func _ready() -> void:
	visible = false
	_title.text = "选择难度"
	_btn_close.text = I18nService.t("ui.btn.close", "关闭")
	_btn_cancel.text = I18nService.t("ui.btn.cancel", "取消")
	_upgrade_box.visible = false
	if not _btn_close.pressed.is_connected(close):
		_btn_close.pressed.connect(close)
	if not _btn_cancel.pressed.is_connected(close):
		_btn_cancel.pressed.connect(close)
	if not _dim_bg.gui_input.is_connected(_on_dim_bg_gui_input):
		_dim_bg.gui_input.connect(_on_dim_bg_gui_input)

func open(stage_def: Dictionary) -> void:
	open_with_target(stage_def, -1)

func open_with_target(stage_def: Dictionary, preferred_diff_index: int = -1) -> void:
	_stage_def = stage_def.duplicate(true)
	_stage_id = str(_stage_def.get("id", "")).strip_edges()
	_preferred_diff_index = preferred_diff_index
	if _stage_id.is_empty():
		return
	visible = true
	_rebuild_all()

func close() -> void:
	visible = false
	_preferred_diff_index = -1

func _rebuild_all() -> void:
	var stage_name := str(_stage_def.get("name", _stage_id))
	_stage_name.text = "地图：%s" % stage_name
	_upgrade_box.visible = false
	_rebuild_list()

func _rebuild_list() -> void:
	for child in _difficulty_list.get_children():
		_difficulty_list.remove_child(child)
		child.queue_free()

	var diffs := _resolved_difficulties(_stage_def)
	var score := EquipmentModel.get_equipped_total_score()
	var highest_recommend := 0
	for diff_any in diffs:
		if diff_any is Dictionary:
			highest_recommend = maxi(highest_recommend, int((diff_any as Dictionary).get("recommended_power", 0)))
	_power_line.text = "当前战力%d / 最高建议战力%d" % [score, highest_recommend]

	for i in range(diffs.size()):
		var diff_any = diffs[i]
		if not (diff_any is Dictionary):
			continue
		var diff: Dictionary = diff_any
		var is_target := _preferred_diff_index >= 0 and i == _preferred_diff_index
		_difficulty_list.add_child(_build_diff_card(diff, i, score, is_target))

func _build_diff_card(diff: Dictionary, diff_index: int, score: int, is_target: bool) -> Control:
	var name := str(diff.get("difficulty_name", "难度%d" % (diff_index + 1)))
	var rec := maxi(0, int(diff.get("recommended_power", 0)))

	var panel := PanelContainer.new()
	panel.name = "DiffCard_%d" % diff_index
	panel.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	panel.add_theme_stylebox_override("panel", _build_card_style(is_target))

	var card := VBoxContainer.new()
	card.custom_minimum_size = Vector2(0, 160)
	card.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	card.add_theme_constant_override("separation", 4)
	panel.add_child(card)

	var top := HBoxContainer.new()
	top.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	top.add_theme_constant_override("separation", 10)
	card.add_child(top)

	var left := VBoxContainer.new()
	left.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	left.add_theme_constant_override("separation", 2)
	top.add_child(left)

	var name_label := Label.new()
	name_label.text = name
	name_label.add_theme_font_size_override("font_size", 24)
	left.add_child(name_label)

	var rec_label := Label.new()
	rec_label.add_theme_font_size_override("font_size", 18)
	rec_label.text = "建议战力：%d" % rec
	rec_label.modulate = Color(1.0, 0.75, 0.75, 1.0) if score < rec else Color(0.76, 0.76, 0.76, 1.0)
	left.add_child(rec_label)

	var enter_btn := Button.new()
	enter_btn.custom_minimum_size = Vector2(120, 46)
	enter_btn.text = "进入目标" if is_target else "进入"
	enter_btn.pressed.connect(_on_enter_pressed.bind(diff_index))
	top.add_child(enter_btn)

	card.add_child(_build_info_line("刷怪参数：%.2f秒 / 同屏%d / 半径%d" % [
		float(diff.get("spawn_interval", 1.6)),
		maxi(1, int(diff.get("onscreen_limit", 1))),
		maxi(1, int(diff.get("spawn_radius", 1))),
	]))
	card.add_child(_build_info_line("普通怪：%s" % _monster_pool_preview(diff.get("normal_monsters", []))))
	card.add_child(_build_info_line("精英怪：%s" % _monster_pool_preview(diff.get("elite_monsters", []))))
	card.add_child(_build_info_line("Boss：%s" % _monster_pool_preview(diff.get("boss_monsters", []))))

	return panel

func _build_info_line(text: String) -> Label:
	var label := Label.new()
	label.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	label.autowrap_mode = TextServer.AUTOWRAP_WORD_SMART
	label.add_theme_font_size_override("font_size", 15)
	label.modulate = Color(0.78, 0.78, 0.78, 1.0)
	label.text = text
	return label

func _monster_pool_preview(pool_any: Variant) -> String:
	if not (pool_any is Array):
		return "未配置"
	var pool: Array = pool_any
	if pool.is_empty():
		return "未配置"
	var names: Array[String] = []
	for row_any in pool:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		var monster_id := str(row.get("monster_id", "")).strip_edges()
		if monster_id.is_empty():
			continue
		var name := _monster_name(monster_id)
		var weight := maxi(0, int(row.get("weight", 0)))
		names.append("%s(%d)" % [name if not name.is_empty() else monster_id, weight])
		if names.size() >= 3:
			break
	if names.is_empty():
		return "未配置"
	return "、".join(names)

func _monster_name(monster_id: String) -> String:
	if monster_id.is_empty():
		return ""
	var cfg: Dictionary = ConfigService.get_cfg()
	var monsters_db_any = cfg.get("monsters_db", {})
	if not (monsters_db_any is Dictionary):
		return monster_id
	var monsters_any = (monsters_db_any as Dictionary).get("monsters", [])
	if not (monsters_any is Array):
		return monster_id
	for row_any in monsters_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		if str(row.get("id", "")).strip_edges() != monster_id:
			continue
		var name := str(row.get("name", monster_id)).strip_edges()
		return name if not name.is_empty() else monster_id
	return monster_id

func _build_card_style(is_target: bool) -> StyleBoxFlat:
	var sb := StyleBoxFlat.new()
	sb.bg_color = Color(0.14, 0.14, 0.16, 0.92)
	sb.border_color = Color(0.36, 0.36, 0.40, 1.0)
	if is_target:
		sb.border_color = Color(0.95, 0.80, 0.38, 1.0)
	sb.border_width_left = 1
	sb.border_width_top = 1
	sb.border_width_right = 1
	sb.border_width_bottom = 1
	sb.corner_radius_top_left = 8
	sb.corner_radius_top_right = 8
	sb.corner_radius_bottom_left = 8
	sb.corner_radius_bottom_right = 8
	return sb

func _resolved_difficulties(stage: Dictionary) -> Array:
	var out: Array = []
	var diffs_any = stage.get("difficulties", [])
	if diffs_any is Array:
		for diff_any in diffs_any:
			if diff_any is Dictionary:
				out.append((diff_any as Dictionary).duplicate(true))
	if out.is_empty():
		out.append({
			"difficulty_name": "普通",
			"recommended_power": 0,
			"spawn_interval": 1.6,
			"onscreen_limit": 10,
			"spawn_radius": 220,
			"normal_monsters": [],
			"elite_monsters": [],
			"boss_monsters": [],
		})
	return out

func _on_enter_pressed(diff_index: int) -> void:
	if _stage_id.is_empty():
		return
	BattleService.set_stage(_stage_id, diff_index, false)
	close()
	get_tree().change_scene_to_file(PAGE_BATTLE)

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
