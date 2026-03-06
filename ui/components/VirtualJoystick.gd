extends Control

signal vector_changed(v: Vector2)

@export var deadzone := 0.10

var _vector := Vector2.ZERO
var _raw_vector := Vector2.ZERO
var _active_touch_id := -1
var _mouse_active := false

func _ready() -> void:
	mouse_filter = Control.MOUSE_FILTER_STOP
	queue_redraw()

func get_vector() -> Vector2:
	return _vector

func _input(event: InputEvent) -> void:
	var local_rect := Rect2(Vector2.ZERO, size)

	if event is InputEventMouseButton:
		var mouse_button: InputEventMouseButton = event
		if mouse_button.button_index != MOUSE_BUTTON_LEFT:
			return
		if mouse_button.pressed:
			var press_local := to_local(mouse_button.position)
			if local_rect.has_point(press_local):
				_mouse_active = true
				_update_from_local_pos(press_local)
		elif _mouse_active:
			_mouse_active = false
			_reset_vector()
		return

	if event is InputEventMouseMotion and _mouse_active and Input.is_mouse_button_pressed(MOUSE_BUTTON_LEFT):
		var mouse_motion: InputEventMouseMotion = event
		_update_from_local_pos(to_local(mouse_motion.position))
		return

	if event is InputEventScreenTouch:
		var touch: InputEventScreenTouch = event
		var touch_local := to_local(touch.position)
		if touch.pressed:
			if _active_touch_id == -1 and local_rect.has_point(touch_local):
				_active_touch_id = touch.index
				_update_from_local_pos(touch_local)
		elif touch.index == _active_touch_id:
			_active_touch_id = -1
			_reset_vector()
		return

	if event is InputEventScreenDrag:
		var drag: InputEventScreenDrag = event
		if drag.index == _active_touch_id:
			_update_from_local_pos(to_local(drag.position))

func _notification(what: int) -> void:
	if what == NOTIFICATION_RESIZED:
		queue_redraw()

func _draw() -> void:
	var center := size * 0.5
	var base_radius := _base_radius()
	if base_radius <= 1.0:
		return

	draw_circle(center, base_radius, Color(1.0, 1.0, 1.0, 0.18))
	draw_arc(center, base_radius, 0.0, TAU, 64, Color(1.0, 1.0, 1.0, 0.30), 2.0, true)

	var knob_radius := maxf(18.0, base_radius * 0.35)
	var knob_center := center + _raw_vector * base_radius
	draw_circle(knob_center, knob_radius, Color(1.0, 1.0, 1.0, 0.45))
	draw_arc(knob_center, knob_radius, 0.0, TAU, 48, Color(1.0, 1.0, 1.0, 0.75), 2.0, true)

func _base_radius() -> float:
	return maxf(0.0, minf(size.x, size.y) * 0.5 - 6.0)

func _update_from_local_pos(local_pos: Vector2) -> void:
	var center := size * 0.5
	var base_radius := _base_radius()
	if base_radius <= 1.0:
		_set_vector(Vector2.ZERO, Vector2.ZERO)
		return

	var raw := (local_pos - center) / base_radius
	var raw_len := raw.length()
	if raw_len > 1.0:
		raw /= raw_len
		raw_len = 1.0

	var out := raw
	if raw_len < deadzone:
		out = Vector2.ZERO
	_set_vector(raw, out)

func _reset_vector() -> void:
	_set_vector(Vector2.ZERO, Vector2.ZERO)

func _set_vector(raw: Vector2, out: Vector2) -> void:
	raw = Vector2(clampf(raw.x, -1.0, 1.0), clampf(raw.y, -1.0, 1.0))
	out = Vector2(clampf(out.x, -1.0, 1.0), clampf(out.y, -1.0, 1.0))

	if _raw_vector.is_equal_approx(raw) and _vector.is_equal_approx(out):
		return

	_raw_vector = raw
	_vector = out
	vector_changed.emit(_vector)
	queue_redraw()
