extends Control

const PAGE_MAP := "res://ui/pages/PageMap.tscn"
const PLACEHOLDER_IMAGE := "res://assets/icons/stage_node_placeholder.png"

var _summary_label: Label
var _list: VBoxContainer

func _ready() -> void:
	_build_ui()
	_connect_signals()
	_refresh()

func _build_ui() -> void:
	var bg := ColorRect.new()
	bg.anchors_preset = PRESET_FULL_RECT
	bg.anchor_right = 1.0
	bg.anchor_bottom = 1.0
	bg.color = Color(0.08, 0.08, 0.1, 1.0)
	add_child(bg)

	var root := VBoxContainer.new()
	root.anchors_preset = PRESET_FULL_RECT
	root.anchor_right = 1.0
	root.anchor_bottom = 1.0
	root.offset_left = 16.0
	root.offset_top = 16.0
	root.offset_right = -16.0
	root.offset_bottom = -16.0
	root.add_theme_constant_override("separation", 12)
	add_child(root)

	var header := HBoxContainer.new()
	header.custom_minimum_size = Vector2(0, 64)
	root.add_child(header)

	var btn_back := Button.new()
	btn_back.custom_minimum_size = Vector2(92, 44)
	btn_back.text = "返回"
	btn_back.pressed.connect(func() -> void:
		get_tree().change_scene_to_file(PAGE_MAP)
	)
	header.add_child(btn_back)

	var title := Label.new()
	title.text = "成长里程碑"
	title.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	title.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	title.vertical_alignment = VERTICAL_ALIGNMENT_CENTER
	title.add_theme_font_size_override("font_size", 34)
	header.add_child(title)

	var spacer := Control.new()
	spacer.custom_minimum_size = Vector2(92, 0)
	header.add_child(spacer)

	_summary_label = Label.new()
	_summary_label.autowrap_mode = TextServer.AUTOWRAP_WORD_SMART
	_summary_label.add_theme_font_size_override("font_size", 20)
	root.add_child(_summary_label)

	var scroll := ScrollContainer.new()
	scroll.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	scroll.size_flags_vertical = Control.SIZE_EXPAND_FILL
	root.add_child(scroll)

	_list = VBoxContainer.new()
	_list.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	_list.add_theme_constant_override("separation", 12)
	scroll.add_child(_list)

func _connect_signals() -> void:
	if not EventBus.inventory_updated.is_connected(_on_state_changed):
		EventBus.inventory_updated.connect(_on_state_changed)

func _refresh() -> void:
	var rows := ProgressModel.get_growth_milestones()
	rows.sort_custom(func(a: Dictionary, b: Dictionary) -> bool:
		var level_a := int(a.get("level", 0))
		var level_b := int(b.get("level", 0))
		if level_a != level_b:
			return level_a < level_b
		return int(a.get("sort", 0)) < int(b.get("sort", 0))
	)
	var claimable := 0
	for row in rows:
		if ProgressModel.can_claim_milestone(row):
			claimable += 1
	_summary_label.text = "当前等级：Lv%d｜已开放 %d/%d｜可领取 %d" % [
		ProgressModel.level,
		_count_unlocked(rows),
		rows.size(),
		claimable,
	]
	_rebuild_cards(rows)

func _rebuild_cards(rows: Array[Dictionary]) -> void:
	for child in _list.get_children():
		_list.remove_child(child)
		child.queue_free()

	if rows.is_empty():
		var empty := Label.new()
		empty.text = "暂无成长里程碑配置"
		empty.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
		_list.add_child(empty)
		return

	for row in rows:
		_list.add_child(_build_card(row))

