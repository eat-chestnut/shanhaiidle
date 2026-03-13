extends Node

const DEFAULT_ARENA_SIZE := Vector2(720.0, 760.0)
const HIT_LOG_INTERVAL := 1.0
const LOOT_TTL := 20.0
const LOOT_PICKUP_RADIUS := 28.0
const DEFAULT_MONSTER_ID := "mob_a"
const DEFAULT_ENEMY_ATK := 1
const DEFAULT_DROP_RARITIES := ["white", "blue", "gold", "purple", "orange"]

var stage_name: String = "未命名关卡"
var current_stage_id: String = ""
var current_diff_index := 0
var _difficulty_def: Dictionary = {}
var arena_size: Vector2 = DEFAULT_ARENA_SIZE
var manual_vec: Vector2 = Vector2.ZERO
var _base_battle_cfg: Dictionary = {}

var player := {
	"pos": Vector2.ZERO,
	"hp": 10,
	"max_hp": 10,
	"radius": 18.0,
	"atk": 1,
	"def": 0,
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
var elite_monster_cfg: Dictionary = {}
var boss_monster_cfg: Dictionary = {}
var elite_need := 40
var boss_need := 120
var normal_kill_counter := 0
var elite_progress := 0
var boss_progress := 0
var special_present: String = ""
var _difficulty_monsters: Dictionary = {}
var skills_by_id: Dictionary = {}
var ai_profiles_by_id: Dictionary = {}
var ai_auto_rules: Array = []
var ai_switch_cooldown := 2.0
var base_gcd := 0.4
var default_skill_per_level := 0.03
var gcd_left := 0.0
var cd_left: Dictionary = {}
var qi := 10
var qi_max := 10
var qi_regen_accum := 0.0
var events: Array[Dictionary] = []
var aoe_cap := 6
var cast_log_recent: Dictionary = {}
var player_shield := 0
var player_shield_left := 0.0
var enemy_uid_seq := 1

var enemies: Array[Dictionary] = []
var loots: Array[Dictionary] = []
var kills := 0

var battle_time := 0.0
var cycle_started_at := 0.0
var attack_timer := 0.0
var hit_log_cd := 0.0
var player_last_hit_at := 0.0
var hp_regen_accum := 0.0
var auto_seek_moving := false
var manual_seek_off_fired := false
var player_pos_inited := false

func _ready() -> void:
	randomize()
	set_process(true)
	_load_battle_cfg()
	var saved_stage := GrindModel.stage_id
	var saved_diff := maxi(0, int(GrindModel.diff_index))
	if saved_stage.is_empty():
		saved_stage = _default_stage_id()
	if not saved_stage.is_empty():
		set_stage(saved_stage, saved_diff, true)
	_reset_player_pos_if_needed()
	_sync_player_combat_stats(true)
	_log_loot_bonus_info()

func set_stage(stage_id: String, diff_index: int = 0, silent: bool = false) -> void:
	if stage_id.is_empty():
		return
	var stage: Dictionary = _find_stage_cfg(stage_id)
	if stage.is_empty():
		return
	if _base_battle_cfg.is_empty():
		_load_battle_cfg()

	current_stage_id = stage_id

	var battle_cfg: Dictionary = _base_battle_cfg.duplicate(true)
	_apply_battle_cfg(battle_cfg)
	_apply_stage_overrides(stage, diff_index)
	GrindModel.set_stage_and_diff(current_stage_id, current_diff_index, not silent)
	_reset_runtime_for_stage_switch()
	if not silent:
		EventBus.add_log("切换地图：%s" % stage_name)

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
			"qi": qi,
			"qimax": qi_max,
			"shield": player_shield,
		},
		"kills": kills,
		"elite_progress": elite_progress,
		"elite_need": elite_need,
		"boss_progress": boss_progress,
		"boss_need": boss_need,
		"special_present": special_present,
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
	_update_skill_runtime(delta)
	_enemy_attack_player(delta)
	_player_regen(delta)
	_update_loot(delta)

	attack_timer += delta
	var attack_interval: float = maxf(0.05, float(player.get("attack_interval", 0.25)))
	while attack_timer >= attack_interval:
		attack_timer -= attack_interval
		_player_auto_attack()

func _load_battle_cfg() -> void:
	var battle_any = ConfigService.get_battle_cfg()
	if battle_any is Dictionary:
		_base_battle_cfg = (battle_any as Dictionary).duplicate(true)
	else:
		_base_battle_cfg = {}

	var battle_cfg: Dictionary = _base_battle_cfg.duplicate(true)
	if not current_stage_id.is_empty():
		var stage: Dictionary = _find_stage_cfg(current_stage_id)
		if not stage.is_empty():
			_apply_battle_cfg(battle_cfg)
			_apply_stage_overrides(stage, current_diff_index)
			return

	_apply_battle_cfg(battle_cfg)

func _apply_stage_overrides(stage: Dictionary, requested_diff_index: int = 0) -> void:
	if stage.is_empty():
		current_diff_index = 0
		_difficulty_def = _default_difficulty_def()
		_apply_difficulty_monster_pools({})
		_apply_difficulty_spawn_patch({})
		_apply_difficulty_spawn_rules({})
		return
	var stage_base_name := str(stage.get("name", stage_name))

	var difficulty := _resolve_stage_difficulty(stage, requested_diff_index)
	var difficulty_name := str(difficulty.get("difficulty_name", "普通"))
	_difficulty_def = difficulty
	stage_name = stage_base_name if difficulty_name.is_empty() else "%s（%s）" % [stage_base_name, difficulty_name]

	_apply_difficulty_monster_pools(difficulty)
	_apply_difficulty_spawn_patch(difficulty)
	_apply_difficulty_spawn_rules(difficulty)

func _default_difficulty_def() -> Dictionary:
	return {
		"difficulty_name": "普通",
		"recommended_power": 0,
		"spawn_interval": 1.6,
		"onscreen_limit": 10,
		"spawn_radius": 220,
		"normal_monsters": [],
		"elite_monsters": [],
		"boss_monsters": [],
		"elite_spawn_rule": null,
		"boss_spawn_rule": null,
	}

func _resolve_stage_difficulty(stage: Dictionary, requested_diff_index: int) -> Dictionary:
	var default_diff := _default_difficulty_def()
	var diffs_any = stage.get("difficulties", [])
	if not (diffs_any is Array):
		current_diff_index = 0
		return default_diff
	var diffs: Array = diffs_any
	if diffs.is_empty():
		current_diff_index = 0
		return default_diff

	var max_idx := diffs.size() - 1
	var target_idx := clampi(requested_diff_index, 0, max_idx)
	current_diff_index = target_idx

	var diff_any = diffs[target_idx]
	if not (diff_any is Dictionary):
		return default_diff
	var diff: Dictionary = diff_any
	var out := default_diff.duplicate(true)
	out.merge(diff, true)
	return out

func _apply_difficulty_monster_pools(difficulty_any: Variant) -> void:
	_difficulty_monsters.clear()
	if not (difficulty_any is Dictionary):
		return
	var difficulty: Dictionary = difficulty_any
	for role in ["normal", "elite", "boss"]:
		var key := _difficulty_pool_key(role)
		var pool_any = difficulty.get(key, [])
		if pool_any is Array:
			_difficulty_monsters[key] = (pool_any as Array).duplicate(true)

func _apply_difficulty_spawn_patch(difficulty_any: Variant) -> void:
	if not (difficulty_any is Dictionary):
		return
	var difficulty: Dictionary = difficulty_any
	spawn_rt["respawn_s"] = maxf(0.05, float(difficulty.get("spawn_interval", spawn_rt.get("respawn_s", 1.6))))
	spawn_rt["max_alive"] = maxi(1, int(difficulty.get("onscreen_limit", spawn_rt.get("max_alive", 10))))
	spawn_rt["spawn_radius"] = maxf(1.0, float(difficulty.get("spawn_radius", spawn_rt.get("spawn_radius", 220.0))))

