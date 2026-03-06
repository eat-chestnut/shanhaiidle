extends Node

const DEFAULT_ARENA_SIZE := Vector2(720.0, 760.0)
const HIT_LOG_INTERVAL := 1.0
const LOOT_TTL := 20.0
const LOOT_PICKUP_RADIUS := 28.0
const DEFAULT_MONSTER_ID := "mob_a"
const DEFAULT_ENEMY_ATK := 6

var stage_name: String = "未命名关卡"
var arena_size: Vector2 = DEFAULT_ARENA_SIZE
var manual_vec: Vector2 = Vector2.ZERO

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
	"crit": 0,
}

var spawn_rt := {
	"id": "sp_1",
	"pos": Vector2.ZERO,
	"x_ratio": 0.5,
	"y_ratio": 0.22,
	"respawn_s": 1.6,
	"max_alive": 10,
	"monster_id": DEFAULT_MONSTER_ID,
	"spawn_radius": 220.0,
	"alive_count": 0,
	"next_spawn_at": 0.0,
}

var monsters_cfg: Dictionary = {}
var default_monster_cfg: Dictionary = {}
var equip_templates_by_id: Dictionary = {}

var drop_chance := 0.0
var drop_weights: Dictionary = {}
var drop_items: Dictionary = {}
var equip_drop_chance := 0.0
var equip_drop_weights: Dictionary = {}
var equip_drop_by_rarity: Dictionary = {}

var enemies: Array[Dictionary] = []
var loots: Array[Dictionary] = []
var kills := 0

var battle_time := 0.0
var attack_timer := 0.0
var hit_log_cd := 0.0
var auto_seek_moving := false
var manual_seek_off_fired := false
var player_pos_inited := false

func _ready() -> void:
	randomize()
	set_process(true)
	_load_battle_cfg()
	_apply_spawn_position()
	_reset_player_pos_if_needed()
	_sync_player_combat_stats(true)

func set_viewport_arena_size(px_size: Vector2) -> void:
	if px_size.x <= 1.0 or px_size.y <= 1.0:
		return
	if arena_size.is_equal_approx(px_size):
		return
	arena_size = px_size
	_apply_spawn_position()
	_reset_player_pos_if_needed()
	player["pos"] = _clamp_pos_in_arena(player["pos"], float(player["radius"]))
	for i in range(enemies.size()):
		var enemy: Dictionary = enemies[i]
		var enemy_radius: float = float(enemy.get("radius", 14.0))
		enemy["pos"] = _clamp_pos_in_arena(enemy.get("pos", Vector2.ZERO), enemy_radius)
		enemy["home_pos"] = _clamp_pos_in_arena(enemy.get("home_pos", spawn_rt["pos"]), enemy_radius)
		enemies[i] = enemy

func set_manual_input(vec: Vector2) -> void:
	if vec.length() > 1.0:
		vec = vec.normalized()
	manual_vec = vec

func get_state() -> Dictionary:
	var enemies_view: Array[Dictionary] = []
	for enemy_any in enemies:
		if not (enemy_any is Dictionary):
			continue
		var enemy: Dictionary = enemy_any
		enemies_view.append({
			"pos": enemy.get("pos", Vector2.ZERO),
			"hp": int(enemy.get("hp", 0)),
			"hpmax": int(enemy.get("hpmax", 0)),
			"radius": float(enemy.get("radius", 14.0)),
			"state": str(enemy.get("state", "idle")),
			"aggro_range": float(enemy.get("aggro_range", 220.0)),
		})

	var loot_view: Array[Dictionary] = []
	for loot_any in loots:
		if not (loot_any is Dictionary):
			continue
		var loot: Dictionary = loot_any
		loot_view.append({
			"pos": loot.get("pos", Vector2.ZERO),
			"type": str(loot.get("type", "item")),
			"label": str(loot.get("label", "")),
			"rarity": str(loot.get("rarity", "white")),
			"ttl": float(loot.get("ttl", 0.0)),
		})

	var aggro_on := false
	var player_pos: Vector2 = player.get("pos", Vector2.ZERO)
	for enemy_any in enemies:
		if not (enemy_any is Dictionary):
			continue
		var enemy: Dictionary = enemy_any
		var enemy_pos: Vector2 = enemy.get("pos", Vector2.ZERO)
		var enemy_aggro_range: float = float(enemy.get("aggro_range", 220.0))
		if enemy_pos.distance_to(player_pos) <= enemy_aggro_range:
			aggro_on = true
			break

	return {
		"stage_name": stage_name,
		"arena_size": arena_size,
		"spawn": {
			"pos": spawn_rt.get("pos", Vector2.ZERO),
			"spawn_radius": float(spawn_rt.get("spawn_radius", 0.0)),
			"alive": int(spawn_rt.get("alive_count", 0)),
			"max": int(spawn_rt.get("max_alive", 0)),
			"aggro_on": aggro_on,
		},
		"player": {
			"pos": player.get("pos", Vector2.ZERO),
			"hp": int(player.get("hp", 0)),
			"hpmax": int(player.get("max_hp", 0)),
			"radius": float(player.get("radius", 18.0)),
			"atk_range": float(player.get("attack_range", 10.0)),
		},
		"kills": kills,
		"enemies": enemies_view,
		"loot": loot_view,
		"auto_seek": GameSettings.auto_seek_enabled,
	}

