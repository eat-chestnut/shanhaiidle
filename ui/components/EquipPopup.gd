extends Control

const MAX_SOCKET_BTNS := 4
const PUNCH_ITEM_ID := "打孔石"

@onready var _dim_bg: ColorRect = $DimBG
@onready var _lbl_title: Label = $Panel/VBox/TopBar/LblTitle
@onready var _btn_close: Button = $Panel/VBox/TopBar/BtnClose
@onready var _left_title: Label = $Panel/VBox/CompareRow/LeftBox/LeftTitle
@onready var _left_detail: RichTextLabel = $Panel/VBox/CompareRow/LeftBox/LeftDetail
@onready var _right_title: Label = $Panel/VBox/CompareRow/RightBox/RightTitle
@onready var _right_detail: RichTextLabel = $Panel/VBox/CompareRow/RightBox/RightDetail
@onready var _delta_detail: RichTextLabel = $Panel/VBox/DeltaDetail
@onready var _btn_identify: Button = $Panel/VBox/ActionBar/BtnIdentify
@onready var _btn_lock: Button = $Panel/VBox/ActionBar/BtnLock
@onready var _btn_salvage: Button = $Panel/VBox/ActionBar/BtnSalvage
@onready var _btn_equip: Button = $Panel/VBox/ActionBar/BtnEquip
@onready var _btn_unequip: Button = $Panel/VBox/ActionBar/BtnUnequip
@onready var _btn_upgrade: Button = $Panel/VBox/ActionBar/BtnUpgrade
@onready var _btn_punch: Button = $Panel/VBox/ActionBar/BtnPunch
@onready var _btn_cancel: Button = $Panel/VBox/ActionBar/BtnCancel
@onready var _lbl_upgrade_hint: Label = $Panel/VBox/LblUpgradeHint
@onready var _dlg_salvage: ConfirmationDialog = $ConfirmSalvage
@onready var _dlg_refine: ConfirmationDialog = $ConfirmRefine
@onready var _gem_select_popup: Node = $"../GemSelectPopup"

var _mode := ""
var _uid := 0
var _target_slot := ""
var _candidate: Dictionary = {}
var _current: Dictionary = {}
var _last_new_effect: Dictionary = {}
var _socket_btns: Array[Button] = []
var _punch_ready_style: StyleBoxFlat

func _ready() -> void:
	visible = false
	_left_detail.bbcode_enabled = true
	_right_detail.bbcode_enabled = true
	_delta_detail.bbcode_enabled = true

	_btn_close.text = I18nService.t("ui.btn.close")
	_btn_close.tooltip_text = I18nService.t("ui.btn.close")
	_btn_identify.text = I18nService.t("ui.btn.identify", "鉴定")
	_btn_lock.text = I18nService.t("ui.btn.lock", "锁定")
	_btn_salvage.text = I18nService.t("ui.btn.salvage", "分解")
	_btn_equip.text = I18nService.t("ui.btn.equip")
	_btn_unequip.text = I18nService.t("ui.btn.unequip")
	_btn_upgrade.text = "进阶"
	_btn_punch.text = I18nService.t("ui.btn.punch", "打孔")
	_btn_cancel.text = I18nService.t("ui.btn.cancel")
	_build_punch_style()

	if not _btn_close.pressed.is_connected(close):
		_btn_close.pressed.connect(close)
	if not _btn_cancel.pressed.is_connected(close):
		_btn_cancel.pressed.connect(close)
	if not _btn_identify.pressed.is_connected(_on_btn_identify_pressed):
		_btn_identify.pressed.connect(_on_btn_identify_pressed)
	if not _btn_lock.pressed.is_connected(_on_btn_lock_pressed):
		_btn_lock.pressed.connect(_on_btn_lock_pressed)
	if not _btn_salvage.pressed.is_connected(_on_btn_salvage_pressed):
		_btn_salvage.pressed.connect(_on_btn_salvage_pressed)
	if not _btn_equip.pressed.is_connected(_on_btn_equip_pressed):
		_btn_equip.pressed.connect(_on_btn_equip_pressed)
	if not _btn_unequip.pressed.is_connected(_on_btn_unequip_pressed):
		_btn_unequip.pressed.connect(_on_btn_unequip_pressed)
	if not _btn_upgrade.pressed.is_connected(_on_btn_upgrade_pressed):
		_btn_upgrade.pressed.connect(_on_btn_upgrade_pressed)
	if not _btn_punch.pressed.is_connected(_on_btn_punch_pressed):
		_btn_punch.pressed.connect(_on_btn_punch_pressed)
	if not _dim_bg.gui_input.is_connected(_on_dim_bg_gui_input):
		_dim_bg.gui_input.connect(_on_dim_bg_gui_input)
	if not _dlg_salvage.confirmed.is_connected(_on_salvage_confirmed):
		_dlg_salvage.confirmed.connect(_on_salvage_confirmed)
	if not _dlg_refine.confirmed.is_connected(_on_refine_confirmed):
		_dlg_refine.confirmed.connect(_on_refine_confirmed)
	if not EventBus.inventory_updated.is_connected(_on_inventory_updated):
		EventBus.inventory_updated.connect(_on_inventory_updated)

	_socket_btns.clear()
	for i in range(MAX_SOCKET_BTNS):
		var btn_any = get_node_or_null("Panel/VBox/SocketRow/SocketBtn%d" % i)
		if not (btn_any is Button):
			continue
		var btn: Button = btn_any
		if not btn.pressed.is_connected(_on_socket_btn_pressed.bind(i)):
			btn.pressed.connect(_on_socket_btn_pressed.bind(i))
		_socket_btns.append(btn)

func open_for_bag(uid: int) -> void:
	_last_new_effect = {}
	var inst := _find_bag_instance(uid)
	if inst.is_empty():
		return
	_mode = "bag"
	_candidate = inst
	_uid = int(inst.get("uid", 0))
	_target_slot = _resolve_target_slot_for_instance(inst)
	_current = EquipmentModel.get_equipped_instance(_target_slot)
	_refresh_ui()
	visible = true