func _apply_difficulty_spawn_rules(difficulty_any: Variant) -> void:
	if not (difficulty_any is Dictionary):
		return
	var difficulty: Dictionary = difficulty_any
	var elite_rule_any: Variant = difficulty.get("elite_spawn_rule", null)
	var boss_rule_any: Variant = difficulty.get("boss_spawn_rule", null)
	var elite_override := _resolve_spawn_rule_every_kills(elite_rule_any)
	var boss_override := _resolve_spawn_rule_every_kills(boss_rule_any)
	if elite_override > 0:
		elite_need = elite_override
	if boss_override > 0:
		boss_need = boss_override

func _resolve_spawn_rule_every_kills(rule_any: Variant) -> int:
	if not (rule_any is Dictionary):
		return 0
	var rule: Dictionary = rule_any
	if not rule.has("every_kills"):
		return 0
	return maxi(0, int(rule.get("every_kills", 0)))

func _difficulty_pool_key(kind: String) -> String:
	match _normalize_enemy_kind(kind):
		"elite":
			return "elite_monsters"
		"boss":
			return "boss_monsters"
		_:
			return "normal_monsters"

func _default_stage_id() -> String:
	var stages_any = ConfigService.get_stages_db().get("stages", [])
	if not (stages_any is Array):
		return "nan_01"
	for stage_any in stages_any:
		if not (stage_any is Dictionary):
			continue
		var stage_id := str((stage_any as Dictionary).get("id", ""))
		if not stage_id.is_empty():
			return stage_id
	return "nan_01"

func _apply_battle_cfg(battle_cfg: Dictionary) -> void:
	stage_name = str(battle_cfg.get("stage_name", stage_name))
	_load_player_cfg(battle_cfg)
	_load_monsters_cfg(battle_cfg)
	_load_spawn_cfg(battle_cfg)
	_load_special_spawn_cfg(battle_cfg)
	_load_balance_cfg()
	_apply_spawn_position()
	var player_radius: float = float(player.get("radius", 18.0))
	player["pos"] = _clamp_pos_in_arena(player.get("pos", arena_size * 0.5), player_radius)

func _merge_battle_patch(base_cfg: Dictionary, patch_any: Variant) -> void:
	if not (patch_any is Dictionary):
		return
	var patch: Dictionary = patch_any
	for key_any in patch.keys():
		var key := str(key_any)
		if not base_cfg.has(key):
			continue
		var value: Variant = patch.get(key_any)
		if value is Dictionary:
			base_cfg[key] = (value as Dictionary).duplicate(true)
		elif value is Array:
			base_cfg[key] = (value as Array).duplicate(true)
		else:
			base_cfg[key] = value

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

func _get_monster_def_by_kind(kind: String) -> Dictionary:
	var kind_key := _normalize_enemy_kind(kind)
	var monsters_any = ConfigService.get_monsters_db().get("monsters", [])
	if monsters_any is Array:
		for mon_any in monsters_any:
			if not (mon_any is Dictionary):
				continue
			var mon: Dictionary = mon_any
			if _normalize_enemy_kind(str(mon.get("kind", "normal"))) != kind_key:
				continue
			if mon.has("is_enabled") and not bool(mon.get("is_enabled", true)):
				continue
			return mon.duplicate(true)

	match kind_key:
		"elite":
			if not elite_monster_cfg.is_empty():
				return elite_monster_cfg.duplicate(true)
		"boss":
			if not boss_monster_cfg.is_empty():
				return boss_monster_cfg.duplicate(true)
		_:
			var monster_id: String = str(spawn_rt.get("monster_id", DEFAULT_MONSTER_ID))
			var template_any = monsters_cfg.get(monster_id, default_monster_cfg)
			if template_any is Dictionary:
				return (template_any as Dictionary).duplicate(true)
	return {}

func _get_monster_def_by_id(monster_id: String) -> Dictionary:
	var target_id := monster_id.strip_edges()
	if target_id.is_empty():
		return {}

	var monsters_any = ConfigService.get_monsters_db().get("monsters", [])
	if monsters_any is Array:
		for mon_any in monsters_any:
			if not (mon_any is Dictionary):
				continue
			var mon: Dictionary = mon_any
			if str(mon.get("id", "")).strip_edges() == target_id:
				return mon.duplicate(true)

	var battle_any = ConfigService.get_battle_cfg()
	if battle_any is Dictionary:
		var battle_cfg: Dictionary = battle_any
		var normal_any = battle_cfg.get("monsters", [])
		if normal_any is Array:
			for mon_any in normal_any:
				if not (mon_any is Dictionary):
					continue
				var mon: Dictionary = mon_any
				if str(mon.get("id", "")).strip_edges() == target_id:
					return mon.duplicate(true)

		var elite_any = battle_cfg.get("elite_monster", {})
		if elite_any is Dictionary:
			var elite: Dictionary = elite_any
			if str(elite.get("id", "")).strip_edges() == target_id:
				return elite.duplicate(true)

		var boss_any = battle_cfg.get("boss_monster", {})
		if boss_any is Dictionary:
			var boss: Dictionary = boss_any
			if str(boss.get("id", "")).strip_edges() == target_id:
				return boss.duplicate(true)

	return {}

func _get_monster_def_for_kind(kind: String) -> Dictionary:
	var kind_key := _normalize_enemy_kind(kind)
	var stage_monster_id := _get_monster_id_for_kind(kind_key)
	if not stage_monster_id.is_empty():
		var by_id := _get_monster_def_by_id(stage_monster_id)
		if not by_id.is_empty():
			by_id["kind"] = kind_key
			return by_id

	var fallback := _get_monster_def_by_kind(kind_key)
	if not fallback.is_empty():
		fallback["kind"] = kind_key
	return fallback

func _pick_from_pool(pool: Array) -> String:
	var total := 0
	for entry_any in pool:
		if not (entry_any is Dictionary):
			continue
		var entry: Dictionary = entry_any
		total += maxi(0, int(entry.get("weight", entry.get("w", 0))))
	if total <= 0:
		return ""

	var roll := randi() % total
	var acc := 0
	for entry_any in pool:
		if not (entry_any is Dictionary):
			continue
		var entry: Dictionary = entry_any
		var w := maxi(0, int(entry.get("weight", entry.get("w", 0))))
		acc += w
		if roll < acc:
			return str(entry.get("monster_id", entry.get("id", ""))).strip_edges()
	return ""

func _get_monster_id_for_kind(kind: String) -> String:
	var kind_key := _normalize_enemy_kind(kind)
	if _difficulty_monsters.is_empty():
		return ""

	var pool_key := _difficulty_pool_key(kind_key)
	var pool_any = _difficulty_monsters.get(pool_key, [])
	if pool_any is Array:
		var picked := _pick_from_pool(pool_any)
		if not picked.is_empty():
			return picked

	return ""

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

func _load_special_spawn_cfg(battle_cfg: Dictionary) -> void:
	elite_need = 40
	boss_need = 120
	elite_monster_cfg.clear()
	boss_monster_cfg.clear()

	var spawn_rules_any = battle_cfg.get("spawn_rules", {})
	if spawn_rules_any is Dictionary:
		var spawn_rules: Dictionary = spawn_rules_any
		elite_need = maxi(1, int(spawn_rules.get("elite_every_kills", elite_need)))
		boss_need = maxi(1, int(spawn_rules.get("boss_every_kills", boss_need)))

	var elite_any = battle_cfg.get("elite_monster", {})
	if elite_any is Dictionary:
		elite_monster_cfg = (elite_any as Dictionary).duplicate(true)
		elite_monster_cfg["kind"] = "elite"

	var boss_any = battle_cfg.get("boss_monster", {})
	if boss_any is Dictionary:
		boss_monster_cfg = (boss_any as Dictionary).duplicate(true)
		boss_monster_cfg["kind"] = "boss"

