extends Control

@onready var bg: Panel = $Bg
@onready var lb: Label = $Text

func set_value(v: int, show_dot_if_zero: bool = false) -> void:
	if v <= 0:
		visible = show_dot_if_zero
		lb.text = ""
		return
	visible = true
	if v >= 10:
		lb.text = "9+"
	else:
		lb.text = str(v)

func set_dot(show: bool) -> void:
	visible = show
	lb.text = ""
