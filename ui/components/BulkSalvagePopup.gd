extends Control

@onready var _dim_bg: ColorRect = $DimBG
@onready var _title: Label = $Panel/VBox/TopBar/Title
@onready var _btn_close: Button = $Panel/VBox/TopBar/BtnClose
@onready var _ck_blue_under: CheckBox = $Panel/VBox/OptionsBox/CkBlueUnder
@onready var _ck_unidentified: CheckBox = $Panel/VBox/OptionsBox/CkUnidentified
@onready var _ck_dup_set: CheckBox = $Panel/VBox/OptionsBox/CkDupSet
@onready var _lbl_preview: RichTextLabel = $Panel/VBox/LblPreview
@onready var _btn_cancel: Button = $Panel/VBox/ActionBar/BtnCancel
@onready var _btn_confirm: Button = $Panel/VBox/ActionBar/BtnConfirm

var _last_uids: Array[int] = []
var _last_preview: Dictionary = {"count": 0, "gold": 0, "items": {}}

func _ready() -> void:
	visible = false
	_title.text = "一键分解"
	_btn_close.text = I18nService.t("ui.btn.close", "关闭")
	_btn_cancel.text = I18nService.t("ui.btn.cancel", "取消")
	_btn_confirm.text = "确认分解"
	_lbl_preview.bbcode_enabled = false

	if not _btn_close.pressed.is_connected(close):
		_btn_close.pressed.connect(close)
	if not _btn_cancel.pressed.is_connected(close):
		_btn_cancel.pressed.connect(close)
	if not _btn_confirm.pressed.is_connected(_on_confirm_pressed):
		_btn_confirm.pressed.connect(_on_confirm_pressed)
	if not _ck_blue_under.toggled.is_connected(_on_option_toggled):
		_ck_blue_under.toggled.connect(_on_option_toggled)
	if not _ck_unidentified.toggled.is_connected(_on_option_toggled):
		_ck_unidentified.toggled.connect(_on_option_toggled)
	if not _ck_dup_set.toggled.is_connected(_on_option_toggled):
		_ck_dup_set.toggled.connect(_on_option_toggled)
	if not _dim_bg.gui_input.is_connected(_on_dim_bg_gui_input):
		_dim_bg.gui_input.connect(_on_dim_bg_gui_input)

func open() -> void:
	visible = true
	refresh_preview()

func close() -> void:
	visible = false

func refresh_preview() -> void:
	_last_uids = _build_filtered_uid_list()
	_last_preview = EquipmentModel.preview_salvage(_last_uids)
	var count := int(_last_preview.get("count", 0))
	if count <= 0:
		_lbl_preview.text = "当前筛选无可分解装备"
		_btn_confirm.disabled = true
		return
	_btn_confirm.disabled = false
	_lbl_preview.text = "将分解：%d件\n预计获得：%s" % [count, _format_reward_text(_last_preview)]

func _build_filtered_uid_list() -> Array[int]:
	var rows: Array = EquipmentModel.list_bag_sorted()
	var filtered: Array = []
	for row_any in rows:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		var uid := int(row.get("uid", 0))
		if uid <= 0:
			continue
		if EquipmentModel.is_locked(row):
			continue
		var rarity := str(row.get("rarity", "white"))
		if _ck_blue_under.button_pressed and rarity != "white" and rarity != "blue":
			continue
		if _ck_unidentified.button_pressed and bool(row.get("identified", true)):
			continue
		filtered.append(row)

	if not _ck_dup_set.button_pressed:
		return _extract_uids(filtered)

	var out: Array[int] = []
	var groups: Dictionary = {}
	for row_any in filtered:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		var uid := int(row.get("uid", 0))
		if uid <= 0:
			continue
		var set_id := str(row.get("set_id", "")).strip_edges()
		if set_id.is_empty():
			out.append(uid)
			continue
		var slot_key := _normalize_slot_key(str(row.get("slot", "")))
		var gkey := "%s|%s" % [set_id, slot_key]
		var arr_any = groups.get(gkey, [])
		var arr: Array = arr_any if arr_any is Array else []
		arr.append(row)
		groups[gkey] = arr

	for gkey_any in groups.keys():
		var arr_any = groups.get(gkey_any, [])
		if not (arr_any is Array):
			continue
		var arr: Array = arr_any
		if arr.size() <= 1:
			continue
		var best_idx := -1
		var best_score := -2147483648
		for i in range(arr.size()):
			var row_any = arr[i]
			if not (row_any is Dictionary):
				continue
			var score := _score_row(row_any)
			if best_idx < 0 or score > best_score:
				best_idx = i
				best_score = score
		for i in range(arr.size()):
			if i == best_idx:
				continue
			var row_any = arr[i]
			if not (row_any is Dictionary):
				continue
			var uid := int((row_any as Dictionary).get("uid", 0))
			if uid > 0:
				out.append(uid)

	return _dedupe_uids(out)

