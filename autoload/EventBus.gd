extends Node

signal log_added(text: String)

func add_log(text: String) -> void:
	emit_signal("log_added", text)