func _process(delta: float) -> void:
	battle_time += delta
	_sync_player_combat_stats()
	player["pos"] = _clamp_pos_in_arena(player["pos"], float(player["radius"]))
	if hit_log_cd > 0.0:
		hit_log_cd = maxf(0.0, hit_log_cd - delta)

	_process_spawn()
	_process_player_move(delta)
	_move_enemies(delta)
	_enemy_attack_player(delta)
	_update_loot(delta)

	attack_timer += delta
	var attack_interval: float = maxf(0.05, float(player.get("attack_interval", 0.25)))
	while attack_timer >= attack_interval:
		attack_timer -= attack_interval
		_player_auto_attack()

func _load_battle_cfg() -> void:
	var battle_cfg: Dictionary = {}
	var full_cfg: Dictionary = ConfigService.get_cfg()
	var battle_any = full_cfg.get("battle", {})
	if battle_any is Dictionary:
		battle_cfg = battle_any

	stage_name = str(battle_cfg.get("stage_name", stage_name))
	_load_player_cfg(battle_cfg)
	_load_monsters_cfg(battle_cfg)
	_load_spawn_cfg(battle_cfg)
	_load_drop_cfg(battle_cfg)
	_load_equip_templates()

func _load_player_cfg(battle_cfg: Dictionary) -> void:
	var player_cfg_any = battle_cfg.get("player", {})
	if not (player_cfg_any is Dictionary):
		return
	var player_cfg: Dictionary = player_cfg_any
	player["radius"] = float(player_cfg.get("radius", player.get("radius", 18.0)))
	player["speed"] = float(player_cfg.get("speed", player.get("speed", 140.0)))
	player["attack_interval"] = maxf(0.05, float(player_cfg.get("attack_interval", player.get("attack_interval", 0.25))))
	player["attack_range"] = float(player_cfg.get("attack_range", player.get("attack_range", 10.0)))

func _load_monsters_cfg(battle_cfg: Dictionary) -> void:
	monsters_cfg.clear()
	default_monster_cfg.clear()
	var monsters_any = battle_cfg.get("monsters", [])
	if not (monsters_any is Array):
		return
	for i in range(monsters_any.size()):
		var mon_any = monsters_any[i]
		if not (mon_any is Dictionary):
			continue
		var mon: Dictionary = mon_any
		var mon_id := str(mon.get("id", ""))
		if not mon_id.is_empty():
			monsters_cfg[mon_id] = mon.duplicate(true)
		if i == 0:
			default_monster_cfg = mon.duplicate(true)

func _load_spawn_cfg(battle_cfg: Dictionary) -> void:
	var spawn_points_any = battle_cfg.get("spawn_points", [])
	if not (spawn_points_any is Array):
		return
	if spawn_points_any.is_empty():
		return
	var sp_any = spawn_points_any[0]
	if not (sp_any is Dictionary):
		return
	var sp: Dictionary = sp_any
	spawn_rt["id"] = str(sp.get("id", "sp_1"))
	spawn_rt["x_ratio"] = clampf(float(sp.get("x_ratio", 0.5)), 0.0, 1.0)
	spawn_rt["y_ratio"] = clampf(float(sp.get("y_ratio", 0.5)), 0.0, 1.0)
	spawn_rt["respawn_s"] = maxf(0.05, float(sp.get("respawn_s", 1.6)))
	spawn_rt["max_alive"] = maxi(0, int(sp.get("max_alive", 1)))
	spawn_rt["monster_id"] = str(sp.get("monster_id", DEFAULT_MONSTER_ID))
	spawn_rt["spawn_radius"] = maxf(0.0, float(sp.get("spawn_radius", 0.0)))
	spawn_rt["alive_count"] = 0
	spawn_rt["next_spawn_at"] = 0.0