func _load_balance_cfg() -> void:
	skills_by_id.clear()
	ai_profiles_by_id.clear()
	ai_auto_rules.clear()
	base_gcd = 0.4
	default_skill_per_level = 0.03
	ai_switch_cooldown = 2.0

	var skills_any = ConfigService.get_skills_catalog_db().get("skills_catalog", [])
	if skills_any is Array:
		for skill_any in skills_any:
			if not (skill_any is Dictionary):
				continue
			var skill: Dictionary = skill_any
			var skill_id := str(skill.get("id", ""))
			if skill_id.is_empty():
				continue
			skills_by_id[skill_id] = skill.duplicate(true)

	var combat_any = ConfigService.get_battle_cfg().get("combat", {})
	if combat_any is Dictionary:
		var combat: Dictionary = combat_any
		base_gcd = maxf(0.05, float(combat.get("gcd_seconds", 0.4)))
		default_skill_per_level = float(combat.get("default_skill_coef_per_level", 0.03))

	var ai_any = ConfigService.get_battle_cfg().get("ai_profiles", {})
	if ai_any is Dictionary:
		var ai_profiles: Dictionary = ai_any
		var profiles_any = ai_profiles.get("profiles", [])
		if profiles_any is Array:
			for profile_any in profiles_any:
				if not (profile_any is Dictionary):
					continue
				var profile: Dictionary = profile_any
				var profile_id := str(profile.get("id", ""))
				if profile_id.is_empty():
					continue
				ai_profiles_by_id[profile_id] = profile.duplicate(true)
		var auto_any = ai_profiles.get("auto_switch", {})
		if auto_any is Dictionary:
			var auto_switch: Dictionary = auto_any
			var rules_any = auto_switch.get("rules_in_order", [])
			if rules_any is Array:
				ai_auto_rules = rules_any
			ai_switch_cooldown = maxf(0.0, float(auto_switch.get("switch_cooldown_sec", 2.0)))

func _find_stage_cfg(stage_id: String) -> Dictionary:
	var stages_any = ConfigService.get_stages_db().get("stages", [])
	if not (stages_any is Array):
		return {}
	for stage_any in stages_any:
		if not (stage_any is Dictionary):
			continue
		var stage: Dictionary = stage_any
		if str(stage.get("id", "")) == stage_id:
			return stage.duplicate(true)
	return {}

func _reset_runtime_for_stage_switch() -> void:
	enemies.clear()
	loots.clear()
	events.clear()
	kills = 0
	normal_kill_counter = 0
	elite_progress = 0
	boss_progress = 0
	special_present = ""
	attack_timer = 0.0
	hit_log_cd = 0.0
	player_last_hit_at = battle_time
	hp_regen_accum = 0.0
	auto_seek_moving = false
	manual_seek_off_fired = false
	gcd_left = 0.0
	cd_left.clear()
	cast_log_recent.clear()
	player_shield = 0
	player_shield_left = 0.0
	spawn_rt["alive_count"] = 0
	spawn_rt["next_spawn_at"] = battle_time
	qi = mini(qi, qi_max)
	qi_regen_accum = 0.0
	cycle_started_at = battle_time

func _sync_player_combat_stats(force_full: bool = false) -> void:
	var stats: Dictionary = EquipmentModel.get_total_stats()
	var new_hp_max := maxi(1, int(stats.get("HP", int(player.get("max_hp", 10)))))
	var new_qi_max := maxi(1, int(stats.get("QI", qi_max)))
	var current_hp := int(player.get("hp", new_hp_max))

	if force_full:
		current_hp = new_hp_max
		qi = new_qi_max
		qi_regen_accum = 0.0
	else:
		current_hp = mini(current_hp, new_hp_max)
		qi = mini(qi, new_qi_max)

	player["max_hp"] = new_hp_max
	player["hp"] = clampi(current_hp, 0, new_hp_max)
	player["atk"] = int(stats.get("ATK", int(player.get("atk", 1))))
	player["def"] = int(round(float(stats.get("DEF", float(player.get("def", 0))))))
	player["crit"] = int(stats.get("CRIT_PERCENT", stats.get("CRIT", 0)))
	qi_max = new_qi_max

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
	if not _has_special_enemy_alive() and not special_present.is_empty():
		special_present = ""
	if special_present == "boss":
		return

	var alive_count: int = int(spawn_rt.get("alive_count", 0))
	var max_alive: int = maxi(0, int(spawn_rt.get("max_alive", 0)))
	var next_spawn_at: float = float(spawn_rt.get("next_spawn_at", 0.0))
	var respawn_s: float = maxf(0.05, float(spawn_rt.get("respawn_s", 1.6)))

	if alive_count < max_alive and battle_time >= next_spawn_at:
		if _spawn_enemy():
			alive_count += 1
			spawn_rt["alive_count"] = alive_count
			spawn_rt["next_spawn_at"] = battle_time + respawn_s

func _spawn_enemy() -> bool:
	var template: Dictionary = _get_monster_def_for_kind("normal")
	if template.is_empty():
		return false
	MonsterDexModel.mark_seen(template)
	var monster_id: String = str(template.get("id", str(spawn_rt.get("monster_id", DEFAULT_MONSTER_ID))))
	if monster_id.is_empty():
		monster_id = str(spawn_rt.get("monster_id", DEFAULT_MONSTER_ID))

	var hp: int = int(template.get("hp", 30))
	var enemy_radius: float = float(template.get("radius", 14.0))
	var home_pos: Vector2 = spawn_rt.get("pos", Vector2.ZERO)
	var spawn_radius: float = maxf(0.0, float(spawn_rt.get("spawn_radius", 0.0)))
	var spawn_pos: Vector2 = home_pos + _random_point_in_circle(spawn_radius)
	spawn_pos = _clamp_pos_in_arena(spawn_pos, enemy_radius)

	enemies.append({
		"uid": enemy_uid_seq,
		"spawn_id": str(spawn_rt.get("id", "sp_1")),
		"name": str(template.get("name", monster_id)),
		"monster_id": monster_id,
		"kind": "normal",
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
		"exp": maxi(0, int(template.get("exp", 1))),
		"drop_bonus_percent": maxi(0, int(template.get("drop_bonus_percent", 0))),
		"drops": template.get("drops", []),
	})
	enemy_uid_seq += 1
	return true

func _spawn_special_enemy(kind: String) -> bool:
	var normalized_kind := _normalize_enemy_kind(kind)
	var template: Dictionary = _get_monster_def_for_kind(normalized_kind)
	if template.is_empty():
		return false
	MonsterDexModel.mark_seen(template)

	var monster_id := str(template.get("id", normalized_kind))
	if monster_id.is_empty():
		monster_id = normalized_kind
	var hp: int = int(template.get("hp", 30))
	var enemy_radius: float = float(template.get("radius", 14.0))
	var home_pos: Vector2 = spawn_rt.get("pos", Vector2.ZERO)
	var spawn_radius: float = maxf(0.0, float(spawn_rt.get("spawn_radius", 0.0)))
	var spawn_pos: Vector2 = home_pos + _random_point_in_circle(spawn_radius)
	spawn_pos = _clamp_pos_in_arena(spawn_pos, enemy_radius)

	enemies.append({
		"uid": enemy_uid_seq,
		"spawn_id": "special",
		"name": str(template.get("name", monster_id)),
		"monster_id": monster_id,
		"kind": normalized_kind,
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
		"exp": maxi(0, int(template.get("exp", 1))),
		"drop_bonus_percent": maxi(0, int(template.get("drop_bonus_percent", 0))),
		"drops": template.get("drops", []),
	})
	enemy_uid_seq += 1
	special_present = normalized_kind
	if normalized_kind == "boss":
		EventBus.add_log("Boss出现：%s" % str(template.get("name", monster_id)))
	else:
		EventBus.add_log("精英出现：%s" % str(template.get("name", monster_id)))
	return true

