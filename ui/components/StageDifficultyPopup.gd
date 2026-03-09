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
@onready var _reward_line: Label = $Panel/VBox/UpgradeBox/RewardLine
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
		card.custom_minimum_size = Vector2(0, 150)
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

		var final_drops := BattleService.get_final_drops_for_stage_difficulty(_stage_def, diff)
		var weights_any = final_drops.get("rarity_weights", {})
		var special_any = final_drops.get("special", {})
		var pools_any = final_drops.get("items_by_rarity", {})
		var weights: Dictionary = weights_any if weights_any is Dictionary else {}
		var special: Dictionary = special_any if special_any is Dictionary else {}
		var pools: Dictionary = pools_any if pools_any is Dictionary else {}

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
		drop2_lb.clip_text = false
		drop2_lb.autowrap_mode = TextServer.AUTOWRAP_WORD_SMART
		drop2_lb.add_theme_font_size_override("font_size", 14)
		drop2_lb.modulate = Color(0.78, 0.78, 0.78, 1.0)
		drop2_lb.text = "%s  %s" % [_items_pool_preview_text(pools), _special_preview_text(special)]
		card.add_child(drop2_lb)

		var first_clear_lb := Label.new()
		first_clear_lb.name = "LblFirstClear"
		first_clear_lb.size_flags_horizontal = Control.SIZE_EXPAND_FILL
		first_clear_lb.horizontal_alignment = HORIZONTAL_ALIGNMENT_LEFT
		first_clear_lb.clip_text = true
		first_clear_lb.add_theme_font_size_override("font_size", 14)
		first_clear_lb.modulate = Color(0.78, 0.78, 0.78, 1.0)
		first_clear_lb.text = _build_first_clear_preview_text(diff, i)
		card.add_child(first_clear_lb)

		_difficulty_list.add_child(panel)

func _weights_to_percent_text(weights: Dictionary) -> String:
	if weights.is_empty():
		return "稀有度：未知"
	var sum_w := 0
	for key_any in weights.keys():
		sum_w += maxi(0, int(weights.get(key_any, 0)))
	if sum_w <= 0:
		return "稀有度：未知"
	var parts: Array[String] = []
	var ordered: Array[String] = ["white", "blue", "gold", "purple", "orange"]
	for key_any in weights.keys():
		var key := str(key_any).strip_edges()
		if key.is_empty() or ordered.has(key):
			continue
		ordered.append(key)
	for rarity in ordered:
		if not weights.has(rarity):
			continue
		var w := maxi(0, int(weights.get(rarity, 0)))
		if w <= 0:
			continue
		var p := int(round(float(w) * 100.0 / float(sum_w)))
		parts.append("%s%d%%" % [_rarity_cn(rarity), p])
	if parts.is_empty():
		return "稀有度：未知"
	return "稀有度：" + " ".join(parts)

func _items_pool_preview_text(pools: Dictionary) -> String:
	if pools.is_empty():
		return "掉落池：未知"
	var parts: Array[String] = []
	var ordered: Array[String] = ["white", "blue", "gold", "purple", "orange"]
	for key_any in pools.keys():
		var key := str(key_any).strip_edges()
		if key.is_empty() or ordered.has(key):
			continue
		ordered.append(key)
	for rarity in ordered:
		if not pools.has(rarity):
			continue
		var arr_any = pools.get(rarity, [])
		if not (arr_any is Array):
			continue
		var arr: Array = arr_any
		if arr.is_empty():
			continue
		var names: Array[String] = []
		for item_any in arr:
			var item_id := str(item_any).strip_edges()
			if item_id.is_empty():
				continue
			names.append(item_id)
			if names.size() >= 2:
				break
		if names.is_empty():
			continue
		var suffix := "…" if arr.size() > names.size() else ""
		parts.append("%s[%s%s]" % [_rarity_cn(rarity), "、".join(names), suffix])
	if parts.is_empty():
		return "掉落池：未知"
	return "掉落池：" + " ".join(parts)