func _load_drop_cfg(battle_cfg: Dictionary) -> void:
	drop_chance = 0.0
	drop_weights.clear()
	drop_items.clear()
	equip_drop_chance = 0.0
	equip_drop_weights.clear()
	equip_drop_by_rarity.clear()

	var drops_any = battle_cfg.get("drops", {})
	if not (drops_any is Dictionary):
		return
	var drops: Dictionary = drops_any

	drop_chance = clampf(float(drops.get("drop_chance", 0.0)), 0.0, 1.0)
	var rarity_weights_any = drops.get("rarity_weights", {})
	if rarity_weights_any is Dictionary:
		var rarity_weights: Dictionary = rarity_weights_any
		for rarity in ["white", "blue", "gold"]:
			drop_weights[rarity] = maxi(0, int(rarity_weights.get(rarity, 0)))

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

	equip_drop_chance = clampf(float(drops.get("equip_chance", 0.0)), 0.0, 1.0)
	var equip_weights_any = drops.get("equip_weights", {})
	if equip_weights_any is Dictionary:
		var equip_weights: Dictionary = equip_weights_any
		for rarity in ["white", "blue", "gold"]:
			equip_drop_weights[rarity] = maxi(0, int(equip_weights.get(rarity, 0)))

	var equip_pool_any = drops.get("equip_by_rarity", {})
	if equip_pool_any is Dictionary:
		var equip_pool: Dictionary = equip_pool_any
		for rarity in ["white", "blue", "gold"]:
			var list_any = equip_pool.get(rarity, [])
			if list_any is Array:
				var ids: Array[String] = []
				for id_any in list_any:
					ids.append(str(id_any))
				equip_drop_by_rarity[rarity] = ids

func _load_equip_templates() -> void:
	equip_templates_by_id.clear()
	var full_cfg: Dictionary = ConfigService.get_cfg()
	var equip_db_any = full_cfg.get("equip_db", {})
	if not (equip_db_any is Dictionary):
		return
	var equip_db: Dictionary = equip_db_any
	var templates_any = equip_db.get("equip_templates", [])
	if not (templates_any is Array):
		return
	for tpl_any in templates_any:
		if not (tpl_any is Dictionary):
			continue
		var tpl: Dictionary = tpl_any
		var tpl_id := str(tpl.get("id", ""))
		if tpl_id.is_empty():
			continue
		equip_templates_by_id[tpl_id] = tpl.duplicate(true)

func _sync_player_combat_stats(force_full: bool = false) -> void:
	var stats: Dictionary = EquipmentModel.get_total_stats()
	var new_hp_max := maxi(1, int(stats.get("HP", int(player.get("max_hp", 200)))))
	var old_hp_max := maxi(1, int(player.get("max_hp", new_hp_max)))
	var current_hp := int(player.get("hp", old_hp_max))

	if force_full:
		current_hp = new_hp_max
	elif old_hp_max != new_hp_max:
		var ratio := float(current_hp) / float(old_hp_max)
		current_hp = int(round(clampf(ratio, 0.0, 1.0) * float(new_hp_max)))

	player["max_hp"] = new_hp_max
	player["hp"] = clampi(current_hp, 0, new_hp_max)
	player["atk"] = int(stats.get("ATK", int(player.get("atk", 12))))
	player["def"] = int(stats.get("DEF", int(player.get("def", 4))))
	player["crit"] = int(stats.get("CRIT", 0))

func _apply_spawn_position() -> void:
	var x_ratio: float = clampf(float(spawn_rt.get("x_ratio", 0.5)), 0.0, 1.0)
	var y_ratio: float = clampf(float(spawn_rt.get("y_ratio", 0.5)), 0.0, 1.0)
	spawn_rt["pos"] = Vector2(arena_size.x * x_ratio, arena_size.y * y_ratio)

