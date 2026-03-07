extends Control

@onready var _bg: TextureRect = $Bg
@onready var _icon: TextureRect = $Icon
@onready var _name_label: Label = $Name
@onready var _count_label: Label = $Count

var _tex_cache: Dictionary = {}

func _ready() -> void:
	if not resized.is_connected(_on_resized):
		resized.connect(_on_resized)
	clear()
	_on_resized()

func set_item(item_id: String, name: String, rarity: String, count: int, icon_path: String) -> void:
	var cfg: Dictionary = ConfigService.get_cfg()
	var ui_frames_any = cfg.get("ui_frames", {})
	var ui_frames: Dictionary = ui_frames_any if ui_frames_any is Dictionary else {}
	var frame_path := str(ui_frames.get(rarity, ui_frames.get("white", "")))
	_set_bg_texture(frame_path)

	var resolved_icon := icon_path if not icon_path.is_empty() else ""
	_set_icon_texture(resolved_icon)
	_icon.visible = _icon.texture != null

	_name_label.text = name if not name.is_empty() else item_id
	_count_label.text = "x%d" % count if count > 1 else ""

func clear() -> void:
	var cfg: Dictionary = ConfigService.get_cfg()
	var ui_frames_any = cfg.get("ui_frames", {})
	var ui_frames: Dictionary = ui_frames_any if ui_frames_any is Dictionary else {}
	_set_bg_texture(str(ui_frames.get("empty", "")))
	_icon.visible = false
	_name_label.text = ""
	_count_label.text = ""

func _on_resized() -> void:
	var side := minf(size.x, size.y)
	var icon_side := int(clampf(side * 0.66, 54.0, 88.0))
	_icon.custom_minimum_size = Vector2(icon_side, icon_side)
	_icon.offset_left = -icon_side * 0.5
	_icon.offset_top = -icon_side * 0.5
	_icon.offset_right = icon_side * 0.5
	_icon.offset_bottom = icon_side * 0.5

func _set_bg_texture(path: String) -> void:
	var tex := _load_tex(path)
	_bg.texture = tex

func _set_icon_texture(path: String) -> void:
	var tex := _load_tex(path)
	_icon.texture = tex

func _load_tex(path: String) -> Texture2D:
	if path.is_empty():
		return null
	if _tex_cache.has(path):
		var cached = _tex_cache[path]
		if cached is Texture2D:
			return cached
	var loaded := load(path)
	if loaded is Texture2D:
		var tex: Texture2D = loaded
		_tex_cache[path] = tex
		return tex
	return null