func open_for_slot(slot_key: String, uid: int) -> void:
	_last_new_effect = {}
	var inst := EquipmentModel.get_equipped_instance(slot_key)
	if inst.is_empty():
		return
	var real_uid := int(inst.get("uid", 0))
	if uid > 0 and uid != real_uid:
		return
	_mode = "slot"
	_target_slot = slot_key
	_current = inst
	_candidate = {}
	_uid = real_uid
	_refresh_ui()
	visible = true

func close() -> void:
	visible = false
	_mode = ""
	_uid = 0
	_target_slot = ""
	_candidate = {}
	_current = {}
	_last_new_effect = {}

func _refresh_ui() -> void:
	if _mode == "bag":
		if _candidate.is_empty():
			close()
			return
		var candidate_identified := EquipmentModel.is_identified(_candidate)
		_lbl_title.text = str(_candidate.get("name", "装备对比"))
		_left_title.text = "待装备"
		_right_title.text = "当前装备"
		var left_text := _format_inst_detail(_candidate, _target_slot)
		if candidate_identified:
			var activations: Array = EquipmentModel.get_new_set_activations(_target_slot, _candidate)
			if not activations.is_empty():
				var act_any = activations[0]
				if act_any is Dictionary:
					var act: Dictionary = act_any
					var set_name := str(act.get("set_name", act.get("set_id", "")))
					var count := int(act.get("count", 0))
					var summary := str(act.get("summary", "")).strip_edges()
					if count > 0 and not set_name.is_empty():
						var line := "将激活：%s %d件" % [set_name, count]
						if not summary.is_empty():
							line += " %s" % summary
						left_text += "\n" + line
		if not _last_new_effect.is_empty():
			left_text += "\n\n[color=#FFD27A]本次新增：%s[/color]" % _format_effect_cn(_last_new_effect)
		_left_detail.text = left_text
		_right_detail.text = _format_inst_detail(_current, _target_slot)
		_delta_detail.visible = candidate_identified
		if candidate_identified:
			_delta_detail.text = _format_delta_detail()
		else:
			_delta_detail.text = "鉴定后可预览属性变化"
		_btn_identify.visible = not candidate_identified
		_btn_identify.disabled = candidate_identified or _uid <= 0
		_btn_salvage.visible = true
		_btn_salvage.disabled = _uid <= 0
		_btn_equip.disabled = _uid <= 0 or not candidate_identified
		_btn_unequip.disabled = true
	elif _mode == "slot":
		if _current.is_empty():
			close()
			return
		_lbl_title.text = str(_current.get("name", "装备详情"))
		_left_title.text = "当前装备"
		_right_title.text = "对比目标"
		var left_text := _format_inst_detail(_current, _target_slot)
		if not _last_new_effect.is_empty():
			left_text += "\n\n[color=#FFD27A]本次新增：%s[/color]" % _format_effect_cn(_last_new_effect)
		_left_detail.text = left_text
		_right_detail.text = "—"
		_delta_detail.visible = false
		_btn_identify.visible = false
		_btn_salvage.visible = false
		_btn_equip.disabled = true
		_btn_unequip.disabled = _target_slot.is_empty() or _uid <= 0
	else:
		_lbl_title.text = "装备"
		_left_title.text = "待装备"
		_right_title.text = "当前装备"
		_left_detail.text = "—"
		_right_detail.text = "—"
		_delta_detail.visible = false
		_btn_identify.visible = false
		_btn_salvage.visible = false
		_btn_equip.disabled = true
		_btn_unequip.disabled = true

	_refresh_lock_button()
	_refresh_socket_buttons()
	_refresh_upgrade_button()

func _refresh_lock_button() -> void:
	var uid := _active_uid()
	var inst := _active_inst()
	if uid <= 0 or inst.is_empty():
		_btn_lock.visible = false
		_btn_lock.disabled = true
		return
	var locked := EquipmentModel.is_locked(inst)
	_btn_lock.visible = true
	_btn_lock.disabled = false
	_btn_lock.text = I18nService.t("ui.btn.unlock", "解锁") if locked else I18nService.t("ui.btn.lock", "锁定")

func _refresh_socket_buttons() -> void:
	var inst := _active_inst()
	if inst.is_empty():
		for btn in _socket_btns:
			btn.text = "×"
			btn.disabled = true
		_refresh_punch_button(0, 0)
		return
	if _mode == "bag" and not EquipmentModel.is_identified(inst):
		for btn in _socket_btns:
			btn.text = "？"
			btn.disabled = true
		_refresh_punch_button(0, 0)
		return
	var sockets := clampi(int(inst.get("sockets", 0)), 0, MAX_SOCKET_BTNS)
	var socket_gems := _normalize_socket_gems(inst.get("socket_gems", []), sockets)
	for i in range(_socket_btns.size()):
		var btn := _socket_btns[i]
		if i >= sockets:
			btn.text = "×"
			btn.disabled = true
			continue
		var gem_id := socket_gems[i]
		btn.text = "○" if gem_id.is_empty() else gem_id
		btn.disabled = false
	_refresh_punch_button(sockets, int(inst.get("uid", 0)))

