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
	var base_drops_any = _stage_def.get("drops_patch", {})
	var base_drops: Dictionary = base_drops_any if base_drops_any is Dictionary else {}

	for i in range(diffs.size()):
		var diff_any = diffs[i]
		if not (diff_any is Dictionary):
			continue
		var diff: Dictionary = diff_any
		var name := str(diff.get("name", "难度%d" % i))
		var rec := maxi(0, int(diff.get("recommend_score", 0)))
		var is_unlocked := i <= unlocked

		var panel := PanelContainer.new()
		panel.name = "DiffCard_%d" % i
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

		var card := VBoxContainer.new()
		card.custom_minimum_size = Vector2(0, 120)
		card.size_flags_horizontal = Control.SIZE_EXPAND_FILL
		card.add_theme_constant_override("separation", 2)
		panel.add_child(card)

		var row := HBoxContainer.new()
		row.name = "TopRow"
		row.custom_minimum_size = Vector2(0, 72)
		row.size_flags_horizontal = Control.SIZE_EXPAND_FILL
		row.add_theme_constant_override("separation", 10)
		card.add_child(row)

		var left := VBoxContainer.new()
		left.size_flags_horizontal = Control.SIZE_EXPAND_FILL
		left.alignment = BoxContainer.ALIGNMENT_CENTER
		left.add_theme_constant_override("separation", 2)
		row.add_child(left)

		var name_lb := Label.new()
		name_lb.name = "LblName"
		name_lb.text = name
		name_lb.add_theme_font_size_override("font_size", 24)
		left.add_child(name_lb)

		var rec_lb := Label.new()
		rec_lb.name = "LblRec"
		rec_lb.add_theme_font_size_override("font_size", 18)
		if score < rec:
			rec_lb.text = "建议战力：%d（建议不足）" % rec
			rec_lb.modulate = Color(1.0, 0.75, 0.75, 1.0)
		else:
			rec_lb.text = "建议战力：%d" % rec
			rec_lb.modulate = Color(0.76, 0.76, 0.76, 1.0)
		left.add_child(rec_lb)

		var enter_btn := Button.new()
		enter_btn.name = "BtnEnter"
		enter_btn.custom_minimum_size = Vector2(120, 46)
		enter_btn.text = "进入" if is_unlocked else "未解锁"
		enter_btn.disabled = not is_unlocked
		if is_unlocked:
			enter_btn.pressed.connect(_on_enter_pressed.bind(i))
		row.add_child(enter_btn)

		var override_any = diff.get("drops_override", {})
		var override_drops: Dictionary = override_any if override_any is Dictionary else {}
		var final_drops := _merge_drops(base_drops, override_drops)
		var weights_any = final_drops.get("rarity_weights", {})
		var special_any = final_drops.get("special", {})
		var weights: Dictionary = weights_any if weights_any is Dictionary else {}
		var special: Dictionary = special_any if special_any is Dictionary else {}

		var drop1_lb := Label.new()
		drop1_lb.name = "LblDrop1"
		drop1_lb.size_flags_horizontal = Control.SIZE_EXPAND_FILL
		drop1_lb.horizontal_alignment = HORIZONTAL_ALIGNMENT_LEFT
		drop1_lb.clip_text = true
		drop1_lb.add_theme_font_size_override("font_size", 14)
		drop1_lb.modulate = Color(0.78, 0.78, 0.78, 1.0)
		drop1_lb.text = _weights_to_percent_text(weights)
		card.add_child(drop1_lb)

		var drop2_lb := Label.new()
		drop2_lb.name = "LblDrop2"
		drop2_lb.size_flags_horizontal = Control.SIZE_EXPAND_FILL
		drop2_lb.horizontal_alignment = HORIZONTAL_ALIGNMENT_LEFT
		drop2_lb.clip_text = true
		drop2_lb.add_theme_font_size_override("font_size", 14)
		drop2_lb.modulate = Color(0.78, 0.78, 0.78, 1.0)
		drop2_lb.text = _special_preview_text(special)
		card.add_child(drop2_lb)

		_difficulty_list.add_child(panel)

