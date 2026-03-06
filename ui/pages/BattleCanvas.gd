extends Control

const PLAYER_LABEL_SIZE := 24
const ENEMY_LABEL_SIZE := 24
const HUD_LABEL_SIZE := 28
const HUD_SUB_LABEL_SIZE := 22
const SPAWN_LABEL_SIZE := 18
const DEFAULT_MONSTER_ID := "mob_a"
const DEFAULT_ENEMY_ATK := 6
const ENEMY_ATTACK_INTERVAL := 0.8
const HIT_LOG_INTERVAL := 1.0

var player := {
	"pos": Vector2.ZERO,
	"hp": 200,
	"max_hp": 200,
	"radius": 18.0,
	"atk": 12,
	"def": 4,
	"speed": 140.0,
	"attack_interval": 0.25,
	"attack_range": 10.0,
}

var stage_name: String = "未命名关卡"
var monsters_cfg: Dictionary = {}
var monster_default_cfg: Dictionary = {}
var spawn_points_cfg: Array[Dictionary] = []
var spawn_rt: Dictionary = {}
var spawn_order: Array[String] = []
var drop_chance := 0.0
var drop_weights: Dictionary = {}
var drop_items: Dictionary = {}

var arena_rect: Rect2 = Rect2(Vector2.ZERO, Vector2.ZERO)
var _last_canvas_size: Vector2 = Vector2.ZERO

var battle_time := 0.0
var enemies: Array[Dictionary] = []
var kills := 0

var _attack_timer := 0.0
var _hit_log_cd := 0.0

func _ready() -> void:
	randomize()
	_load_battle_cfg()
	_recalc_arena(true)
	player["pos"] = arena_rect.position + arena_rect.size * 0.5

func _process(delta: float) -> void:
	battle_time += delta
	_recalc_arena()
	player["pos"] = _clamp_pos_in_arena(player["pos"], float(player["radius"]))
	if _hit_log_cd > 0.0:
		_hit_log_cd = maxf(0.0, _hit_log_cd - delta)

	_process_spawn_points()
	_move_player_to_nearest_enemy(delta)
	_move_enemies(delta)
	_enemy_attack_player(delta)

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
	_load_player_cfg(battle_cfg)
	_load_monster_templates(battle_cfg)
	_load_spawn_points(battle_cfg)
	_load_drops_cfg(battle_cfg)
	_init_spawn_rt()

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
	monster_default_cfg.clear()
	var monster_list = battle_cfg.get("monsters", [])
	if monster_list is Array:
		for i in monster_list.size():
			var monster_any = monster_list[i]
			if monster_any is Dictionary:
				var monster: Dictionary = monster_any
				var monster_id: String = str(monster.get("id", ""))
				if not monster_id.is_empty():
					monsters_cfg[monster_id] = monster.duplicate(true)
				if i == 0:
					monster_default_cfg = monster.duplicate(true)

func _load_player_cfg(battle_cfg: Dictionary) -> void:
	var max_hp: int = int(player.get("max_hp", player.get("hp", 200)))
	var player_cfg_any = battle_cfg.get("player", {})
	if player_cfg_any is Dictionary:
		var player_cfg: Dictionary = player_cfg_any
		max_hp = int(player_cfg.get("hp", max_hp))
		player["radius"] = float(player_cfg.get("radius", player.get("radius", 18.0)))
		player["atk"] = int(player_cfg.get("atk", player.get("atk", 12)))
		player["def"] = int(player_cfg.get("def", player.get("def", 4)))
		player["speed"] = float(player_cfg.get("speed", player.get("speed", 140.0)))
		player["attack_interval"] = maxf(0.05, float(player_cfg.get("attack_interval", player.get("attack_interval", 0.25))))
		player["attack_range"] = float(player_cfg.get("attack_range", player.get("attack_range", 10.0)))
	player["max_hp"] = max_hp
	player["hp"] = max_hp

func _load_spawn_points(battle_cfg: Dictionary) -> void:
	spawn_points_cfg.clear()
	var spawn_points = battle_cfg.get("spawn_points", [])
	if spawn_points is Array:
		if not spawn_points.is_empty():
			var first_any = spawn_points[0]
			if first_any is Dictionary:
				var spawn_point: Dictionary = first_any
				spawn_points_cfg.append(spawn_point.duplicate(true))