func _try_trigger_special_spawn() -> void:
	if special_present != "" or _has_special_enemy_alive():
		return
	if boss_need > 0 and boss_progress >= boss_need:
		if _spawn_special_enemy("boss"):
			boss_progress -= boss_need
			return
	if elite_need > 0 and elite_progress >= elite_need:
		if _spawn_special_enemy("elite"):
			elite_progress -= elite_need

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

	if player_shield > 0:
		var absorbed := mini(player_shield, total_damage)
		player_shield -= absorbed
		total_damage -= absorbed

	if total_damage <= 0:
		return

	var hp_now := maxi(0, int(player.get("hp", 0)) - total_damage)
	player["hp"] = hp_now
	player_last_hit_at = battle_time
	hp_regen_accum = 0.0
	if hit_log_cd <= 0.0:
		EventBus.add_log("受击 -%d（%s %d/%d）" % [total_damage, I18nService.stat("HP"), hp_now, int(player.get("max_hp", hp_now))])
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
	var best_priority := 99
	var best_dist_sq := INF

	for i in range(enemies.size()):
		var enemy: Dictionary = enemies[i]
		var enemy_pos: Vector2 = enemy.get("pos", Vector2.ZERO)
		var enemy_radius: float = float(enemy.get("radius", 14.0))
		var reach: float = player_attack_range + player_radius + enemy_radius
		var distance_to_enemy: float = player_pos.distance_to(enemy_pos)
		if distance_to_enemy > reach:
			continue
		var priority := _enemy_priority(enemy)
		var dist_sq := player_pos.distance_squared_to(enemy_pos)
		if priority < best_priority or (priority == best_priority and dist_sq < best_dist_sq):
			best_priority = priority
			best_dist_sq = dist_sq
			target_index = i

	if target_index < 0:
		return
	var enemy_target: Dictionary = enemies[target_index]
	var damage := maxi(1, int(player.get("atk", 12)) - int(enemy_target.get("def", 2)))
	_apply_damage_to_enemy(target_index, damage)

func _update_skill_runtime(delta: float) -> void:
	_update_shield_runtime(delta)
	_update_qi_runtime(delta)
	_update_cd_runtime(delta)
	_update_skill_events(delta)
	_auto_switch_ai_profile()
	if gcd_left <= 0.0:
		_try_cast_skill()

func _update_shield_runtime(delta: float) -> void:
	if player_shield_left <= 0.0:
		return
	player_shield_left = maxf(0.0, player_shield_left - delta)
	if player_shield_left <= 0.0:
		player_shield = 0

func _update_qi_runtime(delta: float) -> void:
	qi = clampi(qi, 0, qi_max)
	qi_regen_accum += delta
	var ticks := int(floor(qi_regen_accum))
	if ticks <= 0:
		return
	qi_regen_accum -= float(ticks)
	qi = mini(qi_max, qi + ticks)

func _update_cd_runtime(delta: float) -> void:
	if gcd_left > 0.0:
		gcd_left = maxf(0.0, gcd_left - delta)
	for skill_id_any in cd_left.keys():
		var skill_id := str(skill_id_any)
		var left := maxf(0.0, float(cd_left.get(skill_id, 0.0)) - delta)
		cd_left[skill_id] = left

func _update_skill_events(delta: float) -> void:
	if events.is_empty():
		return
	for i in range(events.size() - 1, -1, -1):
		var event_any = events[i]
		if not (event_any is Dictionary):
			events.remove_at(i)
			continue
		var event: Dictionary = event_any
		var event_type := str(event.get("type", ""))
		var finished := true
		match event_type:
			"dot":
				finished = _update_dot_event(event, delta)
			"delayed":
				finished = _update_delayed_event(event, delta)
			"barrage":
				finished = _update_barrage_event(event, delta)
			_:
				finished = true
		if finished:
			events.remove_at(i)
		else:
			events[i] = event

func _update_dot_event(event: Dictionary, delta: float) -> bool:
	var tick_timer := float(event.get("tick_timer", 1.0)) - delta
	var tick_interval := maxf(0.1, float(event.get("tick_interval", 1.0)))
	var ticks_left := maxi(0, int(event.get("ticks_left", 0)))
	var damage := maxi(1, int(event.get("damage", 1)))
	var enemy_uid := int(event.get("enemy_uid", 0))
	while tick_timer <= 0.0 and ticks_left > 0:
		_apply_damage_to_enemy_uid(enemy_uid, damage)
		ticks_left -= 1
		tick_timer += tick_interval
	event["tick_timer"] = tick_timer
	event["ticks_left"] = ticks_left
	return ticks_left <= 0

func _update_delayed_event(event: Dictionary, delta: float) -> bool:
	var timer := float(event.get("timer", 0.0)) - delta
	if timer > 0.0:
		event["timer"] = timer
		return false
	var center: Vector2 = event.get("center", player.get("pos", Vector2.ZERO))
	var radius := maxf(0.0, float(event.get("radius", 0.0)))
	var damage := maxi(1, int(event.get("damage", 1)))
	_apply_aoe_damage(center, radius, damage)
	return true

func _update_barrage_event(event: Dictionary, delta: float) -> bool:
	var tick_timer := float(event.get("tick_timer", 0.5)) - delta
	var tick_interval := maxf(0.1, float(event.get("tick_interval", 0.5)))
	var hits_left := maxi(0, int(event.get("hits_left", 0)))
	var center: Vector2 = event.get("center", player.get("pos", Vector2.ZERO))
	var radius := maxf(0.0, float(event.get("radius", 0.0)))
	var damage := maxi(1, int(event.get("damage", 1)))
	while tick_timer <= 0.0 and hits_left > 0:
		_apply_aoe_damage(center, radius, damage)
		hits_left -= 1
		tick_timer += tick_interval
	event["tick_timer"] = tick_timer
	event["hits_left"] = hits_left
	return hits_left <= 0

func _auto_switch_ai_profile() -> void:
	if ai_auto_rules.is_empty():
		return
	var context := _build_ai_context()
	for rule_any in ai_auto_rules:
		if not (rule_any is Dictionary):
			continue
		var rule: Dictionary = rule_any
		var expr := str(rule.get("if", "true"))
		if not _evaluate_expr(expr, context):
			continue
		var target_profile := str(rule.get("profile", rule.get("target_profile", "")))
		if target_profile.is_empty() or not ai_profiles_by_id.has(target_profile):
			break
		if target_profile == SkillModel.ai_profile:
			break
		var elapsed := battle_time - float(SkillModel.last_switch_ts)
		if elapsed >= ai_switch_cooldown:
			SkillModel.ai_profile = target_profile
			SkillModel.last_switch_ts = battle_time
			SkillModel.save()
			EventBus.notify_inventory_updated()
		break

func _try_cast_skill() -> bool:
	if enemies.is_empty():
		return false
	var profile := _resolve_ai_profile()
	if profile.is_empty():
		return false
	var priorities_any = profile.get("priorities", {})
	if not (priorities_any is Dictionary):
		return false
	var priorities: Dictionary = priorities_any
	var class_prior_any = priorities.get(SkillModel.current_class, {})
	if not (class_prior_any is Dictionary):
		return false
	var class_prior: Dictionary = class_prior_any
	var sorted_entries := _sorted_priority_entries(class_prior)
	if sorted_entries.is_empty():
		return false
	var cast_conditions: Dictionary = {}
	var cast_conditions_any = profile.get("cast_conditions", {})
	if cast_conditions_any is Dictionary:
		cast_conditions = cast_conditions_any
	var context := _build_ai_context()
	for entry in sorted_entries:
		if not (entry is Dictionary):
			continue
		var skill_id := str(entry.get("id", ""))
		if skill_id.is_empty():
			continue
		var level := SkillModel.get_effective_level(skill_id)
		if level <= 0:
			continue
		var skill := _get_skill_def(skill_id)
		if skill.is_empty() or str(skill.get("type", "")) != "active":
			continue
		var cooldown_left := float(cd_left.get(skill_id, 0.0))
		if cooldown_left > 0.0:
			continue
		var cost_qi := _skill_cost_qi(skill, level)
		if qi < cost_qi:
			continue
		if cast_conditions.has(skill_id):
			var expr := str(cast_conditions.get(skill_id, "true"))
			if not _evaluate_expr(expr, context):
				continue
		var target := _resolve_skill_target(skill)
		if not bool(target.get("valid", false)):
			continue
		if _cast_skill(skill_id, skill, level, target, cost_qi):
			return true
	return false