func _reset_player_pos_if_needed() -> void:
	if player_pos_inited:
		return
	player["pos"] = arena_size * 0.5
	player_pos_inited = true

func _process_spawn() -> void:
	var alive_count: int = int(spawn_rt.get("alive_count", 0))
	var max_alive: int = maxi(0, int(spawn_rt.get("max_alive", 0)))
	var next_spawn_at: float = float(spawn_rt.get("next_spawn_at", 0.0))
	var respawn_s: float = maxf(0.05, float(spawn_rt.get("respawn_s", 1.6)))

	if alive_count < max_alive and battle_time >= next_spawn_at:
		_spawn_enemy()
		alive_count += 1
		spawn_rt["alive_count"] = alive_count
		spawn_rt["next_spawn_at"] = battle_time + respawn_s

func _spawn_enemy() -> void:
	var monster_id: String = str(spawn_rt.get("monster_id", DEFAULT_MONSTER_ID))
	var template: Dictionary = default_monster_cfg
	var template_any = monsters_cfg.get(monster_id, default_monster_cfg)
	if template_any is Dictionary:
		template = template_any

	var hp: int = int(template.get("hp", 30))
	var enemy_radius: float = float(template.get("radius", 14.0))
	var home_pos: Vector2 = spawn_rt.get("pos", Vector2.ZERO)
	var spawn_radius: float = maxf(0.0, float(spawn_rt.get("spawn_radius", 0.0)))
	var spawn_pos: Vector2 = home_pos + _random_point_in_circle(spawn_radius)
	spawn_pos = _clamp_pos_in_arena(spawn_pos, enemy_radius)

	enemies.append({
		"spawn_id": str(spawn_rt.get("id", "sp_1")),
		"monster_id": monster_id,
		"pos": spawn_pos,
		"home_pos": home_pos,
		"state": "idle",
		"speed": float(template.get("speed", 85.0)),
		"aggro_range": float(template.get("aggro_range", 220.0)),
		"attack_range": float(template.get("attack_range", 8.0)),
		"attack_cd": maxf(0.05, float(template.get("attack_interval", 0.9))),
		"atk": int(template.get("atk", DEFAULT_ENEMY_ATK)),
		"attack_timer": 0.0,
		"radius": enemy_radius,
		"hp": hp,
		"hpmax": hp,
		"def": int(template.get("def", 2)),
	})

func _process_player_move(delta: float) -> void:
	var input_vec := manual_vec
	manual_vec = Vector2.ZERO
	if input_vec.length() > 0.001:
		var player_pos: Vector2 = player.get("pos", Vector2.ZERO)
		var player_speed: float = float(player.get("speed", 140.0))
		var player_radius: float = float(player.get("radius", 18.0))
		player_pos += input_vec.normalized() * player_speed * delta
		player["pos"] = _clamp_pos_in_arena(player_pos, player_radius)
		auto_seek_moving = false
		if GameSettings.auto_seek_enabled and not manual_seek_off_fired:
			GameSettings.set_auto_seek(false, "手动移动自动关闭")
			manual_seek_off_fired = true
		return

	manual_seek_off_fired = false
	if not GameSettings.auto_seek_enabled:
		auto_seek_moving = false
		return
	_process_auto_seek_move(delta)

func _process_auto_seek_move(delta: float) -> void:
	if enemies.is_empty():
		auto_seek_moving = false
		return
	var nearest_index := _find_nearest_enemy_index()
	if nearest_index < 0:
		auto_seek_moving = false
		return

	var enemy: Dictionary = enemies[nearest_index]
	var enemy_pos: Vector2 = enemy.get("pos", Vector2.ZERO)
	var enemy_radius: float = float(enemy.get("radius", 14.0))
	var player_pos: Vector2 = player.get("pos", Vector2.ZERO)
	var player_radius: float = float(player.get("radius", 18.0))
	var player_attack_range: float = float(player.get("attack_range", 10.0))
	var player_speed: float = float(player.get("speed", 140.0))

	var attack_reach: float = player_attack_range + player_radius + enemy_radius
	var stop_dist: float = maxf(0.0, attack_reach - 8.0)
	var start_dist: float = attack_reach + 8.0
	var distance_to_enemy: float = player_pos.distance_to(enemy_pos)

	if distance_to_enemy > start_dist:
		auto_seek_moving = true
	elif distance_to_enemy < stop_dist:
		auto_seek_moving = false

	if not auto_seek_moving:
		return
	var move_vec: Vector2 = enemy_pos - player_pos
	var move_len: float = move_vec.length()
	if move_len <= 0.001:
		return
	player_pos += (move_vec / move_len) * player_speed * delta
	player["pos"] = _clamp_pos_in_arena(player_pos, player_radius)

