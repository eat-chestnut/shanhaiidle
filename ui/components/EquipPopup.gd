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
@onready var _btn_equip: Button = $Panel/VBox/ActionBar/BtnEquip
@onready var _btn_unequip: Button = $Panel/VBox/ActionBar/BtnUnequip
@onready var _btn_upgrade: Button = $Panel/VBox/ActionBar/BtnUpgrade
@onready var _btn_punch: Button = $Panel/VBox/ActionBar/BtnPunch
@onready var _btn_cancel: Button = $Panel/VBox/ActionBar/BtnCancel
@onready var _lbl_upgrade_hint: Label = $Panel/VBox/LblUpgradeHint
@onready var _gem_select_popup: Node = $"../GemSelectPopup"

var _mode := ""
var _uid := 0
var _target_slot := ""
var _candidate: Dictionary = {}
var _current: Dictionary = {}
var _socket_btns: Array[Button] = []
var _punch_ready_style: StyleBoxFlat

func _ready() -> void:
	visible = false
	_left_detail.bbcode_enabled = true
	_right_detail.bbcode_enabled = true
	_delta_detail.bbcode_enabled = true

	_btn_close.text = I18nService.t("ui.btn.close")
	_btn_close.tooltip_text = I18nService.t("ui.btn.close")
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

func _refresh_ui() -> void:
	if _mode == "bag":
		if _candidate.is_empty():
			close()
			return
		_lbl_title.text = str(_candidate.get("name", "装备对比"))
		_left_title.text = "待装备"
		_right_title.text = "当前装备"
		_left_detail.text = _format_inst_detail(_candidate, _target_slot)
		_right_detail.text = _format_inst_detail(_current, _target_slot)
		_delta_detail.visible = true
		_delta_detail.text = _format_delta_detail()
		_btn_equip.disabled = _uid <= 0
		_btn_unequip.disabled = true
	elif _mode == "slot":
		if _current.is_empty():
			close()
			return
		_lbl_title.text = str(_current.get("name", "装备详情"))
		_left_title.text = "当前装备"
		_right_title.text = "对比目标"
		_left_detail.text = _format_inst_detail(_current, _target_slot)
		_right_detail.text = "—"
		_delta_detail.visible = false
		_btn_equip.disabled = true
		_btn_unequip.disabled = _target_slot.is_empty() or _uid <= 0
	else:
		_lbl_title.text = "装备"
		_left_title.text = "待装备"
		_right_title.text = "当前装备"
		_left_detail.text = "—"
		_right_detail.text = "—"
		_delta_detail.visible = false
		_btn_equip.disabled = true
		_btn_unequip.disabled = true

	_refresh_socket_buttons()
	_refresh_upgrade_button()

func _refresh_socket_buttons() -> void:
	var inst := _active_inst()
	if inst.is_empty():
		for btn in _socket_btns:
			btn.text = "×"
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
	var info: Dictionary = EquipmentModel.can_upgrade(uid)
	if bool(info.get("ok", false)):
		_btn_upgrade.disabled = false
		_btn_upgrade.text = "进阶（消耗：%s）" % _format_cost_text(info.get("cost", {}))
		_set_upgrade_hint("材料齐全", Color(0.75, 0.75, 0.75, 1.0))
		return

	_btn_upgrade.disabled = true
	var reason := str(info.get("reason", ""))
	if reason == "max":
		_btn_upgrade.text = "已满阶（%s）" % _tier_name(2)
		_set_upgrade_hint("", Color(0.75, 0.75, 0.75, 1.0))
	elif reason == "lack":
		_btn_upgrade.text = "进阶（材料不足）"
		_refresh_missing_hint(info.get("cost", {}))
	else:
		_btn_upgrade.text = "进阶"
		_set_upgrade_hint("", Color(0.75, 0.75, 0.75, 1.0))

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
	var lines: Array[String] = []
	var name := str(inst.get("name", "未命名装备"))
	var rarity := str(inst.get("rarity", "white"))
	var rarity_text := I18nService.t("rarity.%s" % rarity, rarity)
	var resolved_slot := slot_key if not slot_key.is_empty() else _resolve_target_slot_for_instance(inst)
	var slot_text := I18nService.t("slot.%s" % resolved_slot, resolved_slot)
	var main_stat := _normalize_stat_key(str(inst.get("main_stat", "")))
	var base_main_val := int(inst.get("main_val", 0))
	var tier := clampi(int(inst.get("tier", 0)), 0, 2)
	var effective_main_val := EquipmentModel.get_effective_main_val(inst)
	var tier_bonus := effective_main_val - base_main_val
	var main_min := int(inst.get("main_min", base_main_val))
	var main_max := int(inst.get("main_max", base_main_val))
	var sockets := clampi(int(inst.get("sockets", 0)), 0, MAX_SOCKET_BTNS)
	var socket_gems := _normalize_socket_gems(inst.get("socket_gems", []), sockets)
	var gem_filled := _count_filled_sockets(socket_gems)

	lines.append("[b]%s[/b]" % name)
	lines.append("稀有度：%s" % rarity_text)
	lines.append("槽位：%s" % slot_text)
	lines.append("阶位：%s" % _tier_name(tier))
	if not main_stat.is_empty() and effective_main_val != 0:
		var stat_text := _stat_value_text(main_stat, effective_main_val)
		if main_min != main_max:
			lines.append("主属性：%s（基础%d + 进阶%d，区间 %d~%d）" % [
				stat_text,
				base_main_val,
				tier_bonus,
				main_min,
				main_max
			])
		else:
			lines.append("主属性：%s（基础%d + 进阶%d）" % [stat_text, base_main_val, tier_bonus])
	lines.append("宝石孔：%d/4，镶嵌 %d" % [sockets, gem_filled])
	lines.append("孔位：%s" % _socket_summary_text(inst))

	var effect_lines := _format_effect_lines(inst)
	if effect_lines.is_empty():
		lines.append("特效：无")
	else:
		lines.append("特效：")
		for line in effect_lines:
			lines.append("• %s" % line)
	return "\n".join(lines)