func _merge_drops(stage_drops: Dictionary, override: Dictionary) -> Dictionary:
	var result := stage_drops.duplicate(true)

	if override.has("drop_chance"):
		result["drop_chance"] = float(override.get("drop_chance", result.get("drop_chance", 0.0)))

	if override.has("rarity_weights"):
		var base_weights_any = result.get("rarity_weights", {})
		var base_weights: Dictionary = base_weights_any if base_weights_any is Dictionary else {}
		var out_weights := base_weights.duplicate(true)
		var over_weights_any = override.get("rarity_weights", {})
		if over_weights_any is Dictionary:
			var over_weights: Dictionary = over_weights_any
			for k in over_weights.keys():
				out_weights[str(k)] = int(over_weights.get(k, out_weights.get(str(k), 0)))
		result["rarity_weights"] = out_weights

	if override.has("items_by_rarity"):
		var base_items_any = result.get("items_by_rarity", {})
		var base_items: Dictionary = base_items_any if base_items_any is Dictionary else {}
		var out_items := base_items.duplicate(true)
		var over_items_any = override.get("items_by_rarity", {})
		if over_items_any is Dictionary:
			var over_items: Dictionary = over_items_any
			for k in over_items.keys():
				var arr_any = over_items.get(k, [])
				if arr_any is Array:
					out_items[str(k)] = (arr_any as Array).duplicate(true)
		result["items_by_rarity"] = out_items

	if override.has("special"):
		var base_special_any = result.get("special", {})
		var base_special: Dictionary = base_special_any if base_special_any is Dictionary else {}
		var out_special := base_special.duplicate(true)
		var over_special_any = override.get("special", {})
		if over_special_any is Dictionary:
			var over_special: Dictionary = over_special_any
			for kind in ["normal", "elite", "boss"]:
				if not over_special.has(kind):
					continue
				var src_any = over_special.get(kind, {})
				if not (src_any is Dictionary):
					continue
				var src: Dictionary = src_any
				var dst_any = out_special.get(kind, {})
				var dst: Dictionary = dst_any if dst_any is Dictionary else {}
				var merged := dst.duplicate(true)
				for sk in src.keys():
					var sval: Variant = src.get(sk)
					if sval is Array:
						merged[str(sk)] = (sval as Array).duplicate(true)
					elif sval is Dictionary:
						merged[str(sk)] = (sval as Dictionary).duplicate(true)
					else:
						merged[str(sk)] = sval
				out_special[kind] = merged
		result["special"] = out_special

	return result

func _weights_to_percent_text(weights: Dictionary) -> String:
	if weights.is_empty():
		return "稀有度：未知"
	var white_w := int(weights.get("white", 0))
	var blue_w := int(weights.get("blue", 0))
	var gold_w := int(weights.get("gold", 0))
	var sum_w := white_w + blue_w + gold_w
	if sum_w <= 0:
		return "稀有度：未知"
	var pw := int(round(float(white_w) * 100.0 / float(sum_w)))
	var pb := int(round(float(blue_w) * 100.0 / float(sum_w)))
	var pg := maxi(0, 100 - pw - pb)
	return "稀有度：白%d%% 蓝%d%% 金%d%%" % [pw, pb, pg]

func _special_preview_text(special: Dictionary) -> String:
	if special.is_empty():
		return "特殊：未知"
	var elite_any = special.get("elite", {})
	var boss_any = special.get("boss", {})
	if not (elite_any is Dictionary) and not (boss_any is Dictionary):
		return "特殊：未知"
	var elite: Dictionary = elite_any if elite_any is Dictionary else {}
	var boss: Dictionary = boss_any if boss_any is Dictionary else {}
	var elite_punch := float(elite.get("punch_stone_chance", 0.0))
	var boss_punch := float(boss.get("punch_stone_chance", 0.0))
	var elite_gem := float(elite.get("extra_gem_chance", 0.0))
	var boss_gem := float(boss.get("extra_gem_chance", 0.0))
	var boss_core := str(boss.get("core_guarantee", "")).strip_edges()
	var core_txt := "Boss核心必掉" if not boss_core.is_empty() else "Boss核心无"
	return "特殊：精英打孔%.0f%% Boss打孔%.0f%% 宝石(精英%.0f%%/Boss%.0f%%) %s" % [
		elite_punch * 100.0,
		boss_punch * 100.0,
		elite_gem * 100.0,
		boss_gem * 100.0,
		core_txt
	]

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