func _refresh_upgrade_button() -> void:
	var uid := _active_uid()
	if uid <= 0:
		_btn_upgrade.disabled = true
		_btn_upgrade.text = "进阶"
		_set_upgrade_hint("", Color(0.75, 0.75, 0.75, 1.0))
		return
	var inst := _active_inst()
	if inst.is_empty():
		_btn_upgrade.disabled = true
		_btn_upgrade.text = "进阶"
		_set_upgrade_hint("", Color(0.75, 0.75, 0.75, 1.0))
		return
	var refine_lv := clampi(int(inst.get("refine_lv", 0)), 0, EquipmentModel.REFINE_MAX)
	if refine_lv >= EquipmentModel.REFINE_MAX:
		_btn_upgrade.disabled = true
		_btn_upgrade.text = "已满级（+%d）" % EquipmentModel.REFINE_MAX
		_set_upgrade_hint("", Color(0.75, 0.75, 0.75, 1.0))
		return
	var rarity := str(inst.get("rarity", "white"))
	var cost := EquipmentModel.get_refine_cost(rarity)
	var items_text := _format_cost_text(cost.get("items", {}))
	if items_text == "—":
		_btn_upgrade.text = "进阶（消耗：金币%d）" % int(cost.get("gold", 0))
	else:
		_btn_upgrade.text = "进阶（消耗：金币%d，%s）" % [int(cost.get("gold", 0)), items_text]

	var gold_need := maxi(0, int(cost.get("gold", 0)))
	var gold_ok := PlayerModel.can_spend_gold(gold_need)
	var item_ok := _has_enough_cost_items(cost.get("items", {}))
	_btn_upgrade.disabled = not (gold_ok and item_ok)
	if gold_ok and item_ok:
		_set_upgrade_hint("材料齐全", Color(0.75, 0.75, 0.75, 1.0))
		return
	_refresh_refine_missing_hint(cost)

func _refresh_punch_button(sockets: int, active_uid: int) -> void:
	var stone_count := InventoryModel.get_count(PUNCH_ITEM_ID)
	_btn_punch.remove_theme_stylebox_override("normal")

	if active_uid <= 0:
		_btn_punch.disabled = true
		_btn_punch.text = I18nService.t("ui.btn.punch", "打孔")
		_btn_punch.remove_theme_color_override("font_color")
		return

	if sockets >= MAX_SOCKET_BTNS:
		_btn_punch.disabled = true
		_btn_punch.text = I18nService.t("ui.btn.punch_full", "孔已满（4/4）")
		_btn_punch.remove_theme_color_override("font_color")
		return

	if stone_count <= 0:
		_btn_punch.disabled = true
		_btn_punch.text = I18nService.t("ui.btn.punch_lack", "打孔（缺打孔石）")
		_btn_punch.add_theme_color_override("font_color", Color(0.7, 0.7, 0.7, 1.0))
		return

	_btn_punch.disabled = false
	_btn_punch.text = "%s（%s x%d）" % [
		I18nService.t("ui.btn.punch", "打孔"),
		PUNCH_ITEM_ID,
		stone_count
	]
	_btn_punch.add_theme_color_override("font_color", Color(1.0, 1.0, 1.0, 1.0))
	_btn_punch.add_theme_stylebox_override("normal", _punch_ready_style)

func _build_punch_style() -> void:
	_punch_ready_style = StyleBoxFlat.new()
	_punch_ready_style.bg_color = Color(0.15, 0.15, 0.15, 1.0)
	_punch_ready_style.border_color = Color(1.0, 0.82, 0.35, 1.0)
	_punch_ready_style.border_width_left = 2
	_punch_ready_style.border_width_top = 2
	_punch_ready_style.border_width_right = 2
	_punch_ready_style.border_width_bottom = 2
	_punch_ready_style.corner_radius_top_left = 10
	_punch_ready_style.corner_radius_top_right = 10
	_punch_ready_style.corner_radius_bottom_left = 10
	_punch_ready_style.corner_radius_bottom_right = 10

func _format_inst_detail(inst: Dictionary, slot_key: String) -> String:
	if inst.is_empty():
		return "—"
	var resolved_slot := slot_key if not slot_key.is_empty() else _resolve_target_slot_for_instance(inst)
	if not EquipmentModel.is_identified(inst):
		return _format_unidentified_detail(inst, resolved_slot)
	var lines: Array[String] = []
	var name := str(inst.get("name", "未命名装备"))
	var rarity := str(inst.get("rarity", "white"))
	var rarity_text := I18nService.t("rarity.%s" % rarity, rarity)
	var slot_text := I18nService.t("slot.%s" % resolved_slot, resolved_slot)
	var main_stat := _normalize_stat_key(str(inst.get("main_stat", "")))
	var tier := clampi(int(inst.get("tier", 0)), 0, 2)
	var refine_lv := clampi(int(inst.get("refine_lv", 0)), 0, EquipmentModel.REFINE_MAX)
	var effective_main_val := EquipmentModel.get_effective_main_val(inst)
	var main_min := int(inst.get("main_min", effective_main_val))
	var main_max := int(inst.get("main_max", effective_main_val))
	var sockets := clampi(int(inst.get("sockets", 0)), 0, MAX_SOCKET_BTNS)
	var socket_gems := _normalize_socket_gems(inst.get("socket_gems", []), sockets)
	var gem_filled := _count_filled_sockets(socket_gems)

	lines.append("[b]%s[/b]" % name)
	lines.append("稀有度：%s" % rarity_text)
	lines.append("槽位：%s" % slot_text)
	lines.append("阶位：%s" % _tier_name(tier))
	lines.append("进阶：+%d/%d" % [refine_lv, EquipmentModel.REFINE_MAX])
	lines.append("评分：%d" % EquipmentModel.calc_score(inst))
	if not main_stat.is_empty() and effective_main_val != 0:
		var stat_text := _stat_value_text(main_stat, effective_main_val)
		if main_min != main_max:
			lines.append("主属性：%s（区间 %d~%d）" % [stat_text, main_min, main_max])
		else:
			lines.append("主属性：%s" % stat_text)
	lines.append("宝石孔：%d/4，镶嵌 %d" % [sockets, gem_filled])
	lines.append("孔位：%s" % _socket_summary_text(inst))
	var set_id := str(inst.get("set_id", "")).strip_edges()
	if set_id.is_empty():
		lines.append("套装：无")
	else:
		var set_def := _find_equipment_set_def(set_id)
		var set_name := str(set_def.get("name", set_id))
		var max_pieces := maxi(1, int(set_def.get("max_pieces", 1)))
		var set_counts := EquipmentModel.get_set_counts(true)
		var pieces := int(set_counts.get(set_id, 0))
		lines.append("套装：%s" % set_name)
		lines.append("进度：%d/%d" % [pieces, max_pieces])

	var effect_lines := _format_effect_lines(inst)
	if effect_lines.is_empty():
		lines.append("特效：无")
	else:
		lines.append("特效：")
		for line in effect_lines:
			lines.append("• %s" % line)

	_append_equip_source_lines(lines, inst)
	return "\n".join(lines)

