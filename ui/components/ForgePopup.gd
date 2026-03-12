extends Control

const TAB_NORMAL := "normal"
const TAB_HIGH := "high"

@onready var _dim_bg: ColorRect = $DimBG
@onready var _title: Label = $Panel/VBox/TopBar/Title
@onready var _btn_tab_normal: Button = $Panel/VBox/TopBar/BtnTabNormal
@onready var _btn_tab_high: Button = $Panel/VBox/TopBar/BtnTabHigh
@onready var _btn_close: Button = $Panel/VBox/TopBar/BtnClose
@onready var _list: ItemList = $Panel/VBox/Body/LeftBox/CandidateList
@onready var _detail_title: Label = $Panel/VBox/Body/RightBox/DetailTitle
@onready var _detail_text: RichTextLabel = $Panel/VBox/Body/RightBox/DetailText
@onready var _block_reason: Label = $Panel/VBox/Body/RightBox/BlockReason
@onready var _btn_compose: Button = $Panel/VBox/ActionBar/BtnCompose
@onready var _btn_select_base: Button = $Panel/VBox/ActionBar/BtnSelectBase
@onready var _btn_clear_base: Button = $Panel/VBox/ActionBar/BtnClearBase
@onready var _btn_forge: Button = $Panel/VBox/ActionBar/BtnForge
@onready var _btn_cancel: Button = $Panel/VBox/ActionBar/BtnCancel
@onready var _base_popup: Control = $"../ForgeBaseEquipSelectPopup"

var _tab := TAB_NORMAL
var _normal_rows: Array[Dictionary] = []
var _high_rows: Array[Dictionary] = []
var _normal_selected_template_id := ""
var _high_selected_template_id := ""
var _selected_base_uid_by_target: Dictionary = {}

func _ready() -> void:
	visible = false
	_detail_text.bbcode_enabled = true
	_title.text = "打造"
	_btn_tab_normal.text = "普通打造"
	_btn_tab_high.text = "高升品打造"
	_btn_close.text = I18nService.t("ui.btn.close", "关闭")
	_btn_compose.text = "合成图纸"
	_btn_select_base.text = "选择基础装备"
	_btn_clear_base.text = "清空选择"
	_btn_forge.text = "打造"
	_btn_cancel.text = I18nService.t("ui.btn.cancel", "取消")
	if not _btn_tab_normal.pressed.is_connected(_on_tab_normal_pressed):
		_btn_tab_normal.pressed.connect(_on_tab_normal_pressed)
	if not _btn_tab_high.pressed.is_connected(_on_tab_high_pressed):
		_btn_tab_high.pressed.connect(_on_tab_high_pressed)
	if not _btn_close.pressed.is_connected(close):
		_btn_close.pressed.connect(close)
	if not _btn_cancel.pressed.is_connected(close):
		_btn_cancel.pressed.connect(close)
	if not _btn_compose.pressed.is_connected(_on_compose_pressed):
		_btn_compose.pressed.connect(_on_compose_pressed)
	if not _btn_select_base.pressed.is_connected(_on_select_base_pressed):
		_btn_select_base.pressed.connect(_on_select_base_pressed)
	if not _btn_clear_base.pressed.is_connected(_on_clear_base_pressed):
		_btn_clear_base.pressed.connect(_on_clear_base_pressed)
	if not _btn_forge.pressed.is_connected(_on_forge_pressed):
		_btn_forge.pressed.connect(_on_forge_pressed)
	if not _list.item_selected.is_connected(_on_list_item_selected):
		_list.item_selected.connect(_on_list_item_selected)
	if not _list.item_activated.is_connected(_on_list_item_activated):
		_list.item_activated.connect(_on_list_item_activated)
	if not _dim_bg.gui_input.is_connected(_on_dim_bg_gui_input):
		_dim_bg.gui_input.connect(_on_dim_bg_gui_input)
	if _base_popup != null and _base_popup.has_signal("equip_selected"):
		if not _base_popup.equip_selected.is_connected(_on_base_selected):
			_base_popup.equip_selected.connect(_on_base_selected)
	if not EventBus.inventory_updated.is_connected(_on_inventory_updated):
		EventBus.inventory_updated.connect(_on_inventory_updated)

