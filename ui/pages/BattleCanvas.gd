extends Control

var player := {
	"pos": Vector2.ZERO,
	"radius": 18.0,
	"atk": 12,
	"def": 3,
	"attack_interval": 0.25,
}

var enemies: Array[Dictionary] = []
var spawn_interval := 1.5
var kills := 0

var _spawn_timer := 0.0
var _attack_timer := 0.0

func _ready() -> void:
	randomize()
	player["pos"] = size * 0.5

func _process(delta: float) -> void:
	player["pos"] = size * 0.5

	_spawn_timer += delta
	while _spawn_timer >= spawn_interval:
		_spawn_timer -= spawn_interval
		_try_spawn_enemy()

	_attack_timer += delta
	var attack_interval: float = float(player["attack_interval"])
	while _attack_timer >= attack_interval:
		_attack_timer -= attack_interval
		_auto_attack()

	queue_redraw()

func _try_spawn_enemy() -> void:
	var radius := 14.0
	var enemy := {
		"pos": _random_border_position(radius),
		"radius": radius,
		"hp": 30,
		"def": 2,
	}
	enemies.append(enemy)

func _random_border_position(radius: float) -> Vector2:
	var padding := radius + 4.0
	var x_min: float = padding
	var x_max: float = size.x - padding
	var y_min: float = padding
	var y_max: float = size.y - padding
	if x_max < x_min:
		x_max = x_min
	if y_max < y_min:
		y_max = y_min

	var side := int(randi() % 4)
	match side:
		0:
			return Vector2(randf_range(x_min, x_max), y_min)
		1:
			return Vector2(x_max, randf_range(y_min, y_max))
		2:
			return Vector2(randf_range(x_min, x_max), y_max)
		_:
			return Vector2(x_min, randf_range(y_min, y_max))

func _auto_attack() -> void:
	if enemies.is_empty():
		return

	var target_index := _find_nearest_enemy_index()
	if target_index < 0:
		return

	var enemy: Dictionary = enemies[target_index]
	var damage: int = int(player["atk"]) - int(enemy["def"])
	if damage < 1:
		damage = 1
	enemy["hp"] = int(enemy["hp"]) - damage

	if int(enemy["hp"]) <= 0:
		enemies.remove_at(target_index)
		kills += 1
		EventBus.add_log("击杀+1（总击杀%d，场上%d）" % [kills, enemies.size()])
	else:
		enemies[target_index] = enemy

func _find_nearest_enemy_index() -> int:
	var player_pos: Vector2 = player["pos"]
	var best_index := -1
	var best_distance_sq := INF

	for i in enemies.size():
		var enemy_pos: Vector2 = enemies[i]["pos"]
		var distance_sq := player_pos.distance_squared_to(enemy_pos)
		if distance_sq < best_distance_sq:
			best_distance_sq = distance_sq
			best_index = i

	return best_index

func _draw() -> void:
	var battle_rect := Rect2(Vector2.ZERO, size)
	draw_rect(battle_rect, Color(0.09, 0.10, 0.13))
	draw_rect(battle_rect, Color(0.32, 0.36, 0.42), false, 2.0)

	var player_pos: Vector2 = player["pos"]
	var player_radius: float = player["radius"]
	draw_circle(player_pos, player_radius, Color(0.20, 0.82, 0.35))
	_draw_label(player_pos + Vector2(-8, 5), "我", Color.WHITE)

	var label_enemy_indices := _get_nearest_enemy_index_set(15)
	for i in enemies.size():
		var enemy: Dictionary = enemies[i]
		var enemy_pos: Vector2 = enemy["pos"]
		var enemy_radius: float = enemy["radius"]
		var hp: int = enemy["hp"]
		draw_circle(enemy_pos, enemy_radius, Color(0.86, 0.18, 0.18))
		if label_enemy_indices.has(i):
			_draw_label(enemy_pos + Vector2(enemy_radius + 6.0, 5.0), "敌 HP:%d" % hp, Color(1.0, 0.90, 0.90))

	_draw_label(
		Vector2(12, 22),
		"Kills: %d  Enemies: %d" % [kills, enemies.size()],
		Color(0.90, 0.95, 1.0)
	)

func _get_nearest_enemy_index_set(limit: int) -> Dictionary:
	var label_indices := {}
	if limit <= 0 or enemies.is_empty():
		return label_indices

	var player_pos: Vector2 = player["pos"]
	var distance_pairs: Array[Dictionary] = []
	for i in enemies.size():
		var enemy_pos: Vector2 = enemies[i]["pos"]
		distance_pairs.append({
			"idx": i,
			"dist": player_pos.distance_squared_to(enemy_pos),
		})

	distance_pairs.sort_custom(func(a: Dictionary, b: Dictionary) -> bool:
		return float(a["dist"]) < float(b["dist"])
	)

	var label_count := mini(limit, distance_pairs.size())
	for i in label_count:
		label_indices[int(distance_pairs[i]["idx"])] = true
	return label_indices

func _draw_label(pos: Vector2, text: String, color: Color) -> void:
	var font := get_theme_default_font()
	var font_size := get_theme_default_font_size()
	if font == null:
		font = ThemeDB.fallback_font
		font_size = ThemeDB.fallback_font_size
	draw_string(font, pos, text, HORIZONTAL_ALIGNMENT_LEFT, -1, font_size, color)