func _format_unidentified_detail(inst: Dictionary, resolved_slot: String) -> String:
	var lines: Array[String] = []
	var base_slot := str(inst.get("slot", "")).strip_edges()
	var slot_key := base_slot if not base_slot.is_empty() else resolved_slot
	var slot_text := I18nService.t("slot.%s" % slot_key, slot_key)
	var rarity := str(inst.get("rarity", "white"))
	var rarity_text := I18nService.t("rarity.%s" % rarity, rarity)
	var cost := EquipmentModel.get_identify_cost_by_rarity(rarity)
	var refine_lv := clampi(int(inst.get("refine_lv", 0)), 0, EquipmentModel.REFINE_MAX)
	lines.append("[b]未鉴定%s[/b]" % slot_text)
	lines.append("稀有度：%s" % rarity_text)
	lines.append("进阶：+%d/%d" % [refine_lv, EquipmentModel.REFINE_MAX])
	lines.append("评分：？？")
	lines.append("主属性：？？？")
	lines.append("特效：？？？")
	lines.append("孔位：？？？")
	lines.append("套装：？？？")
	lines.append("提示：鉴定后揭示主属性/特效/孔位/套装")
	lines.append("鉴定费用：金币 %d" % cost)
	_append_equip_source_lines(lines, inst)
	return "\n".join(lines)

func _format_delta_detail() -> String:
	var lines: Array[String] = []
	var override_after: Dictionary = {}
	if not _target_slot.is_empty():
		override_after[_target_slot] = _candidate if not _candidate.is_empty() else {}
	var before_stats: Dictionary = EquipmentModel.get_total_stats_with_override({})
	var after_stats: Dictionary = EquipmentModel.get_total_stats_with_override(override_after)

	lines.append("[b]属性变化[/b]")
	for stat_any in ["HP", "ATK", "DEF", "CRIT_PERCENT", "LOOT_BONUS_PERCENT", "QI"]:
		var stat := str(stat_any)
		var before := int(before_stats.get(stat, 0))
		var after := int(after_stats.get(stat, before))
		var delta := after - before
		var is_percent: bool = stat == "LOOT_BONUS_PERCENT" or stat == "CRIT_PERCENT"
		lines.append("%s %s -> %s (%s)" % [
			_stat_label(stat),
			_num_text(before, is_percent),
			_num_text(after, is_percent),
			_signed_text(delta, is_percent),
		])

	var skill_ids: Dictionary = {}
	skill_ids["BING_01"] = true
	_collect_skill_ids_from_effects(skill_ids, _candidate)
	_collect_skill_ids_from_effects(skill_ids, _current)
	var before_set_counts := EquipmentModel.get_set_counts_with_override({})
	var after_set_counts := EquipmentModel.get_set_counts_with_override(override_after)
	_collect_skill_ids_from_set_bonus(skill_ids, EquipmentModel.get_active_set_bonuses_from_counts(before_set_counts))
	_collect_skill_ids_from_set_bonus(skill_ids, EquipmentModel.get_active_set_bonuses_from_counts(after_set_counts))

	var changed_skill_lines: Array[String] = []
	if not skill_ids.is_empty():
		var keys := skill_ids.keys()
		keys.sort()
		for skill_id_any in keys:
			var skill_id := str(skill_id_any)
			if skill_id.is_empty():
				continue
			var before_lv := SkillModel.get_effective_level_with_override(skill_id, {})
			var after_lv := SkillModel.get_effective_level_with_override(skill_id, override_after)
			var diff := after_lv - before_lv
			if diff == 0:
				continue
			changed_skill_lines.append("%s Lv %d -> Lv %d (%+d)" % [
				_skill_name(skill_id),
				before_lv,
				after_lv,
				diff,
			])
	if not changed_skill_lines.is_empty():
		lines.append("")
		lines.append("[b]技能变化[/b]")
		for line in changed_skill_lines:
			lines.append(line)
	return "\n".join(lines)

func _collect_skill_ids_from_effects(target: Dictionary, inst: Dictionary) -> void:
	if inst.is_empty():
		return
	for effect_any in EquipmentModel.get_all_effects(inst):
		if not (effect_any is Dictionary):
			continue
		var effect: Dictionary = effect_any
		if str(effect.get("type", "")) != "skill_level":
			continue
		var skill_id := str(effect.get("skill_id", "")).strip_edges()
		if skill_id.is_empty():
			continue
		target[skill_id] = true

func _collect_skill_ids_from_set_bonus(target: Dictionary, set_bonus: Dictionary) -> void:
	var skills_any = set_bonus.get("skills", {})
	if not (skills_any is Dictionary):
		return
	for skill_id_any in (skills_any as Dictionary).keys():
		var skill_id := str(skill_id_any).strip_edges()
		if skill_id.is_empty():
			continue
		target[skill_id] = true

func _calc_swap_stat_delta(candidate: Dictionary, current: Dictionary) -> Dictionary:
	var left := _calc_instance_stat_contrib(candidate)
	var right := _calc_instance_stat_contrib(current)
	var out := {
		"HP": int(left.get("HP", 0)) - int(right.get("HP", 0)),
		"ATK": int(left.get("ATK", 0)) - int(right.get("ATK", 0)),
		"DEF": int(left.get("DEF", 0)) - int(right.get("DEF", 0)),
		"LOOT_BONUS_PERCENT": int(left.get("LOOT_BONUS_PERCENT", 0)) - int(right.get("LOOT_BONUS_PERCENT", 0)),
	}
	return out