func open() -> void:
	visible = true
	_refresh_all()

func close() -> void:
	visible = false
	if _base_popup != null and _base_popup.has_method("close"):
		_base_popup.call("close")

func _refresh_all() -> void:
	_normal_rows = ForgeService.get_normal_forge_candidates()
	_high_rows = ForgeService.get_high_forge_candidates()
	_rebuild_list_for_tab()
	_refresh_tab_visual()
	_refresh_detail()

func _refresh_tab_visual() -> void:
	var active := Color(1.0, 1.0, 1.0, 1.0)
	var inactive := Color(0.7, 0.7, 0.7, 1.0)
	_btn_tab_normal.modulate = active if _tab == TAB_NORMAL else inactive
	_btn_tab_high.modulate = active if _tab == TAB_HIGH else inactive

func _rebuild_list_for_tab() -> void:
	_list.clear()
	var rows := _current_rows()
	for i in range(rows.size()):
		var row: Dictionary = rows[i]
		var name := str(row.get("name", row.get("template_id", "装备")))
		var slot := I18nService.t("slot.%s" % str(row.get("slot", "")), str(row.get("slot", "")))
		var rarity := _rarity_name(str(row.get("rarity", "white")))
		var status := str(row.get("status", ""))
		if _tab == TAB_NORMAL:
			_list.add_item("%s｜%s｜%s｜%s" % [name, slot, rarity, status])
		else:
			var bp_have := int(row.get("blueprint_have", 0))
			var bp_tag := "图纸x%d" % bp_have if bp_have > 0 else status
			var forge_tag := str(row.get("forge_status", "不可升品"))
			_list.add_item("%s｜%s｜%s｜%s" % [name, slot, bp_tag, forge_tag])
	_ensure_selection()

func _ensure_selection() -> void:
	var rows := _current_rows()
	if rows.is_empty():
		return
	var selected_id := _current_selected_template_id()
	if selected_id.is_empty() or _find_row_index_by_template(rows, selected_id) < 0:
		selected_id = str(rows[0].get("template_id", ""))
		_set_current_selected_template_id(selected_id)
	var idx := _find_row_index_by_template(rows, selected_id)
	if idx >= 0:
		_list.select(idx)

func _refresh_detail() -> void:
	var rows := _current_rows()
	if rows.is_empty():
		_detail_title.text = "暂无可打造目标"
		_detail_text.text = "当前没有可用配置"
		_block_reason.text = ""
		_set_buttons_for_empty()
		return
	var template_id := _current_selected_template_id()
	var idx := _find_row_index_by_template(rows, template_id)
	if idx < 0:
		idx = 0
		template_id = str(rows[0].get("template_id", ""))
		_set_current_selected_template_id(template_id)
		_list.select(idx)
	var row: Dictionary = rows[idx]
	if _tab == TAB_NORMAL:
		_refresh_detail_normal(row)
	else:
		_refresh_detail_high(row)

