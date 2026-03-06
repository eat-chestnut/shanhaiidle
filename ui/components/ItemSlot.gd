extends Control

@onready var _name_label: Label = $Content/Name
@onready var _count_label: Label = $Content/Count
@onready var _border_overlay: Panel = $BorderOverlay

func _ready() -> void:
	clear()

func set_item(item_id: String, name: String, rarity: String, count: int, color: Color) -> void:
	_name_label.text = name if not name.is_empty() else item_id
	_count_label.text = "x%d" % count if count > 1 else ""
	var border_color := color
	border_color.a = 0.8
	_apply_border_style(border_color)

func clear() -> void:
	_name_label.text = "—"
	_count_label.text = ""
	_apply_border_style(Color(1.0, 1.0, 1.0, 0.18))

func _apply_border_style(border_color: Color) -> void:
	var style := StyleBoxFlat.new()
	style.bg_color = Color(0.0, 0.0, 0.0, 0.18)
	style.border_color = border_color
	style.set_border_width_all(2)
	style.set_corner_radius_all(6)
	_border_overlay.add_theme_stylebox_override("panel", style)