func _build_card(row: Dictionary) -> Control:
	var panel := PanelContainer.new()
	panel.size_flags_horizontal = Control.SIZE_EXPAND_FILL

	var root := HBoxContainer.new()
	root.custom_minimum_size = Vector2(0, 150)
	root.add_theme_constant_override("separation", 10)
	panel.add_child(root)

	var image := TextureRect.new()
	image.custom_minimum_size = Vector2(120, 120)
	image.expand_mode = TextureRect.EXPAND_IGNORE_SIZE
	image.stretch_mode = TextureRect.STRETCH_KEEP_ASPECT_CENTERED
	var texture: Variant = load(PLACEHOLDER_IMAGE)
	var image_path := str(row.get("image", "")).strip_edges()
	if not image_path.is_empty() and ResourceLoader.exists(image_path):
		var loaded: Variant = load(image_path)
		if loaded is Texture2D:
			texture = loaded
	if texture is Texture2D:
		image.texture = texture
	root.add_child(image)

	var center := VBoxContainer.new()
	center.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	center.add_theme_constant_override("separation", 6)
	root.add_child(center)

	var title := Label.new()
	title.add_theme_font_size_override("font_size", 24)
	title.text = "Lv%d %s" % [int(row.get("level", 0)), str(row.get("title", "成长里程碑"))]
	center.add_child(title)

	var summary := Label.new()
	summary.autowrap_mode = TextServer.AUTOWRAP_WORD_SMART
	summary.text = str(row.get("summary", ""))
	summary.modulate = Color(0.88, 0.88, 0.9, 1.0)
	center.add_child(summary)

	var unlocks := Label.new()
	unlocks.autowrap_mode = TextServer.AUTOWRAP_WORD_SMART
	unlocks.text = "开放内容：%s" % _unlock_contents_text(row)
	center.add_child(unlocks)

	var reward := Label.new()
	reward.autowrap_mode = TextServer.AUTOWRAP_WORD_SMART
	reward.text = "里程碑奖励：%s x%d" % [_item_name(str(row.get("reward_item_id", ""))), maxi(1, int(row.get("reward_count", 1)))]
	reward.modulate = Color(0.92, 0.85, 0.62, 1.0)
	center.add_child(reward)

	var right := VBoxContainer.new()
	right.custom_minimum_size = Vector2(150, 0)
	right.alignment = BoxContainer.ALIGNMENT_CENTER
	right.add_theme_constant_override("separation", 8)
	root.add_child(right)

	var state := Label.new()
	state.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	state.autowrap_mode = TextServer.AUTOWRAP_WORD_SMART
	state.text = _milestone_state_text(row)
	right.add_child(state)

	var btn := Button.new()
	btn.custom_minimum_size = Vector2(140, 46)
	btn.disabled = not ProgressModel.can_claim_milestone(row)
	btn.text = "领取礼包" if not btn.disabled else _milestone_button_text(row)
	if not btn.disabled:
		btn.pressed.connect(_on_claim_pressed.bind(row))
	right.add_child(btn)

	return panel

func _count_unlocked(rows: Array[Dictionary]) -> int:
	var count := 0
	for row in rows:
		if ProgressModel.is_milestone_reached(row):
			count += 1
	return count

func _unlock_contents_text(row: Dictionary) -> String:
	var rows_any = row.get("unlock_contents", [])
	if not (rows_any is Array):
		return "暂无"
	var parts: Array[String] = []
	for unlock_any in rows_any:
		var text := ""
		if unlock_any is Dictionary:
			text = str((unlock_any as Dictionary).get("content", "")).strip_edges()
		else:
			text = str(unlock_any).strip_edges()
		if text.is_empty():
			continue
		parts.append(text)
	return "、".join(parts) if not parts.is_empty() else "暂无"

func _milestone_state_text(row: Dictionary) -> String:
	if not ProgressModel.is_milestone_reached(row):
		return "%d级解锁" % int(row.get("level", 0))
	if bool(row.get("claim_once", true)) and ProgressModel.has_claimed_milestone(str(row.get("milestone_key", ""))):
		return "已领取"
	return "已达成，待领取"

func _milestone_button_text(row: Dictionary) -> String:
	if not ProgressModel.is_milestone_reached(row):
		return "%d级解锁" % int(row.get("level", 0))
	if bool(row.get("claim_once", true)) and ProgressModel.has_claimed_milestone(str(row.get("milestone_key", ""))):
		return "已领取"
	return "领取礼包"

func _item_name(item_id: String) -> String:
	if item_id.is_empty():
		return "未配置礼包"
	var cfg: Dictionary = ConfigService.get_cfg()
	var items_db_any = cfg.get("items_db", {})
	if not (items_db_any is Dictionary):
		return item_id
	var items_any = (items_db_any as Dictionary).get("items", [])
	if not (items_any is Array):
		return item_id
	for item_any in items_any:
		if not (item_any is Dictionary):
			continue
		var item_row: Dictionary = item_any
		if str(item_row.get("id", "")) == item_id:
			return str(item_row.get("name", item_id))
	return item_id

func _on_claim_pressed(row: Dictionary) -> void:
	var ret := ProgressModel.claim_milestone_reward(row)
	if bool(ret.get("ok", false)):
		_refresh()
		return
	match str(ret.get("reason", "")):
		"locked":
			EventBus.add_log("当前等级不足，尚未达到该成长里程碑")
		"claimed":
			EventBus.add_log("该成长里程碑奖励已领取")
		"disabled":
			EventBus.add_log("该成长里程碑当前未启用")
		_:
			EventBus.add_log("当前无法领取该成长里程碑奖励")

func _on_state_changed() -> void:
	_refresh()
