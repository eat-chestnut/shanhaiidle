extends Control

const PLAYER_LABEL_SIZE := 24
const ENEMY_LABEL_SIZE := 24
const HUD_LABEL_SIZE := 28
const HUD_SUB_LABEL_SIZE := 22
const SPAWN_LABEL_SIZE := 18
const LOOT_LABEL_SIZE := 18
const MAX_LOOT_LABELS := 6

var local_state: Dictionary = {}

func _process(_delta: float) -> void:
	local_state = BattleService.get_state()
	queue_redraw()

func _draw() -> void:
	var arena_rect := Rect2(Vector2.ZERO, size)
	draw_rect(arena_rect, Color(0.09, 0.10, 0.13))
	draw_rect(arena_rect, Color(0.32, 0.36, 0.42), false, 2.0)

	var spawn: Dictionary = local_state.get("spawn", {})
	var player_state: Dictionary = local_state.get("player", {})
	var enemies: Array = local_state.get("enemies", [])
	var loots: Array = local_state.get("loot", [])

	_draw_spawn(spawn)
	_draw_player(player_state)
	_draw_loots(player_state, loots)
	_draw_enemies(enemies)
	_draw_hud(spawn, player_state, enemies.size())

func _draw_spawn(spawn: Dictionary) -> void:
	var spawn_pos: Vector2 = spawn.get("pos", Vector2.ZERO)
	var spawn_radius: float = float(spawn.get("spawn_radius", 0.0))
	if spawn_radius > 0.0:
		draw_circle(spawn_pos, spawn_radius, Color(1.0, 1.0, 1.0, 0.05))
		draw_arc(spawn_pos, spawn_radius, 0.0, TAU, 96, Color(1.0, 1.0, 1.0, 0.12), 2.0, true)

	draw_line(spawn_pos + Vector2(-4, 0), spawn_pos + Vector2(4, 0), Color.WHITE, 1.0)
	draw_line(spawn_pos + Vector2(0, -4), spawn_pos + Vector2(0, 4), Color.WHITE, 1.0)
	draw_circle(spawn_pos, 2.0, Color.WHITE)
	_draw_label(
		spawn_pos + Vector2(8, -6),
		"刷 sp_1",
		Color(1.0, 1.0, 1.0, 0.95),
		SPAWN_LABEL_SIZE,
		true
	)

func _draw_player(player_state: Dictionary) -> void:
	var player_pos: Vector2 = player_state.get("pos", Vector2.ZERO)
	var player_radius: float = float(player_state.get("radius", 18.0))
	var player_atk_range: float = float(player_state.get("atk_range", 10.0))
	var attack_visual_radius := player_atk_range + player_radius

	draw_circle(player_pos, attack_visual_radius, Color(1.0, 1.0, 1.0, 0.08))
	draw_arc(player_pos, attack_visual_radius, 0.0, TAU, 72, Color(1.0, 1.0, 1.0, 0.18), 2.0, true)
	draw_circle(player_pos, player_radius, Color(0.20, 0.82, 0.35))
	_draw_label(player_pos + Vector2(-8, 5), "我", Color.WHITE, PLAYER_LABEL_SIZE)

func _draw_enemies(enemies: Array) -> void:
	for enemy_any in enemies:
		if not (enemy_any is Dictionary):
			continue
		var enemy: Dictionary = enemy_any
		var enemy_pos: Vector2 = enemy.get("pos", Vector2.ZERO)
		var enemy_radius: float = float(enemy.get("radius", 14.0))
		var hp: int = int(enemy.get("hp", 0))
		draw_circle(enemy_pos, enemy_radius, Color(0.86, 0.18, 0.18))
		_draw_label(
			enemy_pos + Vector2(enemy_radius + 6.0, 5.0),
			"敌%s:%d" % [I18nService.stat("HP"), hp],
			Color.WHITE,
			ENEMY_LABEL_SIZE,
			true
		)

func _draw_loots(player_state: Dictionary, loots: Array) -> void:
	if loots.is_empty():
		return
	var player_pos: Vector2 = player_state.get("pos", Vector2.ZERO)
	var labeled_indices: Dictionary = {}

	for i in range(loots.size()):
		var loot_any = loots[i]
		if not (loot_any is Dictionary):
			continue
		var loot: Dictionary = loot_any
		var pos: Vector2 = loot.get("pos", Vector2.ZERO)
		var rarity := str(loot.get("rarity", "white"))
		var color := _drop_color_for_rarity(rarity)
		var loot_type := str(loot.get("type", "item"))
		if loot_type == "equip":
			draw_rect(Rect2(pos - Vector2(6, 6), Vector2(12, 12)), color, true)
		else:
			draw_circle(pos, 6.0, color)

	var labels_to_draw := mini(MAX_LOOT_LABELS, loots.size())
	for _n in range(labels_to_draw):
		var nearest_index := -1
		var best_dist := INF
		for i in range(loots.size()):
			if labeled_indices.has(i):
				continue
			var loot_i_any = loots[i]
			if not (loot_i_any is Dictionary):
				continue
			var loot_i: Dictionary = loot_i_any
			var loot_pos_i: Vector2 = loot_i.get("pos", Vector2.ZERO)
			var dist_sq := player_pos.distance_squared_to(loot_pos_i)
			if dist_sq < best_dist:
				best_dist = dist_sq
				nearest_index = i

		if nearest_index < 0:
			break
		labeled_indices[nearest_index] = true

		var loot_n: Dictionary = loots[nearest_index]
		var loot_pos_n: Vector2 = loot_n.get("pos", Vector2.ZERO)
		var label := str(loot_n.get("label", ""))
		var rarity_n := str(loot_n.get("rarity", "white"))
		_draw_label(
			loot_pos_n + Vector2(10.0, -4.0),
			label,
			_drop_color_for_rarity(rarity_n),
			LOOT_LABEL_SIZE,
			true
		)