func _move_enemies(delta: float) -> void:
	if enemies.is_empty():
		return
	var player_pos: Vector2 = player.get("pos", Vector2.ZERO)
	for i in range(enemies.size()):
		var enemy: Dictionary = enemies[i]
		var enemy_pos: Vector2 = enemy.get("pos", Vector2.ZERO)
		var home_pos: Vector2 = enemy.get("home_pos", enemy_pos)
		var enemy_speed: float = float(enemy.get("speed", 85.0))
		var enemy_radius: float = float(enemy.get("radius", 14.0))
		var enemy_aggro_range: float = float(enemy.get("aggro_range", 220.0))
		var state: String = str(enemy.get("state", "idle"))
		var prev_state: String = state
		var dist_to_player: float = enemy_pos.distance_to(player_pos)

		match state:
			"idle":
				if dist_to_player <= enemy_aggro_range:
					state = "chase"
			"chase":
				if dist_to_player > enemy_aggro_range * 1.2:
					state = "return"
			"return":
				pass
			_:
				state = "idle"

		if state == "chase":
			var chase_vec: Vector2 = player_pos - enemy_pos
			var chase_len: float = chase_vec.length()
			if chase_len > 0.001:
				enemy_pos += (chase_vec / chase_len) * enemy_speed * delta
				enemy_pos = _clamp_pos_in_arena(enemy_pos, enemy_radius)
		elif state == "return":
			var back_vec: Vector2 = home_pos - enemy_pos
			var back_len: float = back_vec.length()
			if back_len <= 6.0:
				enemy_pos = home_pos
				state = "idle"
			else:
				enemy_pos += (back_vec / back_len) * enemy_speed * delta
				enemy_pos = _clamp_pos_in_arena(enemy_pos, enemy_radius)
				if enemy_pos.distance_to(home_pos) <= 6.0:
					enemy_pos = home_pos
					state = "idle"

		if prev_state == "chase" and state != "chase":
			enemy["attack_timer"] = 0.0

		enemy["state"] = state
		enemy["pos"] = enemy_pos
		enemy["home_pos"] = home_pos
		enemies[i] = enemy

func _enemy_attack_player(delta: float) -> void:
	if enemies.is_empty():
		return
	var player_pos: Vector2 = player.get("pos", Vector2.ZERO)
	var player_radius: float = float(player.get("radius", 18.0))
	var player_def: int = int(player.get("def", 4))
	var total_damage := 0

	for i in range(enemies.size()):
		var enemy: Dictionary = enemies[i]
		var state: String = str(enemy.get("state", "idle"))
		if state != "chase":
			enemy["attack_timer"] = 0.0
			enemies[i] = enemy
			continue

		var enemy_pos: Vector2 = enemy.get("pos", Vector2.ZERO)
		var enemy_radius: float = float(enemy.get("radius", 14.0))
		var enemy_attack_range: float = float(enemy.get("attack_range", 8.0))
		var enemy_attack_cd: float = maxf(0.05, float(enemy.get("attack_cd", 0.9)))
		var enemy_atk: int = int(enemy.get("atk", DEFAULT_ENEMY_ATK))
		var attack_timer: float = float(enemy.get("attack_timer", 0.0)) + delta

		var distance_to_player: float = enemy_pos.distance_to(player_pos)
		var reach: float = enemy_attack_range + player_radius + enemy_radius
		if distance_to_player <= reach and attack_timer >= enemy_attack_cd:
			var dmg := maxi(1, enemy_atk - player_def)
			total_damage += dmg
			attack_timer = 0.0

		enemy["attack_timer"] = attack_timer
		enemies[i] = enemy

	if total_damage <= 0:
		return

	var hp_now := maxi(0, int(player.get("hp", 0)) - total_damage)
	player["hp"] = hp_now
	if hit_log_cd <= 0.0:
		EventBus.add_log("受击 -%d（HP %d/%d）" % [total_damage, hp_now, int(player.get("max_hp", hp_now))])
		hit_log_cd = HIT_LOG_INTERVAL

	if hp_now <= 0:
		_on_player_dead()