func _cast_skill(skill_id: String, skill: Dictionary, level: int, target: Dictionary, cost_qi: int) -> bool:
	var totals: Dictionary = EquipmentModel.get_total_stats()
	var did_effect := false
	var log_damage := 0
	var primary_uid := int(target.get("primary_uid", 0))
	var primary_pos: Vector2 = target.get("center", player.get("pos", Vector2.ZERO))

	var damage_any = skill.get("damage", {})
	if damage_any is Dictionary:
		var damage_def: Dictionary = damage_any
		var damage := _calc_skill_damage(damage_def, level, totals)
		log_damage = maxi(log_damage, damage)
		did_effect = _apply_skill_damage(damage_def, target, damage) or did_effect

		var chain_any = skill.get("chain", {})
		if chain_any is Dictionary and primary_uid > 0:
			var chain_damage := _apply_chain_damage(primary_uid, primary_pos, chain_any, damage)
			if chain_damage > 0:
				log_damage = maxi(log_damage, chain_damage)
				did_effect = true

	var shield_def_any = skill.get("shield", skill.get("barrier", {}))
	if shield_def_any is Dictionary:
		var shield_def: Dictionary = shield_def_any
		if not shield_def.is_empty():
			var shield_value := _calc_shield_value(shield_def, level)
			if shield_value > 0:
				player_shield += shield_value
				player_shield_left = maxf(player_shield_left, maxf(0.2, float(shield_def.get("duration_sec", 1.0))))
				did_effect = true

	var heal_any = skill.get("heal", {})
	if heal_any is Dictionary:
		var heal_val := _calc_heal_value(heal_any, level, totals)
		if heal_val > 0:
			var hp_now := int(player.get("hp", 0))
			var hp_max := int(player.get("max_hp", hp_now))
			player["hp"] = mini(hp_max, hp_now + heal_val)
			did_effect = true

	var dot_any = skill.get("dot", {})
	if dot_any is Dictionary:
		did_effect = _spawn_dot_event(dot_any, target, level, totals) or did_effect

	var delayed_any = skill.get("delayed_explosion", {})
	if delayed_any is Dictionary:
		did_effect = _spawn_delayed_event(delayed_any, target, level, totals) or did_effect

	var barrage_any = skill.get("area_barrage", {})
	if barrage_any is Dictionary:
		var barrage_damage := _spawn_barrage_event(barrage_any, target, level, totals)
		if barrage_damage > 0:
			log_damage = maxi(log_damage, barrage_damage)
			did_effect = true

	if not did_effect:
		return false

	qi = maxi(0, qi - cost_qi)
	var cd_sec := maxf(0.05, float(skill.get("cd_sec", base_gcd)))
	cd_left[skill_id] = cd_sec
	gcd_left = base_gcd
	_log_skill_cast(skill_id, str(skill.get("name", skill_id)), log_damage)
	return true

func _apply_skill_damage(damage_def: Dictionary, target: Dictionary, damage: int) -> bool:
	if damage <= 0:
		return false
	var rule := str(target.get("rule", "primary"))
	var primary_index := int(target.get("primary_index", -1))
	var primary_pos: Vector2 = target.get("center", player.get("pos", Vector2.ZERO))
	if primary_index >= 0 and primary_index < enemies.size():
		var enemy_any = enemies[primary_index]
		if enemy_any is Dictionary:
			primary_pos = (enemy_any as Dictionary).get("pos", primary_pos)

	var splash_any = damage_def.get("splash", {})
	if splash_any is Dictionary and primary_index >= 0:
		_apply_damage_to_enemy(primary_index, damage)
		var splash: Dictionary = splash_any
		var splash_pct := clampf(float(splash.get("pct_of_main", 0.0)), 0.0, 1.0)
		var splash_radius := maxf(0.0, float(splash.get("radius", 0.0)))
		var splash_damage := maxi(1, int(round(float(damage) * splash_pct)))
		if splash_radius > 0.0 and splash_pct > 0.0:
			var primary_uid := int(target.get("primary_uid", 0))
			var splash_targets := _collect_enemy_indices_in_circle(primary_pos, splash_radius, primary_uid)
			_apply_damage_to_indices(splash_targets, splash_damage)
		return true

	var aoe_any = damage_def.get("aoe", {})
	if aoe_any is Dictionary:
		var aoe: Dictionary = aoe_any
		var radius := maxf(0.0, float(aoe.get("radius", 0.0)))
		if radius > 0.0:
			var center: Vector2 = target.get("center", primary_pos)
			if rule == "primary":
				center = primary_pos
			return _apply_aoe_damage(center, radius, damage)

	if primary_index >= 0:
		_apply_damage_to_enemy(primary_index, damage)
		return true
	return false

func _apply_chain_damage(primary_uid: int, primary_pos: Vector2, chain_def: Dictionary, damage: int) -> int:
	if primary_uid <= 0 or damage <= 0:
		return 0
	var bounce_count := mini(maxi(0, int(chain_def.get("bounces", 1))), 1)
	if bounce_count <= 0:
		return 0
	var bounce_range := maxf(0.0, float(chain_def.get("bounce_range", 4.0)))
	var next_idx := _find_nearest_enemy_index_to_point(primary_pos, primary_uid, bounce_range)
	if next_idx < 0:
		return 0
	_apply_damage_to_enemy(next_idx, damage)
	return damage

func _spawn_dot_event(dot_def: Dictionary, target: Dictionary, _level: int, totals: Dictionary) -> bool:
	var primary_uid := int(target.get("primary_uid", 0))
	if primary_uid <= 0:
		return false
	var tick_sec := maxf(0.1, float(dot_def.get("tick_sec", 1.0)))
	var duration := maxf(tick_sec, float(dot_def.get("duration_sec", tick_sec)))
	var ticks := maxi(1, int(floor(duration / tick_sec)))
	var source_val := _resolve_source_value(str(dot_def.get("source", "SP")), totals)
	var coef := maxf(0.0, float(dot_def.get("coef_per_tick", 0.1)))
	var tick_damage := maxi(1, int(round(float(source_val) * coef)))
	events.append({
		"type": "dot",
		"enemy_uid": primary_uid,
		"tick_interval": tick_sec,
		"tick_timer": tick_sec,
		"ticks_left": ticks,
		"damage": tick_damage,
	})
	return true

func _spawn_delayed_event(delayed_def: Dictionary, target: Dictionary, level: int, totals: Dictionary) -> bool:
	var damage_any = delayed_def.get("damage", {})
	if not (damage_any is Dictionary):
		return false
	var damage_def: Dictionary = damage_any
	var damage := _calc_skill_damage(damage_def, level, totals)
	var aoe_any = damage_def.get("aoe", {})
	var radius := 0.0
	if aoe_any is Dictionary:
		radius = maxf(0.0, float((aoe_any as Dictionary).get("radius", 0.0)))
	if radius <= 0.0:
		return false
	var center: Vector2 = target.get("center", player.get("pos", Vector2.ZERO))
	var primary_idx := int(target.get("primary_index", -1))
	if primary_idx >= 0 and primary_idx < enemies.size():
		var enemy_any = enemies[primary_idx]
		if enemy_any is Dictionary:
			center = (enemy_any as Dictionary).get("pos", center)
	var delay_sec := maxf(0.1, float(delayed_def.get("delay_sec", 0.8)))
	events.append({
		"type": "delayed",
		"timer": delay_sec,
		"center": center,
		"radius": radius,
		"damage": damage,
	})
	return true

