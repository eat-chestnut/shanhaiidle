extends Control

signal challenge_pressed(dungeon_id: String)
signal sweep_pressed(dungeon_id: String)
signal plus_pressed(dungeon_id: String)

@onready var _banner: TextureRect = $CardPanel/Banner
@onready var _gray_mask: ColorRect = $CardPanel/GrayMask
@onready var _lbl_title: Label = $CardPanel/Overlay/TopRow/LblTitleFallback
@onready var _lbl_desc: Label = $CardPanel/Overlay/LblDescFallback
@onready var _lbl_remaining: Label = $CardPanel/Overlay/TopRow/LblRemaining
@onready var _btn_plus: Button = $CardPanel/Overlay/TopRow/BtnPlus
@onready var _lbl_recommended: Label = $CardPanel/Overlay/MidRow/LblRecommended
@onready var _lbl_unlock: Label = $CardPanel/Overlay/MidRow/LblUnlockHint
@onready var _lbl_reward: Label = $CardPanel/Overlay/LblReward
@onready var _btn_challenge: Button = $CardPanel/Overlay/BottomRow/BtnChallenge
@onready var _btn_sweep: Button = $CardPanel/Overlay/BottomRow/BtnSweep

var _dungeon_id := ""

func _ready() -> void:
	if not _btn_challenge.pressed.is_connected(_on_challenge_pressed):
		_btn_challenge.pressed.connect(_on_challenge_pressed)
	if not _btn_sweep.pressed.is_connected(_on_sweep_pressed):
		_btn_sweep.pressed.connect(_on_sweep_pressed)
	if not _btn_plus.pressed.is_connected(_on_plus_pressed):
		_btn_plus.pressed.connect(_on_plus_pressed)

func bind_data(data: Dictionary) -> void:
	_dungeon_id = str(data.get("dungeon_id", "")).strip_edges()
	var title := str(data.get("title", _dungeon_id)).strip_edges()
	var desc := str(data.get("desc", "")).strip_edges()
	var is_unlocked := bool(data.get("is_unlocked", false))
	var can_challenge := bool(data.get("can_challenge", false))
	var can_sweep := bool(data.get("is_sweep_available", false))
	var remaining := int(data.get("remaining_count", 0))
	var daily_limit := int(data.get("daily_limit", 0))
	var unlock_level := int(data.get("unlock_level", 1))
	var unlock_stage_name := str(data.get("unlock_stage_name", "")).strip_edges()
	var unlock_hint := str(data.get("unlock_hint", "")).strip_edges()
	var rec_power := int(data.get("recommended_power", 0))
	var show_plus := bool(data.get("show_plus_button", false))
	var current_level := int(data.get("current_level", 1))
	var max_level := int(data.get("max_level", 1))
	var upgrade_cost_summary := str(data.get("upgrade_cost_summary", "")).strip_edges()
	var reward_preview_any = data.get("reward_preview", [])
	var reward_preview: Array = reward_preview_any if reward_preview_any is Array else []

	var banner_path := str(data.get("banner_image", "")).strip_edges()
	var tex: Texture2D = null
	if not banner_path.is_empty() and ResourceLoader.exists(banner_path):
		var loaded = load(banner_path)
		if loaded is Texture2D:
			tex = loaded
	if tex == null:
		tex = _make_placeholder(data)
	_banner.texture = tex

	var has_banner_text := tex != null and not banner_path.is_empty() and ResourceLoader.exists(banner_path)
	_lbl_title.visible = not has_banner_text
	_lbl_desc.visible = not has_banner_text
	_lbl_title.text = title
	_lbl_desc.text = desc

	if daily_limit <= 0 or remaining < 0:
		_lbl_remaining.text = "副本Lv%d/%d｜今日剩余：∞" % [current_level, max_level]
	else:
		_lbl_remaining.text = "副本Lv%d/%d｜今日剩余：%d" % [current_level, max_level, remaining]

	_lbl_recommended.text = "推荐战力：%d" % rec_power
	if not is_unlocked:
		if not unlock_hint.is_empty():
			_lbl_unlock.text = unlock_hint
		else:
			_lbl_unlock.text = "%d级解锁" % unlock_level if unlock_stage_name.is_empty() else "需Lv%d并通关%s" % [unlock_level, unlock_stage_name]
	elif daily_limit > 0 and remaining <= 0:
		_lbl_unlock.text = "今日次数已用尽"
	elif not can_sweep:
		_lbl_unlock.text = "需先通关后开启扫荡"
	else:
		_lbl_unlock.text = upgrade_cost_summary if not upgrade_cost_summary.is_empty() else "已解锁"

	var reward_text := _build_reward_text(reward_preview)
	_lbl_reward.text = reward_text if not reward_text.is_empty() else "奖励预览：—"

	_btn_plus.visible = show_plus
	_btn_plus.disabled = not is_unlocked or current_level >= max_level

	_btn_challenge.disabled = not can_challenge
	_btn_sweep.disabled = not can_sweep

	_gray_mask.visible = not is_unlocked
	if is_unlocked:
		self.modulate = Color(1, 1, 1, 1)
	else:
		self.modulate = Color(0.75, 0.75, 0.75, 1)

func _make_placeholder(data: Dictionary) -> Texture2D:
	var tab := str(data.get("tab", "normal"))
	var c_top := Color(0.16, 0.21, 0.33, 1)
	var c_bottom := Color(0.08, 0.11, 0.2, 1)
	match tab:
		"elite":
			c_top = Color(0.37, 0.18, 0.18, 1)
			c_bottom = Color(0.2, 0.08, 0.08, 1)
		"event":
			c_top = Color(0.25, 0.2, 0.38, 1)
			c_bottom = Color(0.12, 0.08, 0.2, 1)
	var img := Image.create(1200, 280, false, Image.FORMAT_RGBA8)
	for y in range(img.get_height()):
		var t := float(y) / float(maxi(1, img.get_height() - 1))
		var c := c_top.lerp(c_bottom, t)
		for x in range(img.get_width()):
			img.set_pixel(x, y, c)
	return ImageTexture.create_from_image(img)

func _build_reward_text(arr: Array) -> String:
	if arr.is_empty():
		return ""
	var parts: Array[String] = []
	var max_show := mini(4, arr.size())
	for i in range(max_show):
		parts.append(str(arr[i]))
	if arr.size() > max_show:
		parts.append("…")
	return "奖励预览：%s" % "、".join(parts)

func _on_challenge_pressed() -> void:
	if _dungeon_id.is_empty():
		return
	emit_signal("challenge_pressed", _dungeon_id)

func _on_sweep_pressed() -> void:
	if _dungeon_id.is_empty():
		return
	emit_signal("sweep_pressed", _dungeon_id)

func _on_plus_pressed() -> void:
	if _dungeon_id.is_empty():
		return
	emit_signal("plus_pressed", _dungeon_id)
