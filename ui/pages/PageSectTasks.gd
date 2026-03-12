extends Control

const PAGE_MAP := "res://ui/pages/PageMap.tscn"

var _summary_label: Label
var _daily_list: VBoxContainer
var _milestone_list: VBoxContainer
var _btn_claim_daily: Button
var _btn_claim_milestone: Button

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
	root.add_theme_constant_override("separation", 10)
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
	title.text = "宗务堂"
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

	var action_row := HBoxContainer.new()
	action_row.add_theme_constant_override("separation", 10)
	root.add_child(action_row)

	_btn_claim_daily = Button.new()
	_btn_claim_daily.text = "领取全部日常"
	_btn_claim_daily.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	_btn_claim_daily.pressed.connect(_on_claim_daily_pressed)
	action_row.add_child(_btn_claim_daily)

	_btn_claim_milestone = Button.new()
	_btn_claim_milestone.text = "领取全部历程"
	_btn_claim_milestone.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	_btn_claim_milestone.pressed.connect(_on_claim_milestone_pressed)
	action_row.add_child(_btn_claim_milestone)

	var scroll := ScrollContainer.new()
	scroll.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	scroll.size_flags_vertical = Control.SIZE_EXPAND_FILL
	root.add_child(scroll)

	var content := VBoxContainer.new()
	content.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	content.add_theme_constant_override("separation", 12)
	scroll.add_child(content)

	var daily_title := Label.new()
	daily_title.text = "日常宗务"
	daily_title.add_theme_font_size_override("font_size", 28)
	content.add_child(daily_title)

	_daily_list = VBoxContainer.new()
	_daily_list.add_theme_constant_override("separation", 8)
	content.add_child(_daily_list)

	var milestone_title := Label.new()
	milestone_title.text = "历程宗务"
	milestone_title.add_theme_font_size_override("font_size", 28)
	content.add_child(milestone_title)

	_milestone_list = VBoxContainer.new()
	_milestone_list.add_theme_constant_override("separation", 8)
	content.add_child(_milestone_list)

func _connect_signals() -> void:
	if not EventBus.tasks_updated.is_connected(_on_tasks_changed):
		EventBus.tasks_updated.connect(_on_tasks_changed)
	if not EventBus.inventory_updated.is_connected(_on_tasks_changed):
		EventBus.inventory_updated.connect(_on_tasks_changed)

func _refresh() -> void:
	var summary := TaskService.get_summary()
	_summary_label.text = "今日可接：%d/%d｜历程已开：%d/%d｜可领取：%d｜当前宗门贡献：%d" % [
		int(summary.get("daily_unlocked", 0)),
		int(summary.get("daily_total", 0)),
		int(summary.get("milestone_unlocked", 0)),
		int(summary.get("milestone_total", 0)),
		int(summary.get("claimable", 0)),
		PlayerModel.sect_contribution,
	]
	_rebuild_group(_daily_list, TaskService.get_daily_rows(), "daily")
	_rebuild_group(_milestone_list, TaskService.get_milestone_rows(), "milestone")
	_btn_claim_daily.disabled = not _has_claimable(TaskService.get_daily_rows())
	_btn_claim_milestone.disabled = not _has_claimable(TaskService.get_milestone_rows())

func _rebuild_group(container: VBoxContainer, rows: Array[Dictionary], group: String) -> void:
	for child in container.get_children():
		container.remove_child(child)
		child.queue_free()
	if rows.is_empty():
		var empty := Label.new()
		empty.text = "暂无任务"
		empty.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
		container.add_child(empty)
		return
	for row in rows:
		container.add_child(_build_task_card(row, group))

func _build_task_card(row: Dictionary, group: String) -> Control:
	var panel := PanelContainer.new()
	panel.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	var vbox := VBoxContainer.new()
	vbox.add_theme_constant_override("separation", 6)
	panel.add_child(vbox)

	var top := HBoxContainer.new()
	top.add_theme_constant_override("separation", 8)
	vbox.add_child(top)

	var title := Label.new()
	title.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	title.add_theme_font_size_override("font_size", 24)
	title.text = str(row.get("name", "任务"))
	top.add_child(title)

	var btn := Button.new()
	btn.custom_minimum_size = Vector2(110, 40)
	btn.disabled = true
	if not bool(row.get("is_unlocked", false)):
		btn.text = "未解锁"
	elif bool(row.get("claimed", false)):
		btn.text = "已领取"
	elif bool(row.get("can_claim", false)):
		btn.text = "领取"
		btn.disabled = false
		btn.pressed.connect(_on_claim_single_pressed.bind(group, str(row.get("task_id", ""))))
	else:
		btn.text = "进行中"
	top.add_child(btn)

	var desc := Label.new()
	desc.autowrap_mode = TextServer.AUTOWRAP_WORD_SMART
	desc.text = str(row.get("desc", ""))
	desc.modulate = Color(0.82, 0.82, 0.82, 1.0)
	vbox.add_child(desc)

	var progress := Label.new()
	progress.text = "进度：%d/%d" % [int(row.get("progress", 0)), int(row.get("target", 1))]
	vbox.add_child(progress)

	if not bool(row.get("is_unlocked", false)):
		var unlock_stage_name := str(row.get("unlock_stage_name", "")).strip_edges()
		var unlock_label := Label.new()
		unlock_label.text = "解锁条件：通关%s" % (unlock_stage_name if not unlock_stage_name.is_empty() else "指定主线")
		unlock_label.modulate = Color(1.0, 0.78, 0.78, 1.0)
		vbox.add_child(unlock_label)

	var reward := Label.new()
	reward.autowrap_mode = TextServer.AUTOWRAP_WORD_SMART
	reward.text = "奖励：%s" % str(row.get("reward_summary", "—"))
	reward.modulate = Color(0.88, 0.85, 0.62, 1.0)
	vbox.add_child(reward)

	return panel

func _has_claimable(rows: Array[Dictionary]) -> bool:
	for row in rows:
		if bool(row.get("can_claim", false)):
			return true
	return false

func _on_claim_single_pressed(group: String, task_id: String) -> void:
	var ret := TaskService.claim_task(group, task_id)
	if bool(ret.get("ok", false)):
		_refresh()
		return
	match str(ret.get("reason", "")):
		"locked":
			EventBus.add_log("宗门任务尚未解锁")
		"incomplete":
			EventBus.add_log("宗门任务尚未完成")
		"claimed":
			EventBus.add_log("宗门任务奖励已领取")
		_:
			EventBus.add_log("当前无法领取该宗门任务")

func _on_claim_daily_pressed() -> void:
	var ret := TaskService.claim_all_available("daily")
	_handle_claim_all_result(ret, "日常宗务")

func _on_claim_milestone_pressed() -> void:
	var ret := TaskService.claim_all_available("milestone")
	_handle_claim_all_result(ret, "历程宗务")

func _handle_claim_all_result(ret: Dictionary, title: String) -> void:
	var names_any = ret.get("claimed_names", [])
	if not (names_any is Array) or (names_any as Array).is_empty():
		EventBus.add_log("%s暂无可领取任务" % title)
		_refresh()
		return
	EventBus.add_log("%s已领取：%s" % [title, "、".join(_to_string_array(names_any as Array))])
	_refresh()

func _to_string_array(values: Array) -> Array[String]:
	var out: Array[String] = []
	for value in values:
		out.append(str(value))
	return out

func _on_tasks_changed() -> void:
	_refresh()