func _refresh_detail_normal(row: Dictionary) -> void:
	var template_id := str(row.get("template_id", ""))
	var recipe := ForgeService.get_normal_forge_recipe(template_id)
	var check := ForgeService.can_forge_normal(template_id)
	var lines: Array[String] = []
	_detail_title.text = "普通打造"
	lines.append("[b]%s[/b]" % str(row.get("name", template_id)))
	lines.append("部位：%s" % I18nService.t("slot.%s" % str(row.get("slot", "")), str(row.get("slot", ""))))
	lines.append("稀有度：%s" % _rarity_name(str(row.get("rarity", "white"))) )
	lines.append("阶段：%s" % str(recipe.get("forge_tier", "T1")))
	var base_stats_any = row.get("white_stats", [])
	var base_stat_lines := _format_stats_dict(base_stats_any)
	if not base_stat_lines.is_empty():
		lines.append("白色基础属性：")
		for stat_line in base_stat_lines:
			lines.append("- %s" % stat_line)
	var growth_any = row.get("star_growth", [])
	var growth_lines := _format_stats_dict(growth_any)
	if not growth_lines.is_empty():
		lines.append("每星成长：")
		for stat_line in growth_lines:
			lines.append("- %s" % stat_line)
	lines.append("孔位规则：%s" % _socket_rule_name(str(row.get("socket_rule_ref", ""))))
	lines.append("最大孔位：%d" % EquipmentModel._template_socket_count_for_star(row, int(row.get("star_cap", 10))))
	lines.append("可升星：%s" % ("是" if bool(row.get("star_enabled", true)) else "否"))
	lines.append("")
	lines.append("配方需求：")
	var mats_any = recipe.get("materials", [])
	var mats: Array = mats_any if mats_any is Array else []
	var owned_any = check.get("owned_materials", {})
	var owned: Dictionary = owned_any if owned_any is Dictionary else {}
	for mat_any in mats:
		if not (mat_any is Dictionary):
			continue
		var mat: Dictionary = mat_any
		var item_id := str(mat.get("item_id", ""))
		var need := int(mat.get("count", 0))
		var have := int(owned.get(item_id, InventoryModel.get_count(item_id)))
		lines.append("- %s x%d（拥有%d）" % [_item_name(item_id), need, have])
	var gold_need := int(check.get("required_gold", int(recipe.get("gold_cost", 0))))
	lines.append("- 金币 %d（拥有%d）" % [gold_need, int(PlayerModel.gold)])
	_detail_text.text = "\n".join(lines)

	var ok := bool(check.get("ok", false))
	_btn_forge.disabled = not ok
	_btn_forge.text = "打造"
	_btn_compose.visible = false
	_btn_select_base.visible = false
	_btn_clear_base.visible = false
	if ok:
		_block_reason.text = ""
	else:
		_block_reason.text = _normal_block_reason(str(check.get("reason", "")))

func _refresh_detail_high(row: Dictionary) -> void:
	var template_id := str(row.get("template_id", ""))
	var recipe := ForgeService.get_high_forge_recipe(template_id)
	var selected_base_uid := _selected_base_uid_for_target(template_id)
	var check := ForgeService.can_forge_high(template_id, selected_base_uid)
	var compose := ForgeService.get_blueprint_compose_preview(template_id)

	_detail_title.text = "高升品打造"
	var lines: Array[String] = []
	lines.append("[b]%s[/b]" % str(row.get("name", template_id)))
	lines.append("部位：%s" % I18nService.t("slot.%s" % str(row.get("slot", "")), str(row.get("slot", ""))))
	lines.append("品质：高品质")
	lines.append("主题：%s" % str(recipe.get("theme_key", str(row.get("theme_key", "")))) )
	lines.append("阶段：%s" % str(recipe.get("forge_tier", "T1")))
	lines.append("孔位规则：%s" % _socket_rule_name(str(row.get("socket_rule_ref", ""))))
	lines.append("最大孔位：%d" % EquipmentModel._template_socket_count_for_star(row, int(row.get("star_cap", 10))))
	var growth_any = row.get("star_growth", [])
	var growth_lines := _format_stats_dict(growth_any)
	if not growth_lines.is_empty():
		lines.append("每星成长：")
		for stat_line in growth_lines:
			lines.append("- %s" % stat_line)

	lines.append("")
	lines.append("基础装备：")
	if selected_base_uid <= 0:
		lines.append("- 未选择")
	else:
		var base := EquipmentModel.get_instance(selected_base_uid)
		if base.is_empty():
			lines.append("- 已失效，请重新选择")
		else:
			lines.append("- %s" % str(base.get("name", "装备")))
			lines.append("- 星级 +%d，孔位 %d" % [int(base.get("star_level", 0)), int(base.get("sockets", 0))])
			lines.append("- 宝石 %s" % _socket_summary(base))
			lines.append("- 状态：%s，%s" % ["已穿戴" if _is_uid_equipped(selected_base_uid) else "背包", "锁定" if bool(base.get("locked", false)) else "未锁定"])

	lines.append("")
	lines.append("配方需求：")
	var bp_id := str(recipe.get("blueprint_item_id", ""))
	var bp_have := InventoryModel.get_count(bp_id)
	if bool(recipe.get("require_blueprint", true)):
		lines.append("- 图纸 %s x1（拥有%d）" % [_item_name(bp_id), bp_have])
	var all_mats_any = recipe.get("all_materials", [])
	var all_mats: Array = all_mats_any if all_mats_any is Array else []
	var owned_any = check.get("owned_materials", {})
	var owned: Dictionary = owned_any if owned_any is Dictionary else {}
	for mat_any in all_mats:
		if not (mat_any is Dictionary):
			continue
		var mat: Dictionary = mat_any
		var item_id := str(mat.get("item_id", ""))
		var need := int(mat.get("count", 0))
		var have := int(owned.get(item_id, InventoryModel.get_count(item_id)))
		lines.append("- %s x%d（拥有%d）" % [_item_name(item_id), need, have])
	lines.append("- 金币 %d（拥有%d）" % [int(recipe.get("gold_cost", 0)), int(PlayerModel.gold)])

	lines.append("")
	lines.append("图纸碎片：")
	lines.append("- %s %d/%d" % [
		_item_name(str(compose.get("fragment_item_id", ""))),
		int(compose.get("fragment_have", 0)),
		int(compose.get("fragment_need", 0)),
	])
	lines.append("- 合成消耗金币 %d" % int(compose.get("gold_cost", 0)))

	lines.append("")
	lines.append("继承说明：")
	lines.append("- 继承当前星级")
	lines.append("- 继承已开启孔位")
	lines.append("- 继承已镶嵌宝石")
	lines.append("- 继承锁定状态")
	lines.append("- 若基础装备已穿戴，升品后自动替换穿戴")
	_detail_text.text = "\n".join(lines)

	_btn_compose.visible = true
	_btn_select_base.visible = true
	_btn_clear_base.visible = true
	_btn_compose.disabled = not bool(compose.get("can_compose", false))
	_btn_select_base.disabled = ForgeService.get_base_equip_candidates_for_high_forge(template_id).is_empty()
	_btn_clear_base.disabled = selected_base_uid <= 0
	_btn_forge.disabled = not bool(check.get("ok", false))
	_btn_forge.text = "升品打造"
	if bool(check.get("ok", false)):
		_block_reason.text = ""
	else:
		_block_reason.text = _high_block_reason(str(check.get("reason", "")), compose)