func _calc_instance_stat_contrib(inst: Dictionary) -> Dictionary:
	var totals := {
		"HP": 0,
		"ATK": 0,
		"DEF": 0,
		"QI": 0,
		"CRIT_PERCENT": 0,
		"LOOT_BONUS_PERCENT": 0,
	}
	if inst.is_empty():
		return totals

	var main_stat := _normalize_stat_key(str(inst.get("main_stat", "")))
	var main_val := EquipmentModel.get_effective_main_val(inst)
	if totals.has(main_stat):
		totals[main_stat] = int(totals.get(main_stat, 0)) + main_val

	for effect in EquipmentModel.get_all_effects(inst):
		if str(effect.get("type", "")) != "stat":
			continue
		var stat := _normalize_stat_key(str(effect.get("stat", "")))
		var val := int(effect.get("val", 0))
		if totals.has(stat):
			totals[stat] = int(totals.get(stat, 0)) + val

	var sockets := clampi(int(inst.get("sockets", 0)), 0, MAX_SOCKET_BTNS)
	var socket_gems := _normalize_socket_gems(inst.get("socket_gems", []), sockets)
	for gem_id in socket_gems:
		if gem_id.is_empty():
			continue
		var effect := _gem_effect(gem_id)
		var stat := _normalize_stat_key(str(effect.get("stat", "")))
		var val := int(effect.get("val", 0))
		if totals.has(stat):
			totals[stat] = int(totals.get(stat, 0)) + val
	return totals

func _calc_swap_skill_delta(candidate: Dictionary, current: Dictionary) -> Dictionary:
	var left := _calc_instance_skill_bonus(candidate)
	var right := _calc_instance_skill_bonus(current)
	var out: Dictionary = {}
	for key_any in left.keys():
		var key := str(key_any)
		out[key] = int(left.get(key, 0)) - int(right.get(key, 0))
	for key_any in right.keys():
		var key := str(key_any)
		if out.has(key):
			continue
		out[key] = int(left.get(key, 0)) - int(right.get(key, 0))
	var filtered: Dictionary = {}
	for key_any in out.keys():
		var key := str(key_any)
		var val := int(out.get(key, 0))
		if val != 0:
			filtered[key] = val
	return filtered

func _calc_instance_skill_bonus(inst: Dictionary) -> Dictionary:
	var out: Dictionary = {}
	if inst.is_empty():
		return out
	for effect in EquipmentModel.get_all_effects(inst):
		if str(effect.get("type", "")) != "skill_level":
			continue
		var skill_id := str(effect.get("skill_id", ""))
		if skill_id.is_empty():
			continue
		var bonus := int(effect.get("val", 0))
		if bonus == 0:
			continue
		out[skill_id] = int(out.get(skill_id, 0)) + bonus
	return out

func _format_effect_lines(inst: Dictionary) -> Array[String]:
	var out: Array[String] = []
	for effect in EquipmentModel.get_all_effects(inst):
		var effect_type := str(effect.get("type", ""))
		if effect_type == "stat":
			var stat := _normalize_stat_key(str(effect.get("stat", "")))
			var val := int(effect.get("val", 0))
			if stat.is_empty() or val == 0:
				continue
			out.append(_stat_value_text(stat, val))
		elif effect_type == "skill_level":
			var skill_id := str(effect.get("skill_id", ""))
			var val := int(effect.get("val", 0))
			if skill_id.is_empty() or val == 0:
				continue
			out.append("技能+%d（%s）" % [val, _skill_name(skill_id)])
	return out

func _skill_name(skill_id: String) -> String:
	if skill_id.is_empty():
		return skill_id
	if has_node("/root/SkillNameService"):
		return SkillNameService.name(skill_id)
	return skill_id

func _append_equip_source_lines(lines: Array[String], inst: Dictionary) -> void:
	lines.append("")
	lines.append("推荐刷取")

	if not has_node("/root/SourceGuideService"):
		lines.append("暂无推荐刷取信息")
		return

	var source_lines: Array[String] = []
	var template_id := str(inst.get("template_id", "")).strip_edges()
	if not template_id.is_empty():
		source_lines = SourceGuideService.get_equip_template_lines(template_id, 8)

	if source_lines.is_empty():
		var rarity := str(inst.get("rarity", "")).strip_edges().to_lower()
		if not rarity.is_empty():
			source_lines = SourceGuideService.get_equip_rarity_lines(rarity, 8)

	if source_lines.is_empty():
		lines.append("暂无推荐刷取信息")
		return
	for line in source_lines:
		lines.append("• %s" % line)

func _active_inst() -> Dictionary:
	if _mode == "bag":
		return _candidate
	if _mode == "slot":
		return _current
	return {}

func _socket_summary_text(inst: Dictionary) -> String:
	var sockets := clampi(int(inst.get("sockets", 0)), 0, MAX_SOCKET_BTNS)
	var socket_gems := _normalize_socket_gems(inst.get("socket_gems", []), sockets)
	var parts: Array[String] = []
	for i in range(MAX_SOCKET_BTNS):
		if i >= sockets:
			parts.append("×")
			continue
		var gem_id := socket_gems[i]
		parts.append("○" if gem_id.is_empty() else gem_id)
	return " ".join(parts)

func _count_filled_sockets(socket_gems: Array[String]) -> int:
	var n := 0
	for gem_id in socket_gems:
		if not gem_id.is_empty():
			n += 1
	return n

func _stat_value_text(stat: String, val: int) -> String:
	var is_percent := stat == "LOOT_BONUS_PERCENT" or stat == "CRIT_PERCENT"
	return "%s +%d%s" % [_stat_label(stat), val, "%" if is_percent else ""]