func _rarity_cn(rarity: String) -> String:
	match rarity:
		"white":
			return "白"
		"blue":
			return "蓝"
		"gold":
			return "金"
		"purple":
			return "紫"
		"orange":
			return "橙"
		_:
			return rarity

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
	var chk := MapProgressModel.can_upgrade(_stage_id, _stage_def, next)

	var need_kills := maxi(0, int(chk.get("need_kills", 0)))
	var have_kills := maxi(0, int(chk.get("have_kills", 0)))
	_next_info.text = "升级解锁：%s" % next_name
	_need_kills.text = "Boss击杀：%d/%d" % [have_kills, need_kills]

	var need_items_any = chk.get("need_items", {})
	var need_items: Dictionary = need_items_any if need_items_any is Dictionary else {}
	_need_items.text = _build_need_items_text(need_items)
	_reward_line.text = _build_reward_preview_text(next_diff)

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
	var reward_any = ret.get("reward", {})
	var reward: Dictionary = reward_any if reward_any is Dictionary else {}
	EventBus.add_log(
		"地图升级成功：%s 解锁%s，奖励：%s" % [
			str(_stage_def.get("name", _stage_id)),
			diff_name,
			_build_reward_summary_text(reward),
		]
	)
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
				"reward": {},
			},
			"first_clear_reward": {},
			"recommend_score": 0,
			"monster_mult": {
				"hp": 1.0,
				"atk": 1.0,
				"def": 1.0,
			},
			"drops_override": {},
		})
	return out

func _build_reward_preview_text(diff_def: Dictionary) -> String:
	var unlock_any = diff_def.get("unlock", {})
	if not (unlock_any is Dictionary):
		return "升级奖励：无"
	var unlock: Dictionary = unlock_any
	var reward_any = unlock.get("reward", {})
	if not (reward_any is Dictionary):
		return "升级奖励：无"
	var reward: Dictionary = reward_any
	return "升级奖励：" + _build_reward_summary_text(reward)

func _build_reward_summary_text(reward: Dictionary) -> String:
	if reward.is_empty():
		return "无"
	var parts: Array[String] = []
	var gold := maxi(0, int(reward.get("gold", 0)))
	if gold > 0:
		parts.append("金币+%d" % gold)
	var skill_points := maxi(0, int(reward.get("skill_points", 0)))
	if skill_points > 0:
		parts.append("技能点+%d" % skill_points)
	var items_any = reward.get("items", {})
	if items_any is Dictionary:
		var item_parts: Array[String] = []
		for item_id_any in (items_any as Dictionary).keys():
			var item_id := str(item_id_any).strip_edges()
			var cnt := maxi(0, int((items_any as Dictionary).get(item_id_any, 0)))
			if item_id.is_empty() or cnt <= 0:
				continue
			item_parts.append("%s+%d" % [item_id, cnt])
		item_parts.sort()
		if item_parts.size() > 4:
			item_parts = item_parts.slice(0, 4)
			item_parts.append("...")
		parts.append_array(item_parts)
	if parts.is_empty():
		return "无"
	return " ".join(parts)

func _build_first_clear_preview_text(diff_def: Dictionary, diff_index: int) -> String:
	var reward := _normalize_reward(diff_def.get("first_clear_reward", {}))
	var summary := _build_reward_summary_text(reward)
	var claimed := MapProgressModel.is_first_clear_claimed(_stage_id, diff_index)
	return "首通奖励：%s（%s）" % [summary, "已领取" if claimed else "未领取"]

func _normalize_reward(reward_any: Variant) -> Dictionary:
	var out := {
		"gold": 0,
		"skill_points": 0,
		"items": {},
	}
	if not (reward_any is Dictionary):
		return out
	var reward: Dictionary = reward_any
	out["gold"] = maxi(0, int(reward.get("gold", 0)))
	out["skill_points"] = maxi(0, int(reward.get("skill_points", 0)))
	var items: Dictionary = {}
	var items_any = reward.get("items", {})
	if items_any is Dictionary:
		for item_id_any in (items_any as Dictionary).keys():
			var item_id := str(item_id_any).strip_edges()
			var cnt := maxi(0, int((items_any as Dictionary).get(item_id_any, 0)))
			if item_id.is_empty() or cnt <= 0:
				continue
			items[item_id] = cnt
	out["items"] = items
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
