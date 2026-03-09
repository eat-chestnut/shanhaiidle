extends Control

const PAGE_BATTLE := "res://ui/pages/PageBattle.tscn"

@onready var _dim_bg: ColorRect = $DimBG
@onready var _title: Label = $Panel/VBox/TopBar/Title
@onready var _btn_close: Button = $Panel/VBox/TopBar/BtnClose
@onready var _stage_name: Label = $Panel/VBox/StageName
@onready var _power_line: Label = $Panel/VBox/PowerLine
@onready var _difficulty_list: VBoxContainer = $Panel/VBox/ListScroll/DifficultyList
@onready var _upgrade_box: VBoxContainer = $Panel/VBox/UpgradeBox
@onready var _next_info: Label = $Panel/VBox/UpgradeBox/NextInfo
@onready var _need_kills: Label = $Panel/VBox/UpgradeBox/NeedKills
@onready var _need_items: Label = $Panel/VBox/UpgradeBox/NeedItems
@onready var _btn_upgrade: Button = $Panel/VBox/UpgradeBox/BtnUpgrade
@onready var _btn_cancel: Button = $Panel/VBox/BottomButtons/BtnCancel

var _stage_def: Dictionary = {}
var _stage_id: String = ""

func _ready() -> void:
	visible = false
	_title.text = "选择难度"
	_btn_close.text = I18nService.t("ui.btn.close", "关闭")
	_btn_cancel.text = I18nService.t("ui.btn.cancel", "取消")
	_btn_upgrade.text = "升级解锁"
	if not _btn_close.pressed.is_connected(close):
		_btn_close.pressed.connect(close)
	if not _btn_cancel.pressed.is_connected(close):
		_btn_cancel.pressed.connect(close)
	if not _btn_upgrade.pressed.is_connected(_on_upgrade_pressed):
		_btn_upgrade.pressed.connect(_on_upgrade_pressed)
	if not _dim_bg.gui_input.is_connected(_on_dim_bg_gui_input):
		_dim_bg.gui_input.connect(_on_dim_bg_gui_input)

func open(stage_def: Dictionary) -> void:
	_stage_def = stage_def.duplicate(true)
	_stage_id = str(_stage_def.get("id", "")).strip_edges()
	if _stage_id.is_empty():
		return
	visible = true
	_rebuild_all()

func close() -> void:
	visible = false

func _rebuild_all() -> void:
	var stage_name := str(_stage_def.get("name", _stage_id))
	_stage_name.text = "地图：%s" % stage_name
	_rebuild_list()
	_rebuild_upgrade_box()

func _rebuild_list() -> void:
	for child in _difficulty_list.get_children():
		_difficulty_list.remove_child(child)
		child.queue_free()

	var diffs := _resolved_difficulties(_stage_def)
	if diffs.is_empty():
		_power_line.text = "当前战力0 / 建议战力0"
		return

	var unlocked := _get_unlocked_diff_clamped(diffs.size())
	var score := EquipmentModel.get_equipped_total_score()
	var recommend := int((diffs[mini(unlocked, diffs.size() - 1)] as Dictionary).get("recommend_score", 0))
	_power_line.text = "当前战力%d / 建议战力%d" % [score, recommend]

	for i in range(diffs.size()):
		var diff_any = diffs[i]
		if not (diff_any is Dictionary):
			continue
		var diff: Dictionary = diff_any
		var name := str(diff.get("name", "难度%d" % i))
		var rec := maxi(0, int(diff.get("recommend_score", 0)))
		var is_unlocked := i <= unlocked

		var panel := PanelContainer.new()
		panel.size_flags_horizontal = Control.SIZE_EXPAND_FILL
		var sb := StyleBoxFlat.new()
		sb.bg_color = Color(0.14, 0.14, 0.16, 0.92)
		sb.border_color = Color(0.36, 0.36, 0.40, 1.0)
		sb.border_width_left = 1
		sb.border_width_top = 1
		sb.border_width_right = 1
		sb.border_width_bottom = 1
		sb.corner_radius_top_left = 8
		sb.corner_radius_top_right = 8
		sb.corner_radius_bottom_left = 8
		sb.corner_radius_bottom_right = 8
		panel.add_theme_stylebox_override("panel", sb)

		var row := HBoxContainer.new()
		row.custom_minimum_size = Vector2(0, 88)
		row.size_flags_horizontal = Control.SIZE_EXPAND_FILL
		row.add_theme_constant_override("separation", 10)
		panel.add_child(row)

		var left := VBoxContainer.new()
		left.size_flags_horizontal = Control.SIZE_EXPAND_FILL
		left.alignment = BoxContainer.ALIGNMENT_CENTER
		left.add_theme_constant_override("separation", 2)
		row.add_child(left)

		var name_lb := Label.new()
		name_lb.text = name
		name_lb.add_theme_font_size_override("font_size", 24)
		left.add_child(name_lb)

		var rec_lb := Label.new()
		rec_lb.add_theme_font_size_override("font_size", 18)
		if score < rec:
			rec_lb.text = "建议战力：%d（建议不足）" % rec
			rec_lb.modulate = Color(1.0, 0.75, 0.75, 1.0)
		else:
			rec_lb.text = "建议战力：%d" % rec
			rec_lb.modulate = Color(0.76, 0.76, 0.76, 1.0)
		left.add_child(rec_lb)

		var enter_btn := Button.new()
		enter_btn.custom_minimum_size = Vector2(120, 46)
		enter_btn.text = "进入" if is_unlocked else "未解锁"
		enter_btn.disabled = not is_unlocked
		if is_unlocked:
			enter_btn.pressed.connect(_on_enter_pressed.bind(i))
		row.add_child(enter_btn)

		_difficulty_list.add_child(panel)