func _extract_uids(rows: Array) -> Array[int]:
	var out: Array[int] = []
	for row_any in rows:
		if not (row_any is Dictionary):
			continue
		var uid := int((row_any as Dictionary).get("uid", 0))
		if uid > 0:
			out.append(uid)
	return _dedupe_uids(out)

func _dedupe_uids(uids: Array[int]) -> Array[int]:
	var seen: Dictionary = {}
	var out: Array[int] = []
	for uid in uids:
		if uid <= 0:
			continue
		var key := str(uid)
		if seen.has(key):
			continue
		seen[key] = true
		out.append(uid)
	return out

func _normalize_slot_key(slot: String) -> String:
	if slot.begins_with("ring"):
		return "ring"
	if slot.begins_with("bracelet"):
		return "bracelet"
	return slot

func _score_row(row: Dictionary) -> int:
	var identified_bonus := 100000 if bool(row.get("identified", true)) else 0
	var rarity := str(row.get("rarity", "white"))
	var rarity_rank := _rarity_rank(rarity) * 10000
	var main_score := int(EquipmentModel.get_effective_main_val(row)) * 100
	var sockets := maxi(0, int(row.get("sockets", 0))) * 10
	var effect_sum := _effects_stat_sum(row)
	var gems_count := _gems_in_sockets_count(row)
	return identified_bonus + rarity_rank + main_score + sockets + effect_sum + gems_count

func _effects_stat_sum(row: Dictionary) -> int:
	var sum := 0
	for e in EquipmentModel.get_all_effects(row):
		if str(e.get("type", "")) != "stat":
			continue
		sum += maxi(0, int(e.get("val", 0)))
	return sum

func _gems_in_sockets_count(row: Dictionary) -> int:
	var sockets := maxi(0, int(row.get("sockets", 0)))
	if sockets <= 0:
		return 0
	var n := 0
	var gems_any = row.get("socket_gems", [])
	if gems_any is Array:
		var gems: Array = gems_any
		for i in range(mini(sockets, gems.size())):
			if str(gems[i]).strip_edges() != "":
				n += 1
	return n

func _rarity_rank(rarity: String) -> int:
	match rarity:
		"gold":
			return 3
		"blue":
			return 2
		_:
			return 1

func _format_reward_text(reward: Dictionary) -> String:
	var parts: Array[String] = []
	var gold := maxi(0, int(reward.get("gold", 0)))
	if gold > 0:
		parts.append("金币+%d" % gold)

	var items_any = reward.get("items", {})
	if items_any is Dictionary:
		var keys: Array[String] = []
		for item_id_any in (items_any as Dictionary).keys():
			keys.append(str(item_id_any))
		keys.sort()
		var max_show := mini(4, keys.size())
		for i in range(max_show):
			var item_id := keys[i]
			var cnt := maxi(0, int((items_any as Dictionary).get(item_id, 0)))
			if cnt <= 0:
				continue
			parts.append("%s+%d" % [item_id, cnt])
		if keys.size() > max_show:
			parts.append("…")

	if parts.is_empty():
		return "无"
	return "，".join(parts)

func _on_option_toggled(_pressed: bool) -> void:
	if visible:
		refresh_preview()

func _on_confirm_pressed() -> void:
	if _last_uids.is_empty():
		return
	var ret: Dictionary = EquipmentModel.salvage_many(_last_uids)
	if not bool(ret.get("ok", false)):
		EventBus.add_log("无法分解")
		refresh_preview()
		return
	EventBus.add_log("分解完成：%d件 %s" % [int(ret.get("count", 0)), _format_reward_text(ret)])
	close()

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
