extends Node

signal log_added(text: String)
signal inventory_updated()

func add_log(text: String) -> void:
	emit_signal("log_added", text)

func notify_inventory_updated() -> void:
	emit_signal("inventory_updated")