func _load_drops_cfg(battle_cfg: Dictionary) -> void:
	drop_chance = 0.0
	drop_weights.clear()
	drop_items.clear()

	var drops_any = battle_cfg.get("drops", {})
	if not (drops_any is Dictionary):
		return

	var drops: Dictionary = drops_any
	drop_chance = clampf(float(drops.get("drop_chance", 0.0)), 0.0, 1.0)

	var weights_any = drops.get("rarity_weights", {})
	if weights_any is Dictionary:
		var weights: Dictionary = weights_any
		for rarity in ["white", "blue", "gold"]:
			drop_weights[rarity] = maxi(0, int(weights.get(rarity, 0)))

	var items_any = drops.get("items", {})
	if items_any is Dictionary:
		var items_dict: Dictionary = items_any
		for rarity in ["white", "blue", "gold"]:
			var list_any = items_dict.get(rarity, [])
			if list_any is Array:
				var names: Array[String] = []
				for item_any in list_any:
					names.append(str(item_any))
				drop_items[rarity] = names

func _init_spawn_rt() -> void:
	spawn_rt.clear()
	spawn_order.clear()

	for i in spawn_points_cfg.size():
		var spawn_point: Dictionary = spawn_points_cfg[i]
		var spawn_id: String = str(spawn_point.get("id", "sp_%d" % [i + 1]))
		if spawn_id.is_empty():
			spawn_id = "sp_%d" % [i + 1]
		while spawn_rt.has(spawn_id):
			spawn_id += "_dup"

		var runtime := {
			"pos": Vector2.ZERO,
			"respawn_s": maxf(0.05, float(spawn_point.get("respawn_s", 1.5))),
			"max_alive": maxi(0, int(spawn_point.get("max_alive", 1))),
			"monster_id": str(spawn_point.get("monster_id", DEFAULT_MONSTER_ID)),
			"alive_count": 0,
			"next_spawn_at": 0.0,
			"x_ratio": clampf(float(spawn_point.get("x_ratio", 0.5)), 0.0, 1.0),
			"y_ratio": clampf(float(spawn_point.get("y_ratio", 0.5)), 0.0, 1.0),
		}
		spawn_rt[spawn_id] = runtime
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
		if not spawn_rt.has(spawn_id):
			continue
		var runtime: Dictionary = spawn_rt[spawn_id]
		var x_ratio: float = clampf(float(runtime.get("x_ratio", 0.5)), 0.0, 1.0)
		var y_ratio: float = clampf(float(runtime.get("y_ratio", 0.5)), 0.0, 1.0)
		runtime["pos"] = arena_rect.position + Vector2(arena_rect.size.x * x_ratio, arena_rect.size.y * y_ratio)
		spawn_rt[spawn_id] = runtime

func _process_spawn_points() -> void:
	for spawn_id in spawn_order:
		if not spawn_rt.has(spawn_id):
			continue
		var runtime: Dictionary = spawn_rt[spawn_id]
		var alive_count: int = int(runtime.get("alive_count", 0))
		var max_alive: int = maxi(0, int(runtime.get("max_alive", 0)))
		var next_spawn_at: float = float(runtime.get("next_spawn_at", 0.0))
		var respawn_s: float = maxf(0.05, float(runtime.get("respawn_s", 1.5)))

		if alive_count < max_alive and battle_time >= next_spawn_at:
			_spawn_enemy_from_point(spawn_id, runtime)
			alive_count += 1
			runtime["alive_count"] = alive_count
			runtime["next_spawn_at"] = battle_time + respawn_s
			spawn_rt[spawn_id] = runtime