func _player_auto_attack() -> void:
	if enemies.is_empty():
		return
	var player_pos: Vector2 = player.get("pos", Vector2.ZERO)
	var player_attack_range: float = float(player.get("attack_range", 10.0))
	var player_radius: float = float(player.get("radius", 18.0))
	var target_index := -1
	var best_dist_sq := INF

	for i in range(enemies.size()):
		var enemy: Dictionary = enemies[i]
		var enemy_pos: Vector2 = enemy.get("pos", Vector2.ZERO)
		var enemy_radius: float = float(enemy.get("radius", 14.0))
		var reach: float = player_attack_range + player_radius + enemy_radius
		var distance_to_enemy: float = player_pos.distance_to(enemy_pos)
		if distance_to_enemy > reach:
			continue
		var dist_sq := player_pos.distance_squared_to(enemy_pos)
		if dist_sq < best_dist_sq:
			best_dist_sq = dist_sq
			target_index = i

	if target_index < 0:
		return
	var enemy_target: Dictionary = enemies[target_index]
	var damage := maxi(1, int(player.get("atk", 12)) - int(enemy_target.get("def", 2)))
	enemy_target["hp"] = int(enemy_target.get("hp", 0)) - damage

	if int(enemy_target.get("hp", 0)) <= 0:
		var death_pos: Vector2 = enemy_target.get("pos", player_pos)
		var spawn_id: String = str(enemy_target.get("spawn_id", "sp_1"))
		enemies.remove_at(target_index)
		_decrease_spawn_alive(spawn_id)
		kills += 1
		_try_spawn_drop(death_pos)
		if kills % 5 == 0:
			EventBus.add_log("击杀累计：%d（场上%d）" % [kills, enemies.size()])
	else:
		enemies[target_index] = enemy_target

func _try_spawn_drop(death_pos: Vector2) -> void:
	if _try_spawn_equip_drop(death_pos):
		return
	_try_spawn_item_drop(death_pos)

func _try_spawn_equip_drop(death_pos: Vector2) -> bool:
	if equip_drop_chance <= 0.0:
		return false
	if randf() >= equip_drop_chance:
		return false
	var rarity := _roll_weighted_rarity(equip_drop_weights)
	if rarity.is_empty():
		return false
	var pool_any = equip_drop_by_rarity.get(rarity, [])
	if not (pool_any is Array):
		return false
	var pool: Array = pool_any
	if pool.is_empty():
		return false

	var template_id := str(pool[randi() % pool.size()])
	if template_id.is_empty():
		return false
	var equip_name := _resolve_equip_name(template_id)
	var tag := _rarity_tag(rarity)
	EventBus.add_log("%s 装备掉落：%s" % [tag, equip_name])
	loots.append({
		"type": "equip",
		"pos": death_pos,
		"template_id": template_id,
		"label": equip_name,
		"rarity": rarity,
		"ttl": LOOT_TTL,
	})
	return true

func _try_spawn_item_drop(death_pos: Vector2) -> void:
	if drop_chance <= 0.0:
		return
	if randf() >= drop_chance:
		return
	var rarity := _roll_weighted_rarity(drop_weights)
	if rarity.is_empty():
		return
	var items_any = drop_items.get(rarity, [])
	if not (items_any is Array):
		return
	var items: Array = items_any
	if items.is_empty():
		return
	var item_name := str(items[randi() % items.size()])
	if item_name.is_empty():
		return
	var tag := _rarity_tag(rarity)
	EventBus.add_log("%s 掉落：%s" % [tag, item_name])
	loots.append({
		"type": "item",
		"pos": death_pos,
		"item_id": item_name,
		"label": item_name,
		"rarity": rarity,
		"ttl": LOOT_TTL,
	})