func _stat_label(stat: String) -> String:
	return I18nService.stat(stat)

func _num_text(v: int, is_percent: bool) -> String:
	return "%d%s" % [v, "%" if is_percent else ""]

func _signed_text(v: int, is_percent: bool) -> String:
	return "%+d%s" % [v, "%" if is_percent else ""]

func _reload_instance_and_refresh() -> void:
	if _mode == "bag":
		var refreshed := _find_bag_instance(_uid)
		if refreshed.is_empty():
			close()
			return
		_candidate = refreshed
		_target_slot = _resolve_target_slot_for_instance(_candidate)
		_current = EquipmentModel.get_equipped_instance(_target_slot)
	elif _mode == "slot":
		var refreshed_current := EquipmentModel.get_equipped_instance(_target_slot)
		if refreshed_current.is_empty():
			close()
			return
		_current = refreshed_current
		_uid = int(_current.get("uid", 0))
	_refresh_ui()

func _on_btn_identify_pressed() -> void:
	if _mode != "bag" or _uid <= 0:
		return
	var ret: Dictionary = EquipmentModel.identify(_uid)
	var cost := int(ret.get("cost", 0))
	if bool(ret.get("ok", false)):
		EventBus.add_log("鉴定成功：金币-%d" % cost)
		_reload_instance_and_refresh()
		return
	var reason := str(ret.get("reason", ""))
	if reason == "no_gold":
		EventBus.add_log("金币不足：需要%d" % cost)

func _on_btn_salvage_pressed() -> void:
	if _mode != "bag" or _uid <= 0:
		return
	if EquipmentModel.is_locked(_candidate):
		EventBus.add_log("已锁定，无法分解")
		return
	var text := _build_salvage_preview_text()
	_dlg_salvage.title = "分解装备"
	_dlg_salvage.dialog_text = text
	_dlg_salvage.popup_centered()

func _on_salvage_confirmed() -> void:
	if _mode != "bag" or _uid <= 0:
		return
	var ret: Dictionary = EquipmentModel.salvage(_uid)
	if bool(ret.get("ok", false)):
		EventBus.add_log("分解成功：%s" % _format_salvage_gain(ret))
		close()
		return
	var reason := str(ret.get("reason", ""))
	if reason == "locked":
		EventBus.add_log("已锁定，无法分解")
	elif reason == "equipped":
		EventBus.add_log("已穿戴，无法分解")
	else:
		EventBus.add_log("无法分解")

func _on_btn_lock_pressed() -> void:
	var uid := _active_uid()
	var inst := _active_inst()
	if uid <= 0 or inst.is_empty():
		return
	var equip_name := str(inst.get("name", "装备"))
	var was_locked := EquipmentModel.is_locked(inst)
	if not EquipmentModel.toggle_lock(uid):
		return
	if was_locked:
		EventBus.add_log("已解锁：%s" % equip_name)
	else:
		EventBus.add_log("已锁定：%s" % equip_name)
	_reload_instance_and_refresh()

func _on_btn_equip_pressed() -> void:
	if _mode != "bag" or _uid <= 0:
		return
	if EquipmentModel.equip_uid(_uid):
		close()
	else:
		if _mode == "bag" and not EquipmentModel.is_identified(_candidate):
			EventBus.add_log("请先鉴定装备")

func _on_btn_unequip_pressed() -> void:
	if _mode != "slot" or _target_slot.is_empty():
		return
	EquipmentModel.unequip(_target_slot)
	close()

func _on_btn_punch_pressed() -> void:
	var inst := _active_inst()
	if inst.is_empty():
		return
	var uid := int(inst.get("uid", 0))
	if uid <= 0:
		return
	if EquipmentModel.add_socket(uid):
		_reload_instance_and_refresh()

func _on_btn_upgrade_pressed() -> void:
	var uid := _active_uid()
	if uid <= 0:
		return
	var inst := _active_inst()
	if inst.is_empty():
		return
	var refine_lv := clampi(int(inst.get("refine_lv", 0)), 0, EquipmentModel.REFINE_MAX)
	if refine_lv >= EquipmentModel.REFINE_MAX:
		EventBus.add_log("已满级")
		return
	var rarity := str(inst.get("rarity", "white"))
	var cost := EquipmentModel.get_refine_cost(rarity)
	_dlg_refine.title = "装备进阶"
	_dlg_refine.dialog_text = _build_refine_confirm_text(refine_lv, cost)
	_dlg_refine.popup_centered()

func _on_refine_confirmed() -> void:
	var uid := _active_uid()
	if uid <= 0:
		return
	var ret: Dictionary = EquipmentModel.refine(uid)
	if bool(ret.get("ok", false)):
		var lv := int(ret.get("lv", 0))
		var cost_any = ret.get("cost", {})
		var cost: Dictionary = cost_any if cost_any is Dictionary else {"gold": 0, "items": {}}
		var line := "进阶成功：+%d（金币-%d" % [lv, int(cost.get("gold", 0))]
		var item_text := _format_cost_text(cost.get("items", {}))
		if item_text != "—":
			line += "，%s" % item_text
		line += "）"
		var new_effect_any = ret.get("new_effect", {})
		if new_effect_any is Dictionary and not (new_effect_any as Dictionary).is_empty():
			line += " 新增词条：%s" % _format_effect_cn(new_effect_any)
			_last_new_effect = (new_effect_any as Dictionary).duplicate(true)
		else:
			_last_new_effect = {}
		EventBus.add_log(line)
		_reload_instance_and_refresh()
		return
	var reason := str(ret.get("reason", ""))
	match reason:
		"max":
			EventBus.add_log("已满级")
		"no_gold":
			var cost_any = ret.get("cost", {})
			var cost: Dictionary = cost_any if cost_any is Dictionary else {"gold": 0}
			EventBus.add_log("金币不足：需要%d" % int(cost.get("gold", 0)))
		"no_items":
			EventBus.add_log("材料不足")
		_:
			EventBus.add_log("进阶失败")