func _set_buttons_for_empty() -> void:
	_btn_compose.visible = _tab == TAB_HIGH
	_btn_select_base.visible = _tab == TAB_HIGH
	_btn_clear_base.visible = _tab == TAB_HIGH
	_btn_compose.disabled = true
	_btn_select_base.disabled = true
	_btn_clear_base.disabled = true
	_btn_forge.disabled = true
	_btn_forge.text = "打造"

func _current_rows() -> Array[Dictionary]:
	return _normal_rows if _tab == TAB_NORMAL else _high_rows

func _current_selected_template_id() -> String:
	return _normal_selected_template_id if _tab == TAB_NORMAL else _high_selected_template_id

func _set_current_selected_template_id(template_id: String) -> void:
	if _tab == TAB_NORMAL:
		_normal_selected_template_id = template_id
	else:
		_high_selected_template_id = template_id

func _find_row_index_by_template(rows: Array, template_id: String) -> int:
	for i in range(rows.size()):
		var row_any = rows[i]
		if not (row_any is Dictionary):
			continue
		if str((row_any as Dictionary).get("template_id", "")) == template_id:
			return i
	return -1

func _selected_base_uid_for_target(template_id: String) -> int:
	if template_id.is_empty():
		return 0
	return int(_selected_base_uid_by_target.get(template_id, 0))

func _on_tab_normal_pressed() -> void:
	_tab = TAB_NORMAL
	_rebuild_list_for_tab()
	_refresh_tab_visual()
	_refresh_detail()

func _on_tab_high_pressed() -> void:
	_tab = TAB_HIGH
	_rebuild_list_for_tab()
	_refresh_tab_visual()
	_refresh_detail()

func _on_list_item_selected(index: int) -> void:
	var rows := _current_rows()
	if index < 0 or index >= rows.size():
		return
	var row_any = rows[index]
	if not (row_any is Dictionary):
		return
	_set_current_selected_template_id(str((row_any as Dictionary).get("template_id", "")))
	_refresh_detail()

func _on_list_item_activated(index: int) -> void:
	_on_list_item_selected(index)

