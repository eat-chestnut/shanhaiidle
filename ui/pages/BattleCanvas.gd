extends Control

const PLAYER_LABEL_SIZE := 24
const ENEMY_LABEL_SIZE := 24
const HUD_LABEL_SIZE := 28
const SPAWN_LABEL_SIZE := 18
const DEFAULT_MONSTER_ID := "mob_a"

var player := {
	"pos": Vector2.ZERO,
	"radius": 18.0,
	"atk": 12,
	"def": 3,
	"attack_interval": 0.25,
}

var stage_name: String = "未命名关卡"
var monsters_cfg: Dictionary = {}
var spawn_points_cfg: Array[Dictionary] = []
var spawn_runtime: Dictionary = {}
var spawn_order: Array[String] = []

var arena_rect: Rect2 = Rect2(Vector2.ZERO, Vector2.ZERO)
var _last_canvas_size: Vector2 = Vector2.ZERO

var battle_time := 0.0
var enemies: Array[Dictionary] = []
var kills := 0

var _attack_timer := 0.0

func _ready() -> void:
	randomize()
	_load_battle_cfg()
	_recalc_arena(true)
	player["pos"] = arena_rect.position + arena_rect.size * 0.5

func _process(delta: float) -> void:
	battle_time += delta
	_recalc_arena()
	player["pos"] = arena_rect.position + arena_rect.size * 0.5

	_process_spawn_points()

	_attack_timer += delta
	var attack_interval: float = float(player["attack_interval"])
	while _attack_timer >= attack_interval:
		_attack_timer -= attack_interval
		_auto_attack()

	queue_redraw()

func _load_battle_cfg() -> void:
	var battle_cfg: Dictionary = {}
	var config_service = get_node_or_null("/root/ConfigService")
	if config_service != null and config_service.has_method("get_cfg"):
		var full_cfg = config_service.call("get_cfg")
		if full_cfg is Dictionary:
			battle_cfg = full_cfg.get("battle", {})
	if battle_cfg.is_empty():
		battle_cfg = _load_battle_cfg_from_file()

	stage_name = str(battle_cfg.get("stage_name", "未命名关卡"))
	_load_monster_templates(battle_cfg)
	_load_spawn_points(battle_cfg)
	_init_spawn_runtime()

func _load_battle_cfg_from_file() -> Dictionary:
	var path := "res://data/battle_config.json"
	if not FileAccess.file_exists(path):
		return {}

	var text := FileAccess.get_file_as_string(path)
	var parsed = JSON.parse_string(text)
	if parsed is Dictionary:
		return parsed.get("battle", {})
	return {}

func _load_monster_templates(battle_cfg: Dictionary) -> void:
	monsters_cfg.clear()
	var monster_list = battle_cfg.get("monsters", [])
	if monster_list is Array:
		for monster_any in monster_list:
			if monster_any is Dictionary:
				var monster: Dictionary = monster_any
				var monster_id: String = str(monster.get("id", ""))
				if not monster_id.is_empty():
					monsters_cfg[monster_id] = monster.duplicate(true)

func _load_spawn_points(battle_cfg: Dictionary) -> void:
	spawn_points_cfg.clear()
	var spawn_points = battle_cfg.get("spawn_points", [])
	if spawn_points is Array:
		for spawn_any in spawn_points:
			if spawn_any is Dictionary:
				var spawn_point: Dictionary = spawn_any
				spawn_points_cfg.append(spawn_point.duplicate(true))

func _init_spawn_runtime() -> void:
	spawn_runtime.clear()
	spawn_order.clear()

	for i in spawn_points_cfg.size():
		var spawn_point: Dictionary = spawn_points_cfg[i]
		var spawn_id: String = str(spawn_point.get("id", "sp_%d" % [i + 1]))
		if spawn_id.is_empty():
			spawn_id = "sp_%d" % [i + 1]
		while spawn_runtime.has(spawn_id):
			spawn_id += "_dup"

		var runtime := {
			"id": spawn_id,
			"x_ratio": clampf(float(spawn_point.get("x_ratio", 0.5)), 0.0, 1.0),
			"y_ratio": clampf(float(spawn_point.get("y_ratio", 0.5)), 0.0, 1.0),
			"respawn_s": maxf(0.05, float(spawn_point.get("respawn_s", 1.5))),
			"max_alive": maxi(0, int(spawn_point.get("max_alive", 1))),
			"monster_id": str(spawn_point.get("monster_id", DEFAULT_MONSTER_ID)),
			"alive_count": 0,
			"next_spawn_time": 0.0,
			"pos": Vector2.ZERO,
		}
		spawn_runtime[spawn_id] = runtime
		spawn_order.append(spawn_id)

	_update_spawn_positions()

func _recalc_arena(force: bool = false) -> void:
	if not force and _last_canvas_size.is_equal_approx(size):
		return
	_last_canvas_size = size
	arena_rect = Rect2(Vector2.ZERO, size)
	_update_spawn_positions()

func _update_spawn_positions() -> void:
	for spawn_id in spawn_order:
		if not spawn_runtime.has(spawn_id):
			continue
		var runtime: Dictionary = spawn_runtime[spawn_id]
		var x_ratio: float = clampf(float(runtime.get("x_ratio", 0.5)), 0.0, 1.0)
		var y_ratio: float = clampf(float(runtime.get("y_ratio", 0.5)), 0.0, 1.0)
		runtime["pos"] = arena_rect.position + Vector2(arena_rect.size.x * x_ratio, arena_rect.size.y * y_ratio)
		spawn_runtime[spawn_id] = runtime