func _update_loot(delta: float) -> void:
	if loots.is_empty():
		return
	var player_pos: Vector2 = player.get("pos", Vector2.ZERO)
	for i in range(loots.size() - 1, -1, -1):
		var loot: Dictionary = loots[i]
		var loot_pos: Vector2 = loot.get("pos", Vector2.ZERO)
		if player_pos.distance_to(loot_pos) <= LOOT_PICKUP_RADIUS:
			var loot_type := str(loot.get("type", "item"))
			if loot_type == "equip":
				var template_id := str(loot.get("template_id", ""))
				var equip_name := str(loot.get("label", _resolve_equip_name(template_id)))
				if not template_id.is_empty():
					EquipmentModel.add_equip(template_id)
					EventBus.add_log("拾取装备：%s" % equip_name)
			else:
				var item_id := str(loot.get("item_id", ""))
				if not item_id.is_empty():
					InventoryModel.add_item(item_id, 1)
					EventBus.add_log("拾取：%s" % item_id)
			loots.remove_at(i)
			continue

		var ttl := float(loot.get("ttl", LOOT_TTL)) - delta
		if ttl <= 0.0:
			loots.remove_at(i)
			continue
		loot["ttl"] = ttl
		loots[i] = loot

func _on_player_dead() -> void:
	EventBus.add_log("你倒下了……重开刷怪点")
	_sync_player_combat_stats(true)
	player["hp"] = int(player.get("max_hp", 200))
	kills = 0
	enemies.clear()
	loots.clear()
	attack_timer = 0.0
	hit_log_cd = 0.0
	auto_seek_moving = false
	manual_seek_off_fired = false
	spawn_rt["alive_count"] = 0
	spawn_rt["next_spawn_at"] = battle_time

func _decrease_spawn_alive(spawn_id: String) -> void:
	if spawn_id != str(spawn_rt.get("id", "sp_1")):
		return
	var alive_count := maxi(0, int(spawn_rt.get("alive_count", 0)) - 1)
	var respawn_s: float = maxf(0.05, float(spawn_rt.get("respawn_s", 1.6)))
	var gate_time := battle_time + respawn_s
	var next_spawn_at: float = float(spawn_rt.get("next_spawn_at", 0.0))
	spawn_rt["alive_count"] = alive_count
	if next_spawn_at < gate_time:
		spawn_rt["next_spawn_at"] = gate_time

func _find_nearest_enemy_index() -> int:
	var player_pos: Vector2 = player.get("pos", Vector2.ZERO)
	var best_idx := -1
	var best_dist_sq := INF
	for i in range(enemies.size()):
		var enemy: Dictionary = enemies[i]
		var enemy_pos: Vector2 = enemy.get("pos", Vector2.ZERO)
		var dist_sq := player_pos.distance_squared_to(enemy_pos)
		if dist_sq < best_dist_sq:
			best_dist_sq = dist_sq
			best_idx = i
	return best_idx

func _resolve_equip_name(template_id: String) -> String:
	var tpl_any = equip_templates_by_id.get(template_id, {})
	if tpl_any is Dictionary:
		var tpl: Dictionary = tpl_any
		return str(tpl.get("name", template_id))
	return template_id

func _roll_weighted_rarity(weights: Dictionary) -> String:
	var white_w := maxi(0, int(weights.get("white", 0)))
	var blue_w := maxi(0, int(weights.get("blue", 0)))
	var gold_w := maxi(0, int(weights.get("gold", 0)))
	var total := white_w + blue_w + gold_w
	if total <= 0:
		return ""
	var roll := randi() % total
	if roll < white_w:
		return "white"
	if roll < white_w + blue_w:
		return "blue"
	return "gold"

func _rarity_tag(rarity: String) -> String:
	match rarity:
		"blue":
			return "[蓝]"
		"gold":
			return "[金]"
		_:
			return "[白]"

func _random_point_in_circle(radius: float) -> Vector2:
	if radius <= 0.0:
		return Vector2.ZERO
	var angle := randf() * TAU
	var dist := sqrt(randf()) * radius
	return Vector2(cos(angle), sin(angle)) * dist

func _clamp_pos_in_arena(pos: Vector2, radius: float) -> Vector2:
	var min_x := radius
	var max_x := arena_size.x - radius
	var min_y := radius
	var max_y := arena_size.y - radius
	if max_x < min_x:
		max_x = min_x
	if max_y < min_y:
		max_y = min_y
	return Vector2(
		clampf(pos.x, min_x, max_x),
		clampf(pos.y, min_y, max_y)
	)