func _spawn_barrage_event(barrage_def: Dictionary, target: Dictionary, level: int, totals: Dictionary) -> int:
	var hit_damage_any = barrage_def.get("per_hit_damage", {})
	if not (hit_damage_any is Dictionary):
		return 0
	var hit_damage_def: Dictionary = hit_damage_any
	var hit_damage := _calc_skill_damage(hit_damage_def, level, totals)
	var duration := maxf(0.2, float(barrage_def.get("duration_sec", 1.0)))
	var hits := maxi(1, int(barrage_def.get("hits", 1)))
	var tick_interval := duration / float(hits)
	var aoe_any = barrage_def.get("aoe", {})
	var radius := 0.0
	if aoe_any is Dictionary:
		radius = maxf(0.0, float((aoe_any as Dictionary).get("radius", 0.0)))
	if radius <= 0.0:
		return 0
	var center: Vector2 = target.get("center", player.get("pos", Vector2.ZERO))
	events.append({
		"type": "barrage",
		"center": center,
		"radius": radius,
		"damage": hit_damage,
		"hits_left": hits,
		"tick_interval": tick_interval,
		"tick_timer": tick_interval,
	})
	return hit_damage

func _apply_aoe_damage(center: Vector2, radius: float, damage: int) -> bool:
	if radius <= 0.0 or damage <= 0:
		return false
	var targets := _collect_enemy_indices_in_circle(center, radius)
	if targets.is_empty():
		return false
	_apply_damage_to_indices(targets, damage)
	return true

func _collect_enemy_indices_in_circle(center: Vector2, radius: float, exclude_uid: int = 0) -> Array:
	var pairs: Array = []
	for i in range(enemies.size()):
		var enemy: Dictionary = enemies[i]
		if exclude_uid > 0 and int(enemy.get("uid", 0)) == exclude_uid:
			continue
		var enemy_pos: Vector2 = enemy.get("pos", Vector2.ZERO)
		if enemy_pos.distance_to(center) > radius:
			continue
		pairs.append({
			"idx": i,
			"dist_sq": center.distance_squared_to(enemy_pos),
		})
	pairs.sort_custom(_sort_dist_pair)
	var indices: Array = []
	var cap := mini(aoe_cap, pairs.size())
	for i in range(cap):
		var pair_any = pairs[i]
		if not (pair_any is Dictionary):
			continue
		indices.append(int((pair_any as Dictionary).get("idx", -1)))
	return indices

func _sort_dist_pair(a: Dictionary, b: Dictionary) -> bool:
	return float(a.get("dist_sq", INF)) < float(b.get("dist_sq", INF))

func _apply_damage_to_indices(indices: Array, damage: int) -> void:
	if damage <= 0:
		return
	if indices.is_empty():
		return
	var sorted := indices.duplicate()
	sorted.sort()
	for i in range(sorted.size() - 1, -1, -1):
		var idx := int(sorted[i])
		_apply_damage_to_enemy(idx, damage)

func _apply_damage_to_enemy_uid(enemy_uid: int, damage: int) -> bool:
	var idx := _find_enemy_index_by_uid(enemy_uid)
	if idx < 0:
		return false
	return _apply_damage_to_enemy(idx, damage)

func _apply_damage_to_enemy(index: int, damage: int) -> bool:
	if index < 0 or index >= enemies.size():
		return false
	var enemy: Dictionary = enemies[index]
	var real_damage := maxi(1, damage)
	enemy["hp"] = int(enemy.get("hp", 0)) - real_damage
	if int(enemy.get("hp", 0)) > 0:
		enemies[index] = enemy
		return false

	var death_pos: Vector2 = enemy.get("pos", player.get("pos", Vector2.ZERO))
	var spawn_id: String = str(enemy.get("spawn_id", "sp_1"))
	var enemy_exp: int = maxi(0, int(enemy.get("exp", 1)))
	var enemy_kind: String = _normalize_enemy_kind(str(enemy.get("kind", "normal")))
	var enemy_name := str(enemy.get("name", enemy.get("monster_id", "敌人")))
	var enemy_drop_bonus := maxi(0, int(enemy.get("drop_bonus_percent", 0)))
	enemies.remove_at(index)
	_decrease_spawn_alive(spawn_id)
	if enemy_kind == "normal":
		normal_kill_counter += 1
		elite_progress += 1
		boss_progress += 1
	else:
		special_present = ""
		EventBus.add_log("已击败：%s" % enemy_name)
		if enemy_kind == "boss":
			var clear_seconds := maxf(1.0, battle_time - cycle_started_at)
			var patrol_ret := OfflineService.record_patrol_clear(current_stage_id, current_diff_index, clear_seconds)
			if bool(patrol_ret.get("improved", false)):
				EventBus.add_log("自动巡查路线更新：%s %.1f秒" % [
					str(patrol_ret.get("route_name", stage_name)),
					float(patrol_ret.get("best_clear_seconds", clear_seconds)),
				])
			if MapProgressModel.mark_stage_cleared(current_stage_id, current_diff_index):
				EventBus.add_log("通关地图：%s" % stage_name)
				TaskService.on_stage_cleared(current_stage_id)
				EventBus.request_profile_sync("stage_clear")
			normal_kill_counter = 0
			elite_progress = 0
			boss_progress = 0
			cycle_started_at = battle_time
	kills += 1
	_heal_on_kill(enemy_kind)
	PerfTracker.record_kill(enemy_kind, enemy_exp)
	ProgressModel.add_exp(enemy_exp, "online")
	TaskService.on_kill(enemy_kind, 1)
	_try_spawn_drop(enemy, death_pos, enemy_drop_bonus)
	if enemy_kind == "normal":
		_try_trigger_special_spawn()
	elif enemy_kind == "elite":
		_try_trigger_special_spawn()
	if kills % 5 == 0:
		EventBus.add_log("击杀累计：%d（场上%d）" % [kills, enemies.size()])
	return true

func _find_enemy_index_by_uid(enemy_uid: int) -> int:
	if enemy_uid <= 0:
		return -1
	for i in range(enemies.size()):
		var enemy: Dictionary = enemies[i]
		if int(enemy.get("uid", 0)) == enemy_uid:
			return i
	return -1

func _find_nearest_enemy_index_to_point(point: Vector2, exclude_uid: int = 0, max_range: float = INF) -> int:
	var best_idx := -1
	var best_dist_sq := INF
	var max_dist_sq := max_range * max_range
	for i in range(enemies.size()):
		var enemy: Dictionary = enemies[i]
		if exclude_uid > 0 and int(enemy.get("uid", 0)) == exclude_uid:
			continue
		var enemy_pos: Vector2 = enemy.get("pos", Vector2.ZERO)
		var dist_sq := point.distance_squared_to(enemy_pos)
		if dist_sq >= best_dist_sq:
			continue
		if max_range < INF and dist_sq > max_dist_sq:
			continue
		best_dist_sq = dist_sq
		best_idx = i
	return best_idx

func _resolve_ai_profile() -> Dictionary:
	var profile_id := str(SkillModel.ai_profile)
	if ai_profiles_by_id.has(profile_id):
		var profile_any = ai_profiles_by_id[profile_id]
		if profile_any is Dictionary:
			return profile_any
	if ai_profiles_by_id.has("clear"):
		var clear_any = ai_profiles_by_id["clear"]
		if clear_any is Dictionary:
			return clear_any
	for profile_any in ai_profiles_by_id.values():
		if profile_any is Dictionary:
			return profile_any
	return {}

func _sorted_priority_entries(priority_map: Dictionary) -> Array:
	var rows: Array = []
	for skill_id_any in priority_map.keys():
		rows.append({
			"id": str(skill_id_any),
			"priority": int(priority_map.get(skill_id_any, 0)),
		})
	rows.sort_custom(_sort_priority_desc)
	return rows

func _sort_priority_desc(a: Dictionary, b: Dictionary) -> bool:
	var pa := int(a.get("priority", 0))
	var pb := int(b.get("priority", 0))
	if pa != pb:
		return pa > pb
	return str(a.get("id", "")) < str(b.get("id", ""))