func _process_spawn_points() -> void:
	for spawn_id in spawn_order:
		if not spawn_runtime.has(spawn_id):
			continue
		var runtime: Dictionary = spawn_runtime[spawn_id]
		var alive_count: int = int(runtime.get("alive_count", 0))
		var max_alive: int = maxi(0, int(runtime.get("max_alive", 0)))
		var next_spawn_time: float = float(runtime.get("next_spawn_time", 0.0))
		var respawn_s: float = maxf(0.05, float(runtime.get("respawn_s", 1.5)))

		if alive_count < max_alive and battle_time >= next_spawn_time:
			_spawn_enemy_from_point(spawn_id, runtime)
			alive_count += 1
			runtime["alive_count"] = alive_count
			runtime["next_spawn_time"] = battle_time + respawn_s
			spawn_runtime[spawn_id] = runtime

func _spawn_enemy_from_point(spawn_id: String, runtime: Dictionary) -> void:
	var monster_id: String = str(runtime.get("monster_id", DEFAULT_MONSTER_ID))
	var template: Dictionary = {}
	var template_any = monsters_cfg.get(monster_id, {})
	if template_any is Dictionary:
		template = template_any

	var enemy := {
		"spawn_id": spawn_id,
		"monster_id": monster_id,
		"pos": runtime.get("pos", Vector2.ZERO),
		"radius": float(template.get("radius", 14)),
		"hp": int(template.get("hp", 30)),
		"def": int(template.get("def", 2)),
	}
	enemies.append(enemy)

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
		var spawn_id: String = str(enemy.get("spawn_id", ""))
		enemies.remove_at(target_index)
		_decrease_spawn_alive(spawn_id)
		kills += 1
		if kills % 5 == 0:
			EventBus.add_log("击杀累计：%d（场上%d）" % [kills, enemies.size()])
	else:
		enemies[target_index] = enemy

func _decrease_spawn_alive(spawn_id: String) -> void:
	if spawn_id.is_empty() or not spawn_runtime.has(spawn_id):
		return
	var runtime: Dictionary = spawn_runtime[spawn_id]
	var alive_count: int = maxi(0, int(runtime.get("alive_count", 0)) - 1)
	var respawn_s: float = maxf(0.05, float(runtime.get("respawn_s", 1.5)))
	var gate_time: float = battle_time + respawn_s
	var next_spawn_time: float = float(runtime.get("next_spawn_time", 0.0))
	runtime["alive_count"] = alive_count
	if next_spawn_time < gate_time:
		runtime["next_spawn_time"] = gate_time
	spawn_runtime[spawn_id] = runtime

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
	draw_rect(arena_rect, Color(0.09, 0.10, 0.13))
	draw_rect(arena_rect, Color(0.32, 0.36, 0.42), false, 2.0)

	_draw_spawn_points()

	var player_pos: Vector2 = player["pos"]
	var player_radius: float = player["radius"]
	draw_circle(player_pos, player_radius, Color(0.20, 0.82, 0.35))
	_draw_label(player_pos + Vector2(-8, 5), "我", Color.WHITE, PLAYER_LABEL_SIZE)

	for enemy in enemies:
		var enemy_dict: Dictionary = enemy
		var enemy_pos: Vector2 = enemy_dict["pos"]
		var enemy_radius: float = enemy_dict["radius"]
		var hp: int = enemy_dict["hp"]
		draw_circle(enemy_pos, enemy_radius, Color(0.86, 0.18, 0.18))
		_draw_label(
			enemy_pos + Vector2(enemy_radius + 6.0, 5.0),
			"敌 HP:%d" % hp,
			Color.WHITE,
			ENEMY_LABEL_SIZE,
			true
		)

	var hud_text := "%s｜击杀:%d｜场上:%d｜刷怪点:%d" % [stage_name, kills, enemies.size(), spawn_order.size()]
	var hud_pos := Vector2(12, 30)
	var hud_bg_width: float = minf(size.x - 16.0, _measure_text_width(hud_text, HUD_LABEL_SIZE) + 20.0)
	var hud_bg_rect := Rect2(hud_pos + Vector2(-8, -28), Vector2(hud_bg_width, 40))
	draw_rect(hud_bg_rect, Color(0, 0, 0, 0.6), true)

	_draw_label(
		hud_pos,
		hud_text,
		Color.WHITE,
		HUD_LABEL_SIZE,
		true
	)

func _draw_spawn_points() -> void:
	for i in spawn_order.size():
		var spawn_id: String = spawn_order[i]
		if not spawn_runtime.has(spawn_id):
			continue
		var runtime: Dictionary = spawn_runtime[spawn_id]
		var pos: Vector2 = runtime.get("pos", Vector2.ZERO)
		draw_line(pos + Vector2(-4, 0), pos + Vector2(4, 0), Color.WHITE, 1.0)
		draw_line(pos + Vector2(0, -4), pos + Vector2(0, 4), Color.WHITE, 1.0)
		draw_circle(pos, 2.0, Color.WHITE)
		_draw_label(
			pos + Vector2(8, -6),
			"刷%d" % [i + 1],
			Color(1.0, 1.0, 1.0, 0.95),
			SPAWN_LABEL_SIZE,
			true
		)

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