func _format_delta_detail() -> String:
	var lines: Array[String] = []
	var before_stats: Dictionary = EquipmentModel.get_total_stats()
	var delta_stats := _calc_swap_stat_delta(_candidate, _current)

	lines.append("[b]属性变化[/b]")
	for stat_any in ["ATK", "DEF", "HP", "LOOT_BONUS_PERCENT"]:
		var stat := str(stat_any)
		var before := int(before_stats.get(stat, 0))
		var delta := int(delta_stats.get(stat, 0))
		var after := before + delta
		var is_percent: bool = stat == "LOOT_BONUS_PERCENT"
		lines.append("%s %s -> %s (%s)" % [
			_stat_label(stat),
			_num_text(before, is_percent),
			_num_text(after, is_percent),
			_signed_text(delta, is_percent),
		])

	var skill_delta := _calc_swap_skill_delta(_candidate, _current)
	if not skill_delta.is_empty():
		lines.append("")
		lines.append("[b]技能变化[/b]")
		var ids := skill_delta.keys()
		ids.sort()
		for skill_id_any in ids:
			var skill_id := str(skill_id_any)
			var delta := int(skill_delta.get(skill_id, 0))
			if delta == 0:
				continue
			var before_lv := SkillModel.get_effective_level(skill_id)
			var after_lv := maxi(0, before_lv + delta)
			lines.append("%s Lv %d -> Lv %d (%+d)" % [
				_skill_name(skill_id),
				before_lv,
				after_lv,
				delta
			])
	return "\n".join(lines)

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

	var effects_any = inst.get("effects", [])
	if effects_any is Array:
		for effect_any in effects_any:
			if not (effect_any is Dictionary):
				continue
			var effect: Dictionary = effect_any
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
	var effects_any = inst.get("effects", [])
	if not (effects_any is Array):
		return out
	for effect_any in effects_any:
		if not (effect_any is Dictionary):
			continue
		var effect: Dictionary = effect_any
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
	var effects_any = inst.get("effects", [])
	if not (effects_any is Array):
		return out
	for effect_any in effects_any:
		if not (effect_any is Dictionary):
			continue
		var effect: Dictionary = effect_any
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
	var cfg: Dictionary = ConfigService.get_cfg()
	var balance_any = cfg.get("balance", {})
	if not (balance_any is Dictionary):
		return skill_id
	var skills_any = (balance_any as Dictionary).get("skills", [])
	if not (skills_any is Array):
		return skill_id
	for skill_any in skills_any:
		if not (skill_any is Dictionary):
			continue
		var skill: Dictionary = skill_any
		if str(skill.get("id", "")) == skill_id:
			return str(skill.get("name", skill_id))
	return skill_id

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
	match stat:
		"HP":
			return "HP"
		"ATK":
			return "ATK"
		"DEF":
			return "DEF"
		"QI":
			return "Qi"
		"CRIT_PERCENT":
			return "暴击"
		"LOOT_BONUS_PERCENT":
			return "掉落"
		_:
			return stat

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

func _on_btn_equip_pressed() -> void:
	if _mode != "bag" or _uid <= 0:
		return
	EquipmentModel.equip_uid(_uid)
	close()

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
	if EquipmentModel.upgrade_equipment(uid):
		_reload_instance_and_refresh()

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

func _refresh_missing_hint(cost_any: Variant) -> void:
	if not (cost_any is Dictionary):
		_set_upgrade_hint("进阶（材料不足）", Color(1.0, 0.55, 0.55, 1.0))
		return
	var cost: Dictionary = cost_any
	var missing: Dictionary = {}
	for key_any in cost.keys():
		var mat_id := str(key_any)
		var need := int(cost.get(key_any, 0))
		if mat_id.is_empty() or need <= 0:
			continue
		var have := InventoryModel.get_count(mat_id)
		if have < need:
			missing[mat_id] = need - have
	if missing.is_empty():
		_set_upgrade_hint("材料齐全", Color(0.75, 0.75, 0.75, 1.0))
		return

	var missing_keys: Array[String] = []
	for mat_any in missing.keys():
		missing_keys.append(str(mat_any))
	missing_keys.sort()
	var parts: Array[String] = []
	for mat_id in missing_keys:
		parts.append("%sx%d" % [mat_id, int(missing.get(mat_id, 0))])
	var hint := "缺：%s" % " ".join(parts)

	var recommendations := _recommend_stages_for_materials(missing)
	if not recommendations.is_empty():
		hint += "\n推荐地图：%s" % " / ".join(recommendations)
	_set_upgrade_hint(hint, Color(1.0, 0.55, 0.55, 1.0))

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
