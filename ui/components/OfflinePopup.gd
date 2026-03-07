extends Control

const ITEM_SHOW_LIMIT := 12

@onready var _dim_bg: ColorRect = $DimBG
@onready var _title: Label = $Panel/VBox/Title
@onready var _body: RichTextLabel = $Panel/VBox/Body
@onready var _btn_ok: Button = $Panel/VBox/BtnOK

func _ready() -> void:
	visible = false
	_title.text = "离线收益"
	_btn_ok.text = "确定"
	if not _btn_ok.pressed.is_connected(close):
		_btn_ok.pressed.connect(close)
	if not _dim_bg.gui_input.is_connected(_on_dim_bg_gui_input):
		_dim_bg.gui_input.connect(_on_dim_bg_gui_input)

func open(summary: Dictionary) -> void:
	if summary.is_empty():
		return
	visible = true
	_body.text = _build_summary_text(summary)

func close() -> void:
	visible = false

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

func _build_summary_text(summary: Dictionary) -> String:
	var seconds := maxi(0, int(summary.get("seconds", 0)))
	var kills := maxi(0, int(summary.get("kills", 0)))
	var elite := maxi(0, int(summary.get("elite", 0)))
	var boss := maxi(0, int(summary.get("boss", 0)))
	var exp := maxi(0, int(summary.get("exp", 0)))
	var equip_count := maxi(0, int(summary.get("equip_count", 0)))

	var lines: Array[String] = []
	lines.append("离线时间：%s" % _format_mm_ss(seconds))
	lines.append("击杀：%d（精英%d / Boss%d）" % [kills, elite, boss])
	lines.append("经验：+%d" % exp)
	lines.append("装备：+%d" % equip_count)
	lines.append("物品：")

	var items_any = summary.get("items", {})
	var rows := _sorted_item_rows(items_any)
	if rows.is_empty():
		lines.append("  - 无")
	else:
		var show_count := mini(ITEM_SHOW_LIMIT, rows.size())
		for i in range(show_count):
			var row_any = rows[i]
			if not (row_any is Dictionary):
				continue
			var row: Dictionary = row_any
			lines.append("  - %s x%d" % [str(row.get("name", "")), int(row.get("count", 0))])
		if rows.size() > ITEM_SHOW_LIMIT:
			lines.append("  ...")

	return "\n".join(lines)

func _sorted_item_rows(items_any: Variant) -> Array:
	var rows: Array = []
	if not (items_any is Dictionary):
		return rows
	var items: Dictionary = items_any
	var name_map := _build_item_name_map()
	for item_id_any in items.keys():
		var item_id := str(item_id_any)
		if item_id.is_empty():
			continue
		var count := maxi(0, int(items.get(item_id_any, 0)))
		if count <= 0:
			continue
		rows.append({
			"id": item_id,
			"name": str(name_map.get(item_id, item_id)),
			"count": count,
		})
	rows.sort_custom(_sort_item_rows)
	return rows

func _sort_item_rows(a: Dictionary, b: Dictionary) -> bool:
	var ac := int(a.get("count", 0))
	var bc := int(b.get("count", 0))
	if ac != bc:
		return ac > bc
	return str(a.get("name", "")) < str(b.get("name", ""))

func _build_item_name_map() -> Dictionary:
	var out: Dictionary = {}
	var cfg: Dictionary = ConfigService.get_cfg()
	var items_db_any = cfg.get("items_db", {})
	if not (items_db_any is Dictionary):
		return out
	var items_any = (items_db_any as Dictionary).get("items", [])
	if not (items_any is Array):
		return out
	for row_any in items_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		var item_id := str(row.get("id", ""))
		if item_id.is_empty():
			continue
		out[item_id] = str(row.get("name", item_id))
	return out

func _format_mm_ss(seconds: int) -> String:
	var total_seconds := maxi(0, seconds)
	var mm := int(total_seconds / 60)
	var ss := int(total_seconds % 60)
	return "%02d:%02d" % [mm, ss]