func _on_socket_btn_pressed(socket_idx: int) -> void:
	var inst := _active_inst()
	if inst.is_empty():
		return
	var uid := int(inst.get("uid", 0))
	if uid <= 0:
		return
	var sockets := clampi(int(inst.get("sockets", 0)), 0, MAX_SOCKET_BTNS)
	if socket_idx < 0 or socket_idx >= sockets:
		return
	var socket_gems := _normalize_socket_gems(inst.get("socket_gems", []), sockets)
	var gem_id := socket_gems[socket_idx]
	if gem_id.is_empty():
		if _gem_select_popup != null and _gem_select_popup.has_method("open_for_equip_socket"):
			_gem_select_popup.call("open_for_equip_socket", uid, socket_idx)
		return
	if EquipmentModel.remove_socket_gem(uid, socket_idx):
		_reload_instance_and_refresh()

func _on_inventory_updated() -> void:
	if visible:
		_reload_instance_and_refresh()

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
	var rows: Array = EquipmentModel.list_bag_sorted()
	for row_any in rows:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		if int(row.get("uid", 0)) == uid:
			return row
	return {}

func _active_uid() -> int:
	var inst := _active_inst()
	if inst.is_empty():
		return 0
	return int(inst.get("uid", 0))

func _build_salvage_preview_text() -> String:
	if _candidate.is_empty():
		return "确定分解该装备？"
	var rarity := str(_candidate.get("rarity", "white"))
	var reward := EquipmentModel.get_salvage_reward_by_rarity(rarity)
	return "确定分解该装备？\n将获得：%s" % _format_salvage_gain(reward)

func _build_refine_confirm_text(cur_lv: int, cost: Dictionary) -> String:
	var next_lv := mini(cur_lv + 1, EquipmentModel.REFINE_MAX)
	var parts: Array[String] = []
	var gold := maxi(0, int(cost.get("gold", 0)))
	parts.append("金币%d" % gold)
	var items_text := _format_cost_text(cost.get("items", {}))
	if items_text != "—":
		parts.append(items_text)
	return "进阶到 +%d 将消耗：%s\n是否确认？" % [next_lv, "，".join(parts)]

func _format_salvage_gain(reward: Dictionary) -> String:
	var parts: Array[String] = []
	var gold := maxi(0, int(reward.get("gold", 0)))
	if gold > 0:
		parts.append("金币+%d" % gold)
	var items_any = reward.get("items", {})
	if items_any is Dictionary:
		var item_keys: Array[String] = []
		for item_id_any in (items_any as Dictionary).keys():
			item_keys.append(str(item_id_any))
		item_keys.sort()
		for item_id in item_keys:
			var cnt := maxi(0, int((items_any as Dictionary).get(item_id, 0)))
			if cnt <= 0:
				continue
			parts.append("%s+%d" % [item_id, cnt])
	if parts.is_empty():
		return "无"
	return "，".join(parts)

func _format_effect_cn(e: Dictionary) -> String:
	var effect_type := str(e.get("type", ""))
	var val := int(e.get("val", 0))
	if effect_type == "stat":
		var stat := str(e.get("stat", ""))
		var name := I18nService.stat(stat)
		if stat == "LOOT_BONUS_PERCENT" or stat == "CRIT_PERCENT":
			return "%s+%d%%" % [name, val]
		return "%s+%d" % [name, val]
	if effect_type == "skill_level":
		var skill_id := str(e.get("skill_id", ""))
		var skill_name := SkillNameService.name(skill_id)
		return "%s+%d级" % [skill_name, val]
	return "未知词条"

func _format_cost_text(cost_any: Variant) -> String:
	if not (cost_any is Dictionary):
		return "—"
	var cost: Dictionary = cost_any
	var parts: Array[String] = []
	for key_any in cost.keys():
		var item_id := str(key_any)
		var cnt := int(cost.get(key_any, 0))
		if item_id.is_empty() or cnt <= 0:
			continue
		parts.append("%sx%d" % [item_id, cnt])
	if parts.is_empty():
		return "—"
	parts.sort()
	return " ".join(parts)

func _tier_name(tier: int) -> String:
	var cfg: Dictionary = ConfigService.get_cfg()
	var upgrade_db_any = cfg.get("upgrade_db", {})
	if upgrade_db_any is Dictionary:
		var names_any = (upgrade_db_any as Dictionary).get("tier_names", [])
		if names_any is Array:
			var names: Array = names_any
			if tier >= 0 and tier < names.size():
				return str(names[tier])
	match tier:
		1:
			return "灵"
		2:
			return "玄"
		_:
			return "凡"

func _refresh_refine_missing_hint(cost: Dictionary) -> void:
	var missing: Dictionary = {}
	var items_any = cost.get("items", {})
	if items_any is Dictionary:
		for key_any in (items_any as Dictionary).keys():
			var mat_id := str(key_any)
			var need := int((items_any as Dictionary).get(key_any, 0))
			if mat_id.is_empty() or need <= 0:
				continue
			var have := InventoryModel.get_count(mat_id)
			if have < need:
				missing[mat_id] = need - have

	var need_gold := maxi(0, int(cost.get("gold", 0)))
	var lack_gold := maxi(0, need_gold - PlayerModel.gold)
	if missing.is_empty() and lack_gold <= 0:
		_set_upgrade_hint("材料齐全", Color(0.75, 0.75, 0.75, 1.0))
		return

	var parts: Array[String] = []
	if lack_gold > 0:
		parts.append("金币x%d" % lack_gold)
	for mat_any in missing.keys():
		var mat_id := str(mat_any)
		parts.append("%sx%d" % [mat_id, int(missing.get(mat_id, 0))])
	parts.sort()
	var hint := "缺：%s" % " ".join(parts)

	var recommendations := _recommend_stages_for_materials(missing)
	if not recommendations.is_empty():
		hint += "\n推荐地图：%s" % " / ".join(recommendations)
	_set_upgrade_hint(hint, Color(1.0, 0.55, 0.55, 1.0))