func _get_skill_def(skill_id: String) -> Dictionary:
	var skill_any = skills_by_id.get(skill_id, {})
	if skill_any is Dictionary:
		return skill_any
	return {}

func _skill_cost_qi(skill: Dictionary, level: int) -> int:
	var cost := maxi(0, int(skill.get("cost_qi", 0)))
	var milestone := _active_milestone(skill, level)
	if milestone.has("cost_qi_delta"):
		cost += int(round(float(milestone.get("cost_qi_delta", 0.0))))
	return maxi(0, cost)

func _active_milestone(skill: Dictionary, level: int) -> Dictionary:
	var result: Dictionary = {}
	var milestones_any = skill.get("milestones", {})
	if not (milestones_any is Dictionary):
		return result
	var milestones: Dictionary = milestones_any
	var best_level := -1
	for lv_key_any in milestones.keys():
		var lv := int(str(lv_key_any))
		if lv > level or lv <= best_level:
			continue
		var row_any = milestones.get(lv_key_any, {})
		if not (row_any is Dictionary):
			continue
		best_level = lv
		result = row_any
	return result

func _resolve_skill_target(skill: Dictionary) -> Dictionary:
	var target_rule := str(skill.get("target_rule", "primary"))
	var target := {
		"valid": false,
		"rule": target_rule,
		"primary_index": -1,
		"primary_uid": 0,
		"center": player.get("pos", Vector2.ZERO),
	}
	match target_rule:
		"self":
			target["valid"] = true
			target["center"] = player.get("pos", Vector2.ZERO)
		"cluster":
			if enemies.is_empty():
				return target
			var sum := Vector2.ZERO
			for enemy in enemies:
				if enemy is Dictionary:
					sum += (enemy as Dictionary).get("pos", Vector2.ZERO)
			target["valid"] = true
			target["center"] = sum / float(maxi(1, enemies.size()))
		_:
			var nearest_idx := _find_nearest_enemy_index()
			if nearest_idx < 0:
				return target
			var nearest_enemy: Dictionary = enemies[nearest_idx]
			target["valid"] = true
			target["primary_index"] = nearest_idx
			target["primary_uid"] = int(nearest_enemy.get("uid", 0))
			target["center"] = nearest_enemy.get("pos", Vector2.ZERO)
	return target

func _calc_skill_damage(damage_def: Dictionary, level: int, totals: Dictionary) -> int:
	var source := str(damage_def.get("source", "WD"))
	var source_val := _resolve_source_value(source, totals)
	var coef := 0.0
	if damage_def.has("coef"):
		coef = float(damage_def.get("coef", 0.0))
	else:
		var base_coef := float(damage_def.get("base_coef", 0.0))
		var per_level := float(damage_def.get("per_level", default_skill_per_level))
		coef = base_coef + per_level * float(level - 1)
	return maxi(1, int(round(float(source_val) * coef)))

func _calc_shield_value(shield_def: Dictionary, level: int) -> int:
	var base_value := float(shield_def.get("base_value", 0.0))
	var per_level := float(shield_def.get("per_level", 0.0))
	return maxi(0, int(round(base_value + per_level * float(level - 1))))

func _calc_heal_value(heal_def: Dictionary, level: int, totals: Dictionary) -> int:
	var source := str(heal_def.get("source", "SP"))
	var source_val := _resolve_source_value(source, totals)
	if heal_def.has("coef"):
		return maxi(0, int(round(float(source_val) * float(heal_def.get("coef", 0.0)))))
	var base_value := float(heal_def.get("base_value", 0.0))
	var per_level := float(heal_def.get("per_level", 0.0))
	return maxi(0, int(round(base_value + per_level * float(level - 1))))

func _resolve_source_value(source: String, totals: Dictionary) -> int:
	match source:
		"SP":
			return maxi(1, int(totals.get("ATK", 1)))
		"WD":
			return maxi(1, int(totals.get("ATK", 1)))
		_:
			return maxi(1, int(totals.get("ATK", 1)))

func _log_skill_cast(skill_id: String, skill_name: String, damage: int) -> void:
	var last_ts := float(cast_log_recent.get(skill_id, -999.0))
	if battle_time - last_ts < 0.3:
		return
	cast_log_recent[skill_id] = battle_time
	EventBus.add_log("施放：%s 伤害%d %s %d/%d" % [skill_name, maxi(0, damage), I18nService.stat("QI"), qi, qi_max])

func _build_ai_context() -> Dictionary:
	var hp_now := maxi(0, int(player.get("hp", 0)))
	var hp_max := maxi(1, int(player.get("max_hp", hp_now)))
	var nearest_idx := _find_nearest_enemy_index()
	var dist_to_target := 9999.0
	var has_boss := false
	var has_elite := false
	if nearest_idx >= 0 and nearest_idx < enemies.size():
		var enemy: Dictionary = enemies[nearest_idx]
		var enemy_pos: Vector2 = enemy.get("pos", Vector2.ZERO)
		dist_to_target = enemy_pos.distance_to(player.get("pos", Vector2.ZERO))
	for enemy_any in enemies:
		if not (enemy_any is Dictionary):
			continue
		var enemy: Dictionary = enemy_any
		var kind := _normalize_enemy_kind(str(enemy.get("kind", "normal")))
		if kind == "boss":
			has_boss = true
		elif kind == "elite":
			has_elite = true
	return {
		"self_hp_pct": float(hp_now) / float(hp_max),
		"enemy_count": enemies.size(),
		"boss_present": has_boss,
		"elite_present": has_elite,
		"qi_pct": float(qi) / float(maxi(1, qi_max)),
		"time_in_fight": battle_time,
		"distance_to_target": dist_to_target,
		"self_has_debuff": false,
		"adds_present": enemies.size() >= 4,
	}

func _evaluate_expr(expr: String, context: Dictionary) -> bool:
	var text := _strip_outer_parens(expr.strip_edges())
	if text.is_empty():
		return true
	var or_parts := text.split("||")
	for or_part in or_parts:
		var and_ok := true
		var and_parts := str(or_part).split("&&")
		for and_part in and_parts:
			if not _evaluate_atom(str(and_part).strip_edges(), context):
				and_ok = false
				break
		if and_ok:
			return true
	return false

func _evaluate_atom(atom: String, context: Dictionary) -> bool:
	var token := _strip_outer_parens(atom.strip_edges())
	if token.is_empty():
		return true
	if token == "true":
		return true
	if token == "false":
		return false
	if token.begins_with("!"):
		return not _evaluate_atom(token.substr(1), context)

	for op in [">=", "<=", "==", ">", "<"]:
		var idx := token.find(op)
		if idx <= 0:
			continue
		var lhs := token.substr(0, idx).strip_edges()
		var rhs := token.substr(idx + op.length(), token.length()).strip_edges()
		var lhs_num := _to_number(context.get(lhs, 0.0))
		var rhs_num := rhs.to_float()
		match op:
			">=":
				return lhs_num >= rhs_num
			"<=":
				return lhs_num <= rhs_num
			"==":
				return is_equal_approx(lhs_num, rhs_num)
			">":
				return lhs_num > rhs_num
			"<":
				return lhs_num < rhs_num
	return bool(context.get(token, false))

func _to_number(v: Variant) -> float:
	if v is bool:
		return 1.0 if bool(v) else 0.0
	if v is int:
		return float(v)
	if v is float:
		return v
	return str(v).to_float()

func _strip_outer_parens(text: String) -> String:
	var out := text.strip_edges()
	while out.begins_with("(") and out.ends_with(")") and out.length() >= 2:
		out = out.substr(1, out.length() - 2).strip_edges()
	return out