func _on_compose_pressed() -> void:
	if _tab != TAB_HIGH:
		return
	var template_id := _current_selected_template_id()
	if template_id.is_empty():
		return
	var ret := ForgeService.do_compose_blueprint(template_id)
	if bool(ret.get("ok", false)):
		EventBus.add_log("图纸合成成功：%s" % _item_name(str(ret.get("blueprint_item_id", ""))))
	else:
		EventBus.add_log("图纸合成失败：%s" % _compose_fail_text(str(ret.get("reason", ""))))
	_refresh_all()

func _on_select_base_pressed() -> void:
	if _tab != TAB_HIGH:
		return
	var template_id := _current_selected_template_id()
	if template_id.is_empty():
		return
	var rows := ForgeService.get_base_equip_candidates_for_high_forge(template_id)
	if rows.is_empty():
		EventBus.add_log("当前没有可用基础装备")
		return
	if _base_popup != null and _base_popup.has_method("open_with_candidates"):
		_base_popup.call("open_with_candidates", rows, _selected_base_uid_for_target(template_id))

func _on_clear_base_pressed() -> void:
	if _tab != TAB_HIGH:
		return
	var template_id := _current_selected_template_id()
	if template_id.is_empty():
		return
	_selected_base_uid_by_target[template_id] = 0
	_refresh_detail()

func _on_base_selected(uid: int) -> void:
	if _tab != TAB_HIGH:
		return
	var template_id := _current_selected_template_id()
	if template_id.is_empty():
		return
	_selected_base_uid_by_target[template_id] = uid
	_refresh_detail()

func _on_forge_pressed() -> void:
	var template_id := _current_selected_template_id()
	if template_id.is_empty():
		return
	if _tab == TAB_NORMAL:
		var ret := ForgeService.do_forge_normal(template_id)
		if bool(ret.get("ok", false)):
			EventBus.add_log("打造成功：%s" % _template_name(template_id))
			TaskService.on_forge_done(1)
		else:
			EventBus.add_log("打造失败：%s" % _normal_block_reason(str(ret.get("reason", ""))))
	else:
		var base_uid := _selected_base_uid_for_target(template_id)
		var ret2 := ForgeService.do_forge_high(template_id, base_uid)
		if bool(ret2.get("ok", false)):
			EventBus.add_log("升品成功：%s" % _template_name(template_id))
			TaskService.on_forge_done(1)
			_selected_base_uid_by_target[template_id] = 0
		else:
			var compose := ForgeService.get_blueprint_compose_preview(template_id)
			EventBus.add_log("升品失败：%s" % _high_block_reason(str(ret2.get("reason", "")), compose))
	_refresh_all()

func _on_inventory_updated() -> void:
	if visible:
		_refresh_all()

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

func _normal_block_reason(reason: String) -> String:
	match reason:
		"tier_locked":
			return "未达到当前打造阶段等级"
		"no_material":
			return "材料不足"
		"no_gold":
			return "金币不足"
		"no_rule":
			return "未配置打造配方"
		"forge_disabled":
			return "该模板不可打造"
		_:
			return "当前不可打造"

func _high_block_reason(reason: String, compose: Dictionary) -> String:
	match reason:
		"no_base":
			return "请先选择基础装备"
		"base_not_found":
			return "基础装备已不存在，请重新选择"
		"base_mismatch":
			return "基础装备与目标家族不匹配"
		"no_blueprint":
			var frag_have := int(compose.get("fragment_have", 0))
			var frag_need := int(compose.get("fragment_need", 0))
			if frag_need > 0:
				return "缺少图纸（碎片 %d/%d）" % [frag_have, frag_need]
			return "缺少图纸"
		"blueprint_not_set":
			return "未配置目标图纸"
		"no_material":
			return "材料不足"
		"no_gold":
			return "金币不足"
		"tier_locked":
			return "未达到当前打造阶段等级"
		"no_rule":
			return "未配置升品配方"
		_:
			return "当前不可升品"

func _compose_fail_text(reason: String) -> String:
	match reason:
		"fragment_not_enough":
			return "图纸碎片不足"
		"no_gold":
			return "金币不足"
		"no_compose_rule":
			return "未配置图纸合成规则"
		"blueprint_not_set":
			return "未配置目标图纸"
		_:
			return "当前不可合成"

