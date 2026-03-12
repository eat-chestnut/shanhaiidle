extends Control

const PAGE_MAP := "res://ui/pages/PageMap.tscn"
const INVENTORY_OVERLAY_SCENE := preload("res://ui/components/InventoryOverlay.tscn")

var _overlay: Control

func _ready() -> void:
	var bg := ColorRect.new()
	bg.anchors_preset = PRESET_FULL_RECT
	bg.anchor_right = 1.0
	bg.anchor_bottom = 1.0
	bg.color = Color(0.06, 0.06, 0.08, 1.0)
	add_child(bg)

	var overlay_any = INVENTORY_OVERLAY_SCENE.instantiate()
	if overlay_any is Control:
		_overlay = overlay_any
		add_child(_overlay)
		if _overlay.has_signal("closed"):
			_overlay.connect("closed", _on_overlay_closed)
		if _overlay.has_method("open"):
			_overlay.call_deferred("open")
	EventBus.add_log("进入工坊：可进行打造、升星与升品。")

func _on_overlay_closed() -> void:
	get_tree().change_scene_to_file(PAGE_MAP)