func _try_spawn_drop(enemy: Dictionary, _death_pos: Vector2, kill_drop_bonus_percent: int = 0) -> void:
	var drops_any = enemy.get("drops", [])
	if not (drops_any is Array):
		return
	var drops: Array = drops_any
	var loot_bonus := _get_loot_bonus_percent()
	for row_any in drops:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		if not bool(row.get("is_enabled", true)):
			continue
		var item_id := str(row.get("item_id", "")).strip_edges()
		if item_id.is_empty():
			continue
		var count_min := maxi(1, int(row.get("count_min", 1)))
		var count_max := maxi(count_min, int(row.get("count_max", count_min)))
		var chance := 1.0
		if row.has("drop_rate") and row.get("drop_rate", null) != null:
			chance = clampf(float(row.get("drop_rate", 1.0)), 0.0, 1.0)
		chance = _apply_loot_bonus(chance, loot_bonus)
		chance = _apply_kill_drop_bonus(chance, kill_drop_bonus_percent)
		if chance <= 0.0 or randf() > chance:
			continue
		var count := count_min
		if count_max > count_min:
			count = randi_range(count_min, count_max)
		_grant_item_drop(item_id, count)

func _grant_item_drop(item_id: String, count: int = 1) -> void:
	if item_id.is_empty():
		return
	var grant_count := maxi(1, count)
	InventoryModel.add_item(item_id, grant_count, "online")
	TaskService.on_loot(grant_count)
	var rarity := _item_rarity(item_id, "white")
	var item_name := _item_name(item_id)
	var label := item_name if not item_name.is_empty() else item_id
	EventBus.add_log("%s 掉落：%s x%d" % [_rarity_tag(rarity), label, grant_count])

func _item_rarity(item_id: String, fallback_rarity: String = "white") -> String:
	if item_id.is_empty():
		return fallback_rarity
	var cfg: Dictionary = ConfigService.get_cfg()
	var items_db_any = cfg.get("items_db", {})
	if not (items_db_any is Dictionary):
		return fallback_rarity
	var items_any = (items_db_any as Dictionary).get("items", [])
	if not (items_any is Array):
		return fallback_rarity
	for item_any in items_any:
		if not (item_any is Dictionary):
			continue
		var item_def: Dictionary = item_any
		if str(item_def.get("id", "")) != item_id:
			continue
		var rarity := str(item_def.get("rarity", fallback_rarity))
		return rarity if not rarity.is_empty() else fallback_rarity
	return fallback_rarity

func _item_name(item_id: String) -> String:
	if item_id.is_empty():
		return ""
	var cfg: Dictionary = ConfigService.get_cfg()
	var items_db_any = cfg.get("items_db", {})
	if not (items_db_any is Dictionary):
		return item_id
	var items_any = (items_db_any as Dictionary).get("items", [])
	if not (items_any is Array):
		return item_id
	for item_any in items_any:
		if not (item_any is Dictionary):
			continue
		var item_def: Dictionary = item_any
		if str(item_def.get("id", "")).strip_edges() != item_id:
			continue
		var name := str(item_def.get("name", item_id)).strip_edges()
		return name if not name.is_empty() else item_id
	return item_id

func _update_loot(delta: float) -> void:
	if loots.is_empty():
		return
	var player_pos: Vector2 = player.get("pos", Vector2.ZERO)
	for i in range(loots.size() - 1, -1, -1):
		var loot: Dictionary = loots[i]
		var loot_pos: Vector2 = loot.get("pos", Vector2.ZERO)
		if player_pos.distance_to(loot_pos) <= LOOT_PICKUP_RADIUS:
			var picked_up := false
			var item_id := str(loot.get("item_id", ""))
			if not item_id.is_empty():
				InventoryModel.add_item(item_id, 1, "online")
				EventBus.add_log("拾取：%s" % _item_name(item_id))
				picked_up = true
			if picked_up:
				TaskService.on_loot(1)
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
	player["hp"] = int(player.get("max_hp", 10))
	kills = 0
	normal_kill_counter = 0
	elite_progress = 0
	boss_progress = 0
	special_present = ""
	enemies.clear()
	loots.clear()
	events.clear()
	attack_timer = 0.0
	hit_log_cd = 0.0
	player_last_hit_at = battle_time
	hp_regen_accum = 0.0
	auto_seek_moving = false
	manual_seek_off_fired = false
	gcd_left = 0.0
	cd_left.clear()
	cast_log_recent.clear()
	player_shield = 0
	player_shield_left = 0.0
	qi = qi_max
	qi_regen_accum = 0.0
	spawn_rt["alive_count"] = 0
	spawn_rt["next_spawn_at"] = battle_time
	cycle_started_at = battle_time
	_log_loot_bonus_info()

func _player_regen(delta: float) -> void:
	var hp := int(player.get("hp", 0))
	var max_hp := int(player.get("max_hp", hp))
	if hp <= 0:
		return
	if hp >= max_hp:
		return
	if battle_time - player_last_hit_at < 1.2:
		return
	var attrs_any = ProgressModel.attrs
	var attrs: Dictionary = attrs_any if attrs_any is Dictionary else {}
	var physique := maxi(0, int(attrs.get("physique", 0)))
	var regen_rate := 0.35 + float(physique) * 0.02
	hp_regen_accum += regen_rate * delta
	var add := int(floor(hp_regen_accum))
	if add <= 0:
		return
	hp_regen_accum -= float(add)
	player["hp"] = mini(max_hp, hp + add)

func _heal_on_kill(kind: String) -> void:
	var max_hp := int(player.get("max_hp", 10))
	var hp := int(player.get("hp", 10))
	var add := 1
	if kind == "elite":
		add = 2
	elif kind == "boss":
		add = 4
	player["hp"] = mini(max_hp, hp + add)

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
	var best_priority := 99
	var best_dist_sq := INF
	for i in range(enemies.size()):
		var enemy: Dictionary = enemies[i]
		var priority := _enemy_priority(enemy)
		var enemy_pos: Vector2 = enemy.get("pos", Vector2.ZERO)
		var dist_sq := player_pos.distance_squared_to(enemy_pos)
		if priority < best_priority or (priority == best_priority and dist_sq < best_dist_sq):
			best_priority = priority
			best_dist_sq = dist_sq
			best_idx = i
	return best_idx

func _rarity_tag(rarity: String) -> String:
	match rarity:
		"blue":
			return "[蓝]"
		"gold":
			return "[金]"
		"purple":
			return "[紫]"
		"orange":
			return "[橙]"
		_:
			return "[白]"

func _get_loot_bonus_percent() -> int:
	var stats: Dictionary = EquipmentModel.get_total_stats()
	return maxi(0, int(stats.get("LOOT_BONUS_PERCENT", stats.get("DROP", 0))))

func _apply_loot_bonus(chance: float, loot_bonus: int) -> float:
	if loot_bonus <= 0:
		return clampf(chance, 0.0, 1.0)
	return clampf(chance * (1.0 + float(loot_bonus) / 100.0), 0.0, 1.0)

func _apply_kill_drop_bonus(effective_drop: float, kill_drop_bonus_percent: int) -> float:
	if kill_drop_bonus_percent > 0:
		effective_drop *= (1.0 + float(kill_drop_bonus_percent) / 100.0)
	return clampf(effective_drop, 0.0, 1.0)

func _normalize_enemy_kind(kind: String) -> String:
	var k := kind.strip_edges().to_lower()
	if k == "elite" or k == "boss":
		return k
	return "normal"

func _enemy_priority(enemy: Dictionary) -> int:
	var kind := _normalize_enemy_kind(str(enemy.get("kind", "normal")))
	if kind == "boss":
		return 0
	if kind == "elite":
		return 1
	return 2

func _has_special_enemy_alive() -> bool:
	for enemy_any in enemies:
		if not (enemy_any is Dictionary):
			continue
		var enemy: Dictionary = enemy_any
		var kind := _normalize_enemy_kind(str(enemy.get("kind", "normal")))
		if kind == "elite" or kind == "boss":
			return true
	return false

func _log_loot_bonus_info() -> void:
	var loot_bonus: int = _get_loot_bonus_percent()
	EventBus.add_log("运势掉落加成：+%d%%（影响怪物掉落概率）" % loot_bonus)

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