func _template_name(template_id: String) -> String:
	var tpl := _find_template(template_id)
	if tpl.is_empty():
		return template_id
	return str(tpl.get("name", template_id))

func _find_template(template_id: String) -> Dictionary:
	return EquipmentModel.get_template(template_id)

func _item_name(item_id: String) -> String:
	if item_id.is_empty():
		return "—"
	var cfg: Dictionary = ConfigService.get_cfg()
	var db_any = cfg.get("items_db", {})
	if not (db_any is Dictionary):
		return item_id
	var rows_any = (db_any as Dictionary).get("items", [])
	if not (rows_any is Array):
		return item_id
	for row_any in rows_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		if str(row.get("id", "")).strip_edges() == item_id:
			return str(row.get("name", item_id))
	return item_id

func _is_uid_equipped(uid: int) -> bool:
	var equipped_any = EquipmentModel.equipped
	if not (equipped_any is Dictionary):
		return false
	for slot_key_any in (equipped_any as Dictionary).keys():
		if int((equipped_any as Dictionary).get(slot_key_any, 0)) == uid:
			return true
	return false

func _socket_summary(inst: Dictionary) -> String:
	var sockets := maxi(0, int(inst.get("sockets", 0)))
	if sockets <= 0:
		return "无"
	var gems_any = inst.get("socket_gems", [])
	var gems: Array = gems_any if gems_any is Array else []
	var parts: Array[String] = []
	for i in range(sockets):
		var gem_id := ""
		if i < gems.size():
			gem_id = str(gems[i]).strip_edges()
		parts.append(gem_id if not gem_id.is_empty() else "空")
	return " / ".join(parts)

func _rarity_name(rarity: String) -> String:
	match rarity:
		"blue":
			return "蓝"
		"gold":
			return "金"
		"purple":
			return "紫"
		"orange":
			return "橙"
		_:
			return "白"

func _format_stats_dict(stats_any: Variant) -> Array[String]:
	var out: Array[String] = []
	var stats: Dictionary = {}
	if stats_any is Dictionary:
		for stat_any in (stats_any as Dictionary).keys():
			var stat := _normalize_stat_key(str(stat_any))
			if stat.is_empty():
				continue
			stats[stat] = int((stats_any as Dictionary).get(stat_any, 0))
	elif stats_any is Array:
		for row_any in (stats_any as Array):
			if not (row_any is Dictionary):
				continue
			var row: Dictionary = row_any
			var stat := _normalize_stat_key(str(row.get("stat", "")))
			if stat.is_empty():
				continue
			stats[stat] = int(row.get("value", 0))
	else:
		return out
	var ordered := ["HP", "ATK", "DEF", "CRIT_PERCENT", "CRIT_DMG", "QI"]
	for key in ordered:
		if not stats.has(key):
			continue
		var v := int(stats.get(key, 0))
		if v == 0:
			continue
		out.append(_format_stat_line(key, v))
	for key_any in stats.keys():
		var key := str(key_any)
		if ordered.find(key) != -1:
			continue
		var v := int(stats.get(key_any, 0))
		if v == 0:
			continue
		out.append(_format_stat_line(key, v))
	return out

func _format_stat_line(stat: String, val: int) -> String:
	var is_percent := _is_percent_stat(stat)
	return "%s +%d%s" % [I18nService.stat(stat), val, "%" if is_percent else ""]

func _socket_rule_name(rule_ref: String) -> String:
	match rule_ref.strip_edges():
		"fixed_star_3_6_8_10", "":
			return "固定开孔（3/6/8/10星）"
		"none":
			return "无孔位"
		_:
			return rule_ref

func _normalize_stat_key(stat: String) -> String:
	match stat:
		"CRIT":
			return "CRIT_PERCENT"
		"CRIT_RATE":
			return "CRIT_PERCENT"
		_:
			return stat

func _is_percent_stat(stat: String) -> bool:
	return stat == "CRIT_PERCENT" or stat == "CRIT_DMG" or stat == "ATK_SPEED" or stat == "CDR" or stat == "LOOT_BONUS_PERCENT"