func _spawn_enemy_from_point(spawn_id: String, runtime: Dictionary) -> void:
	var monster_id: String = str(runtime.get("monster_id", DEFAULT_MONSTER_ID))
	var template: Dictionary = monster_default_cfg
	var template_any = monsters_cfg.get(monster_id, monster_default_cfg)
	if template_any is Dictionary:
		template = template_any

	var hp: int = int(template.get("hp", 30))
	var enemy := {
		"spawn_id": spawn_id,
		"monster_id": monster_id,
		"pos": runtime.get("pos", Vector2.ZERO),
		"speed": float(template.get("speed", 85.0)),
		"aggro_range": float(template.get("aggro_range", 220.0)),
		"attack_range": float(template.get("attack_range", 8.0)),
		"attack_cd": maxf(0.05, float(template.get("attack_interval", ENEMY_ATTACK_INTERVAL))),
		"atk": int(template.get("atk", DEFAULT_ENEMY_ATK)),
		"attack_timer": 0.0,
		"radius": float(template.get("radius", 14.0)),
		"hp": hp,
		"hpmax": hp,
		"def": int(template.get("def", 2)),
	}
	enemies.append(enemy)

func _move_player_to_nearest_enemy(delta: float) -> void:
	if enemies.is_empty():
		return

	var nearest_index := _find_nearest_enemy_index()
	if nearest_index < 0:
		return

	var nearest_enemy: Dictionary = enemies[nearest_index]
	var player_pos: Vector2 = player["pos"]
	var enemy_pos: Vector2 = nearest_enemy["pos"]
	var enemy_radius: float = float(nearest_enemy.get("radius", 14.0))
	var player_radius: float = float(player["radius"])
	var player_attack_range: float = float(player.get("attack_range", 10.0))
	var player_speed: float = float(player.get("speed", 140.0))

	var desired_distance: float = player_attack_range + player_radius + enemy_radius
	var distance_to_enemy: float = player_pos.distance_to(enemy_pos)
	if distance_to_enemy > desired_distance:
		var dir: Vector2 = (enemy_pos - player_pos).normalized()
		player_pos += dir * player_speed * delta
		player["pos"] = _clamp_pos_in_arena(player_pos, player_radius)

func _move_enemies(delta: float) -> void:
	if enemies.is_empty():
		return

	var player_pos: Vector2 = player["pos"]
	for i in enemies.size():
		var enemy: Dictionary = enemies[i]
		var enemy_pos: Vector2 = enemy["pos"]
		var enemy_speed: float = float(enemy.get("speed", 85.0))
		var enemy_aggro_range: float = float(enemy.get("aggro_range", 220.0))
		var enemy_radius: float = float(enemy.get("radius", 14.0))
		var distance_to_player: float = enemy_pos.distance_to(player_pos)

		if distance_to_player <= enemy_aggro_range:
			var dir: Vector2 = (player_pos - enemy_pos).normalized()
			enemy_pos += dir * enemy_speed * delta
			enemy_pos = _clamp_pos_in_arena(enemy_pos, enemy_radius)

		enemy["pos"] = enemy_pos
		enemies[i] = enemy

func _enemy_attack_player(delta: float) -> void:
	if enemies.is_empty():
		return

	var player_pos: Vector2 = player["pos"]
	var player_radius: float = float(player["radius"])
	var player_def: int = int(player["def"])
	var total_damage := 0

	for i in enemies.size():
		var enemy: Dictionary = enemies[i]
		var enemy_pos: Vector2 = enemy["pos"]
		var enemy_radius: float = float(enemy.get("radius", 14.0))
		var enemy_aggro_range: float = float(enemy.get("aggro_range", 220.0))
		var enemy_attack_range: float = float(enemy.get("attack_range", 8.0))
		var enemy_attack_cd: float = maxf(0.05, float(enemy.get("attack_cd", ENEMY_ATTACK_INTERVAL)))
		var enemy_atk: int = int(enemy.get("atk", DEFAULT_ENEMY_ATK))
		var attack_timer: float = float(enemy.get("attack_timer", 0.0))
		attack_timer += delta

		var distance_to_player: float = enemy_pos.distance_to(player_pos)
		var attack_distance: float = enemy_attack_range + player_radius + enemy_radius
		if distance_to_player <= enemy_aggro_range and distance_to_player <= attack_distance and attack_timer >= enemy_attack_cd:
			var damage: int = enemy_atk - player_def
			if damage < 1:
				damage = 1
			total_damage += damage
			attack_timer = 0.0

		enemy["attack_timer"] = attack_timer
		enemies[i] = enemy

	if total_damage <= 0:
		return

	var current_hp: int = int(player["hp"]) - total_damage
	if current_hp < 0:
		current_hp = 0
	player["hp"] = current_hp

	if _hit_log_cd <= 0.0:
		EventBus.add_log("受击 -%d（HP %d/%d）" % [total_damage, int(player["hp"]), int(player["max_hp"])])
		_hit_log_cd = HIT_LOG_INTERVAL

	if current_hp <= 0:
		_on_player_dead()