func _rebuild_upgrade_box() -> void:
	var diffs := _resolved_difficulties(_stage_def)
	var unlocked := _get_unlocked_diff_clamped(diffs.size())
	var next := unlocked + 1
	if next >= diffs.size():
		_upgrade_box.visible = false
		return
	_upgrade_box.visible = true

	var next_diff_any = diffs[next]
	var next_diff: Dictionary = next_diff_any if next_diff_any is Dictionary else {}
	var next_name := str(next_diff.get("name", "困难"))
	var chk := MapProgressModel.can_upgrade(_stage_id, next, _stage_def)

	var need_kills := maxi(0, int(chk.get("need_kills", 0)))
	var have_kills := maxi(0, int(chk.get("have_kills", 0)))
	_next_info.text = "升级解锁：%s" % next_name
	_need_kills.text = "Boss击杀：%d/%d" % [have_kills, need_kills]

	var need_items_any = chk.get("need_items", {})
	var need_items: Dictionary = need_items_any if need_items_any is Dictionary else {}
	_need_items.text = _build_need_items_text(need_items)

	_btn_upgrade.disabled = not bool(chk.get("ok", false))
	_btn_upgrade.text = "升级解锁" if bool(chk.get("ok", false)) else "升级条件不足"

func _build_need_items_text(need_items: Dictionary) -> String:
	if need_items.is_empty():
		return "材料：无"
	var parts: Array[String] = []
	for item_id_any in need_items.keys():
		var item_id := str(item_id_any).strip_edges()
		var need := maxi(0, int(need_items.get(item_id_any, 0)))
		if item_id.is_empty() or need <= 0:
			continue
		var have := maxi(0, InventoryModel.get_count(item_id))
		parts.append("%s %d/%d" % [item_id, have, need])
	if parts.is_empty():
		return "材料：无"
	return "材料：" + "，".join(parts)

func _on_enter_pressed(diff_index: int) -> void:
	if _stage_id.is_empty():
		return
	BattleService.set_stage(_stage_id, diff_index, false)
	close()
	get_tree().change_scene_to_file(PAGE_BATTLE)

func _on_upgrade_pressed() -> void:
	if _stage_id.is_empty():
		return
	var ret := MapProgressModel.upgrade(_stage_id, _stage_def)
	if not bool(ret.get("ok", false)):
		EventBus.add_log("升级条件不足")
		_rebuild_upgrade_box()
		return
	var new_diff := maxi(0, int(ret.get("new_diff", 0)))
	var diffs := _resolved_difficulties(_stage_def)
	var diff_name := "困难"
	if new_diff >= 0 and new_diff < diffs.size():
		var row_any = diffs[new_diff]
		if row_any is Dictionary:
			diff_name = str((row_any as Dictionary).get("name", diff_name))
	EventBus.add_log("地图升级成功：%s 解锁%s" % [str(_stage_def.get("name", _stage_id)), diff_name])
	_rebuild_all()

func _get_unlocked_diff_clamped(diff_count: int) -> int:
	if diff_count <= 0:
		return 0
	var unlocked := maxi(0, MapProgressModel.get_unlocked_diff(_stage_id))
	return mini(unlocked, diff_count - 1)

func _resolved_difficulties(stage: Dictionary) -> Array:
	var out: Array = []
	var diffs_any = stage.get("difficulties", [])
	if diffs_any is Array:
		for diff_any in diffs_any:
			if diff_any is Dictionary:
				out.append((diff_any as Dictionary).duplicate(true))
	if out.is_empty():
		out.append({
			"name": "普通",
			"unlock": {
				"boss_kills_required": 0,
				"material_cost": {},
			},
			"recommend_score": 0,
			"monster_mult": {
				"hp": 1.0,
				"atk": 1.0,
				"def": 1.0,
			},
			"drops_override": {},
		})
	return out

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