func _draw_hud(spawn: Dictionary, player_state: Dictionary, enemy_count: int) -> void:
	var hp_now: int = int(player_state.get("hp", 0))
	var hp_max: int = int(player_state.get("hpmax", hp_now))
	var stage_name := str(local_state.get("stage_name", "未命名关卡"))
	var kills: int = int(local_state.get("kills", 0))
	var auto_seek: bool = bool(local_state.get("auto_seek", true))

	var alive: int = int(spawn.get("alive", 0))
	var alive_max: int = int(spawn.get("max", 0))
	var aggro_on := bool(spawn.get("aggro_on", false))

	var line_1 := "%s｜%s %d/%d｜击杀 %d｜场上 %d" % [stage_name, I18nService.stat("HP"), hp_now, hp_max, kills, enemy_count]
	var line_2 := "刷怪点 sp_1：%d/%d｜警戒：%s｜索敌：%s" % [
		alive,
		alive_max,
		"已进入" if aggro_on else "未进入",
		"开" if auto_seek else "关"
	]
	var elite_progress: int = int(local_state.get("elite_progress", 0))
	var elite_need: int = maxi(1, int(local_state.get("elite_need", 40)))
	var boss_progress: int = int(local_state.get("boss_progress", 0))
	var boss_need: int = maxi(1, int(local_state.get("boss_need", 120)))
	var line_3 := "精英 %d/%d    Boss %d/%d" % [elite_progress, elite_need, boss_progress, boss_need]

	var hud_pos_1 := Vector2(12, 36)
	var hud_pos_2 := hud_pos_1 + Vector2(0, 28)
	var hud_pos_3 := hud_pos_2 + Vector2(0, 24)
	var hud_text_w := maxf(
		_measure_text_width(line_1, HUD_LABEL_SIZE),
		_measure_text_width(line_2, HUD_SUB_LABEL_SIZE)
	)
	hud_text_w = maxf(hud_text_w, _measure_text_width(line_3, HUD_SUB_LABEL_SIZE))
	var hud_bg_width := hud_text_w + 20.0
	hud_bg_width = minf(size.x - 16.0, hud_bg_width)
	hud_bg_width = minf(hud_bg_width, 420.0)
	var hud_bg_rect := Rect2(hud_pos_1 + Vector2(-8, -16), Vector2(hud_bg_width, 80))
	draw_rect(hud_bg_rect, Color(0, 0, 0, 0.6), true)

	_draw_label(hud_pos_1, line_1, Color.WHITE, HUD_LABEL_SIZE, true)
	_draw_label(hud_pos_2, line_2, Color.WHITE, HUD_SUB_LABEL_SIZE, true)
	_draw_label(hud_pos_3, line_3, Color.WHITE, HUD_SUB_LABEL_SIZE, true)

func _drop_color_for_rarity(rarity: String) -> Color:
	match rarity:
		"blue":
			return Color(0.53, 0.74, 1.0, 0.95)
		"gold":
			return Color(1.0, 0.86, 0.35, 0.95)
		_:
			return Color(1.0, 1.0, 1.0, 0.95)

func _draw_label(
	pos: Vector2,
	text: String,
	color: Color,
	font_size: int,
	with_shadow: bool = false
) -> void:
	var font := get_theme_default_font()
	if font == null:
		font = ThemeDB.fallback_font
	if font_size <= 0:
		font_size = get_theme_default_font_size()
		if font_size <= 0:
			font_size = ThemeDB.fallback_font_size
	if with_shadow:
		draw_string(
			font,
			pos + Vector2(2, 2),
			text,
			HORIZONTAL_ALIGNMENT_LEFT,
			-1,
			font_size,
			Color.BLACK
		)
	draw_string(font, pos, text, HORIZONTAL_ALIGNMENT_LEFT, -1, font_size, color)

func _measure_text_width(text: String, font_size: int) -> float:
	var font := get_theme_default_font()
	if font == null:
		font = ThemeDB.fallback_font
	if font_size <= 0:
		font_size = get_theme_default_font_size()
		if font_size <= 0:
			font_size = ThemeDB.fallback_font_size
	return font.get_string_size(text, HORIZONTAL_ALIGNMENT_LEFT, -1, font_size).x