func _on_player_dead() -> void:
	EventBus.add_log("你倒下了……重开刷怪点")
	player["hp"] = int(player["max_hp"])
	kills = 0
	enemies.clear()
	_attack_timer = 0.0
	_hit_log_cd = 0.0

	for spawn_id in spawn_order:
		if not spawn_rt.has(spawn_id):
			continue
		var runtime: Dictionary = spawn_rt[spawn_id]
		runtime["alive_count"] = 0
		runtime["next_spawn_at"] = battle_time
		spawn_rt[spawn_id] = runtime

func _clamp_pos_in_arena(pos: Vector2, radius: float) -> Vector2:
	var min_x: float = arena_rect.position.x + radius
	var max_x: float = arena_rect.position.x + arena_rect.size.x - radius
	var min_y: float = arena_rect.position.y + radius
	var max_y: float = arena_rect.position.y + arena_rect.size.y - radius

	if max_x < min_x:
		max_x = min_x
	if max_y < min_y:
		max_y = min_y

	return Vector2(
		clampf(pos.x, min_x, max_x),
		clampf(pos.y, min_y, max_y)
	)

func _auto_attack() -> void:
	if enemies.is_empty():
		return

	var target_index := _find_nearest_enemy_index()
	if target_index < 0:
		return

	var enemy: Dictionary = enemies[target_index]
	var player_pos: Vector2 = player["pos"]
	var enemy_pos: Vector2 = enemy["pos"]
	var player_attack_range: float = float(player.get("attack_range", 10.0))
	var player_radius: float = float(player["radius"])
	var enemy_radius: float = float(enemy.get("radius", 14.0))
	var attack_distance: float = player_attack_range + player_radius + enemy_radius
	if player_pos.distance_to(enemy_pos) > attack_distance:
		return

	var damage: int = int(player["atk"]) - int(enemy["def"])
	if damage < 1:
		damage = 1
	enemy["hp"] = int(enemy["hp"]) - damage

	if int(enemy["hp"]) <= 0:
		var spawn_id: String = str(enemy.get("spawn_id", ""))
		enemies.remove_at(target_index)
		_decrease_spawn_alive(spawn_id)
		kills += 1
		_try_log_drop()
		if kills % 5 == 0:
			EventBus.add_log("击杀累计：%d（场上%d）" % [kills, enemies.size()])
	else:
		enemies[target_index] = enemy

func _try_log_drop() -> void:
	if drop_chance <= 0.0:
		return
	if randf() >= drop_chance:
		return

	var rarity := _roll_drop_rarity()
	if rarity.is_empty():
		return

	var items_any = drop_items.get(rarity, [])
	if not (items_any is Array):
		return
	var items: Array = items_any
	if items.is_empty():
		return

	var item_name := str(items[randi() % items.size()])
	var tag := "[白]"
	match rarity:
		"blue":
			tag = "[蓝]"
		"gold":
			tag = "[金]"
		_:
			tag = "[白]"
	EventBus.add_log("%s 掉落：%s" % [tag, item_name])

func _roll_drop_rarity() -> String:
	var white_w: int = int(drop_weights.get("white", 0))
	var blue_w: int = int(drop_weights.get("blue", 0))
	var gold_w: int = int(drop_weights.get("gold", 0))
	var total: int = max(0, white_w) + max(0, blue_w) + max(0, gold_w)
	if total <= 0:
		return ""

	var roll: int = randi() % total
	if roll < white_w:
		return "white"
	if roll < white_w + blue_w:
		return "blue"
	return "gold"