func _has_enough_cost_items(cost_any: Variant) -> bool:
	if not (cost_any is Dictionary):
		return true
	var cost: Dictionary = cost_any
	for key_any in cost.keys():
		var mat_id := str(key_any)
		var need := int(cost.get(key_any, 0))
		if mat_id.is_empty() or need <= 0:
			continue
		var have := InventoryModel.get_count(mat_id)
		if have < need:
			return false
	return true

func _recommend_stages_for_materials(missing: Dictionary) -> Array[String]:
	var out: Array[String] = []
	if missing.is_empty():
		return out

	var cfg: Dictionary = ConfigService.get_cfg()
	var db_any = cfg.get("stages_db", {})
	if not (db_any is Dictionary):
		return out
	var stages_any = (db_any as Dictionary).get("stages", [])
	if not (stages_any is Array):
		return out

	var scored: Array[Dictionary] = []
	for stage_any in stages_any:
		if not (stage_any is Dictionary):
			continue
		var stage: Dictionary = stage_any
		var stage_name := str(stage.get("name", ""))
		if stage_name.is_empty():
			continue
		var score := 0
		var items_by_rarity := _stage_items_by_rarity(stage)
		for mat_any in missing.keys():
			var mat_id := str(mat_any)
			if mat_id.is_empty():
				continue
			if _stage_has_material(items_by_rarity, mat_id):
				score += 10
		scored.append({
			"name": stage_name,
			"score": score,
		})

	scored.sort_custom(_sort_stage_score_desc)
	for row_any in scored:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		var score := int(row.get("score", 0))
		if score <= 0:
			continue
		out.append(str(row.get("name", "")))
		if out.size() >= 2:
			break
	return out

func _stage_items_by_rarity(stage: Dictionary) -> Dictionary:
	var out := {
		"white": [],
		"blue": [],
		"gold": [],
	}
	var patch_any = stage.get("drops_patch", {})
	if not (patch_any is Dictionary):
		return out
	var patch: Dictionary = patch_any
	var items_any = patch.get("items_by_rarity", patch.get("items", {}))
	if not (items_any is Dictionary):
		return out
	var items_dict: Dictionary = items_any
	for rarity in ["white", "blue", "gold"]:
		var list_any = items_dict.get(rarity, [])
		if not (list_any is Array):
			continue
		var names: Array[String] = []
		for item_any in list_any:
			var item_id := str(item_any)
			if item_id.is_empty():
				continue
			names.append(item_id)
		out[rarity] = names
	return out

func _stage_has_material(items_by_rarity: Dictionary, material_id: String) -> bool:
	for rarity in ["white", "blue", "gold"]:
		var list_any = items_by_rarity.get(rarity, [])
		if not (list_any is Array):
			continue
		for item_any in (list_any as Array):
			if str(item_any) == material_id:
				return true
	return false

func _sort_stage_score_desc(a: Dictionary, b: Dictionary) -> bool:
	var sa := int(a.get("score", 0))
	var sb := int(b.get("score", 0))
	if sa != sb:
		return sa > sb
	return str(a.get("name", "")) < str(b.get("name", ""))

func _set_upgrade_hint(text: String, color: Color) -> void:
	_lbl_upgrade_hint.text = text
	_lbl_upgrade_hint.modulate = color

func _resolve_target_slot_for_instance(inst: Dictionary) -> String:
	var base_slot := str(inst.get("slot", ""))
	var equipped_ref: Dictionary = EquipmentModel.equipped
	match base_slot:
		"ring":
			if int(equipped_ref.get("ring1", 0)) == 0:
				return "ring1"
			if int(equipped_ref.get("ring2", 0)) == 0:
				return "ring2"
			return "ring1"
		"bracelet":
			if int(equipped_ref.get("bracelet1", 0)) == 0:
				return "bracelet1"
			if int(equipped_ref.get("bracelet2", 0)) == 0:
				return "bracelet2"
			return "bracelet1"
		_:
			return base_slot

func _normalize_stat_key(stat: String) -> String:
	match stat:
		"CRIT":
			return "CRIT_PERCENT"
		"DROP":
			return "LOOT_BONUS_PERCENT"
		_:
			return stat

func _normalize_socket_gems(gems_any: Variant, sockets: int) -> Array[String]:
	var arr: Array[String] = []
	if gems_any is Array:
		for gem_any in (gems_any as Array):
			arr.append(str(gem_any))
	if arr.size() < sockets:
		for _i in range(sockets - arr.size()):
			arr.append("")
	elif arr.size() > sockets:
		arr.resize(sockets)
	return arr

func _gem_effect(gem_id: String) -> Dictionary:
	var cfg: Dictionary = ConfigService.get_cfg()
	var items_db_any = cfg.get("items_db", {})
	if not (items_db_any is Dictionary):
		return {}
	var items_any = (items_db_any as Dictionary).get("items", [])
	if not (items_any is Array):
		return {}
	for item_any in items_any:
		if not (item_any is Dictionary):
			continue
		var item_def: Dictionary = item_any
		if str(item_def.get("id", "")) != gem_id:
			continue
		var effect_any: Variant = item_def.get("gem_effect", {})
		if effect_any is Dictionary:
			return effect_any
		return {}
	return {}

func _find_equipment_set_def(set_id: String) -> Dictionary:
	if set_id.is_empty():
		return {}
	var cfg: Dictionary = ConfigService.get_cfg()
	var db_any = cfg.get("equipment_sets_db", {})
	if not (db_any is Dictionary):
		return {}
	var rows_any = (db_any as Dictionary).get("equipment_sets", [])
	if not (rows_any is Array):
		return {}
	for row_any in rows_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		if str(row.get("id", "")) == set_id:
			return row
	return {}
