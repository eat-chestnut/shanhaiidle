extends Control

@onready var bg: Panel = $Bg
@onready var lb: Label = $Text

func _apply_layout(w: float, h: float) -> void:
	anchor_left = 1.0
	anchor_right = 1.0
	anchor_top = 0.0
	anchor_bottom = 0.0
	var right_pad := 6.0
	var top_pad := 6.0
	offset_right = -right_pad
	offset_left = -right_pad - w
	offset_top = top_pad
	offset_bottom = top_pad + h
	mouse_filter = Control.MOUSE_FILTER_IGNORE

func set_value(v: int, show_dot_if_zero: bool = false) -> void:
	if v <= 0:
		visible = show_dot_if_zero
		lb.text = ""
		if show_dot_if_zero:
			_apply_layout(12.0, 12.0)
		return
	visible = true
	if v >= 10:
		lb.text = "9+"
	else:
		lb.text = str(v)
	_apply_layout(22.0, 18.0)

func set_dot(show: bool) -> void:
	visible = show
	lb.text = ""
	_apply_layout(12.0, 12.0)