func _decrease_spawn_alive(spawn_id: String) -> void:
	if spawn_id.is_empty() or not spawn_rt.has(spawn_id):
		return
	var runtime: Dictionary = spawn_rt[spawn_id]
	var alive_count: int = maxi(0, int(runtime.get("alive_count", 0)) - 1)
	var respawn_s: float = maxf(0.05, float(runtime.get("respawn_s", 1.5)))
	var gate_time: float = battle_time + respawn_s
	var next_spawn_at: float = float(runtime.get("next_spawn_at", 0.0))
	runtime["alive_count"] = alive_count
	if next_spawn_at < gate_time:
		runtime["next_spawn_at"] = gate_time
	spawn_rt[spawn_id] = runtime

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

	var hp_now: int = int(player.get("hp", 0))
	var hp_max: int = int(player.get("max_hp", hp_now))
	var hud_line_1 := "%s｜HP %d/%d｜击杀 %d｜场上 %d" % [stage_name, hp_now, hp_max, kills, enemies.size()]
	var hud_line_2 := _build_spawn_status_line()
	var hud_pos_1 := Vector2(12, 30)
	var hud_pos_2 := Vector2(12, 60)
	var hud_bg_width: float = maxf(
		_measure_text_width(hud_line_1, HUD_LABEL_SIZE),
		_measure_text_width(hud_line_2, HUD_SUB_LABEL_SIZE)
	) + 20.0
	hud_bg_width = minf(size.x - 16.0, hud_bg_width)
	var hud_bg_rect := Rect2(hud_pos_1 + Vector2(-8, -28), Vector2(hud_bg_width, 72))
	draw_rect(hud_bg_rect, Color(0, 0, 0, 0.6), true)

	_draw_label(
		hud_pos_1,
		hud_line_1,
		Color.WHITE,
		HUD_LABEL_SIZE,
		true
	)
	_draw_label(
		hud_pos_2,
		hud_line_2,
		Color.WHITE,
		HUD_SUB_LABEL_SIZE,
		true
	)

func _draw_spawn_points() -> void:
	for i in spawn_order.size():
		var spawn_id: String = spawn_order[i]
		if not spawn_rt.has(spawn_id):
			continue
		var runtime: Dictionary = spawn_rt[spawn_id]
		var pos: Vector2 = runtime.get("pos", Vector2.ZERO)
		draw_line(pos + Vector2(-4, 0), pos + Vector2(4, 0), Color.WHITE, 1.0)
		draw_line(pos + Vector2(0, -4), pos + Vector2(0, 4), Color.WHITE, 1.0)
		draw_circle(pos, 2.0, Color.WHITE)
		_draw_label(
			pos + Vector2(8, -6),
			"刷 %s" % spawn_id,
			Color(1.0, 1.0, 1.0, 0.95),
			SPAWN_LABEL_SIZE,
			true
		)

func _build_spawn_status_line() -> String:
	var alive_count := 0
	var max_alive := 0
	if spawn_rt.has("sp_1"):
		var runtime_sp1: Dictionary = spawn_rt["sp_1"]
		alive_count = int(runtime_sp1.get("alive_count", 0))
		max_alive = int(runtime_sp1.get("max_alive", 0))
	elif not spawn_order.is_empty():
		var first_id: String = spawn_order[0]
		if spawn_rt.has(first_id):
			var runtime_first: Dictionary = spawn_rt[first_id]
			alive_count = int(runtime_first.get("alive_count", 0))
			max_alive = int(runtime_first.get("max_alive", 0))

	var aggro_on := "未进入"
	var player_pos: Vector2 = player["pos"]
	for enemy in enemies:
		var enemy_dict: Dictionary = enemy
		var enemy_pos: Vector2 = enemy_dict["pos"]
		var enemy_aggro_range: float = float(enemy_dict.get("aggro_range", 220.0))
		if enemy_pos.distance_to(player_pos) <= enemy_aggro_range:
			aggro_on = "已进入"
			break

	return "刷怪点 sp_1：%d/%d｜警戒：%s" % [alive_count, max_alive, aggro_on]

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
