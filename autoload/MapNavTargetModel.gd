extends Node

var _pending_target: Dictionary = {}

func set_target(stage_id: String, difficulty_index: int = -1, source: String = "") -> void:
	var sid := stage_id.strip_edges()
	if sid.is_empty():
		return
	_pending_target = {
		"stage_id": sid,
		"difficulty_index": difficulty_index,
		"source": source,
	}

func has_target() -> bool:
	return not _pending_target.is_empty()

func peek_target() -> Dictionary:
	if _pending_target.is_empty():
		return {}
	return _pending_target.duplicate(true)

func consume_target() -> Dictionary:
	if _pending_target.is_empty():
		return {}
	var out := _pending_target.duplicate(true)
	_pending_target.clear()
	return out

func clear() -> void:
	_pending_target.clear()
