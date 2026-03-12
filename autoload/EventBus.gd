extends Node

signal log_added(text: String)
signal inventory_updated()
signal tasks_updated()
signal mountain_god_updated()
signal patrol_updated()
signal profile_sync_requested(reason: String)

func add_log(text: String) -> void:
	emit_signal("log_added", text)

func notify_inventory_updated() -> void:
	emit_signal("inventory_updated")

func notify_tasks_updated() -> void:
	emit_signal("tasks_updated")

func notify_mountain_god_updated() -> void:
	emit_signal("mountain_god_updated")

func notify_patrol_updated() -> void:
	emit_signal("patrol_updated")

func request_profile_sync(reason: String = "manual") -> void:
	emit_signal("profile_sync_requested", reason)
