extends Node

const BASE_URL := "http://127.0.0.1:8001/bundles/latest"
const ACTIVE_ROOT := "user://remote"
const ACTIVE_DIR := "user://remote/active"
const TMP_DIR := "user://remote_tmp"
const ACTIVE_MANIFEST := "user://remote/active_manifest.json"

const FILE_KEY_ORDER := [
	"stages",
	"items",
	"equip_templates",
	"equipment_sets",
	"monsters",
	"skills_catalog",
	"battle_defaults",
]

const OPTIONAL_FILE_KEYS := [
	"star_rules",
	"forge_rules",
]

const FILE_KEY_TO_NAME := {
	"stages": "stages_v1.json",
	"items": "items.json",
	"equip_templates": "equip_templates.json",
	"equipment_sets": "equipment_sets.json",
	"monsters": "monsters.json",
	"skills_catalog": "skills_catalog.json",
	"battle_defaults": "battle_defaults.json",
	"star_rules": "star_rules_v1.json",
	"forge_rules": "forge_rules_v1.json",
}

func get_stages_json_text() -> String:
	return get_active_text("stages_v1.json", "res://data/stages_v1.json")

func get_items_json_text() -> String:
	return get_active_text("items.json", "res://data/items.json")

func get_equip_templates_text() -> String:
	return get_active_text("equip_templates.json", "res://data/equip_templates.json")

func get_equipment_sets_text() -> String:
	return get_active_text("equipment_sets.json", "res://data/equipment_sets.json")

func get_monsters_json_text() -> String:
	return get_active_text("monsters.json", "res://data/monsters.json")

func get_skills_catalog_text() -> String:
	return get_active_text("skills_catalog.json", "res://data/skills_catalog.json")

func get_battle_defaults_text() -> String:
	return get_active_text("battle_defaults.json", "res://data/battle_defaults.json")

func get_star_rules_text() -> String:
	return get_active_text("star_rules_v1.json", "res://data/star_rules_v1.json")

func get_forge_rules_text() -> String:
	return get_active_text("forge_rules_v1.json", "res://data/forge_rules_v1.json")

func get_active_text(filename: String, fallback_res_path: String) -> String:
	var active_path := "%s/%s" % [ACTIVE_DIR, filename]
	if FileAccess.file_exists(active_path):
		return FileAccess.get_file_as_string(active_path)
	if not fallback_res_path.is_empty() and FileAccess.file_exists(fallback_res_path):
		return FileAccess.get_file_as_string(fallback_res_path)
	return ""

func get_active_versions() -> Dictionary:
	var out := {
		"stages": 0,
		"items": 0,
		"equip_templates": 0,
		"equipment_sets": 0,
		"monsters": 0,
		"skills_catalog": 0,
		"battle_defaults": 0,
		"star_rules": 0,
		"forge_rules": 0,
	}
	if not FileAccess.file_exists(ACTIVE_MANIFEST):
		return out
	var manifest_text := FileAccess.get_file_as_string(ACTIVE_MANIFEST)
	var parsed_any: Variant = JSON.parse_string(manifest_text)
	if not (parsed_any is Dictionary):
		return out
	var parsed: Dictionary = parsed_any
	var files_any = parsed.get("files", [])
	if not (files_any is Array):
		return out
	for row_any in files_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		var key := str(row.get("key", ""))
		if not out.has(key):
			continue
		out[key] = maxi(0, int(row.get("version", 0)))
	return out

func get_active_bundle_id() -> String:
	if not FileAccess.file_exists(ACTIVE_MANIFEST):
		return "内置"
	var manifest_text := FileAccess.get_file_as_string(ACTIVE_MANIFEST)
	var parsed_any: Variant = JSON.parse_string(manifest_text)
	if not (parsed_any is Dictionary):
		return "内置"
	var parsed: Dictionary = parsed_any
	var meta_any: Variant = parsed.get("meta", {})
	if not (meta_any is Dictionary):
		return "内置"
	var meta: Dictionary = meta_any
	var bundle_id := str(meta.get("bundle_id", "")).strip_edges()
	if bundle_id.is_empty():
		return "内置"
	return bundle_id

func get_meta_version_from_text(text: String) -> int:
	if text.strip_edges().is_empty():
		return 0
	var parsed: Variant = JSON.parse_string(text)
	if not (parsed is Dictionary):
		return 0
	var root: Dictionary = parsed
	var meta_any: Variant = root.get("meta", null)
	if not (meta_any is Dictionary):
		return 0
	var meta: Dictionary = meta_any
	return maxi(0, int(meta.get("version", 0)))

func validate_stages_json(text: String) -> Dictionary:
	if text.strip_edges().is_empty():
		return {"ok": false, "reason": "配置内容为空"}

	var parsed: Variant = JSON.parse_string(text)
	if not (parsed is Dictionary):
		return {"ok": false, "reason": "JSON根节点必须是对象"}

	var root: Dictionary = parsed
	var meta_check := _validate_optional_meta(root, "stages")
	if not bool(meta_check.get("ok", false)):
		return meta_check
	var stages_any: Variant = root.get("stages", null)
	if not (stages_any is Array):
		return {"ok": false, "reason": "缺少 stages 数组"}
	var stages: Array = stages_any
	if stages.is_empty():
		return {"ok": false, "reason": "stages 不能为空"}

	for i in range(stages.size()):
		var stage_any: Variant = stages[i]
		if not (stage_any is Dictionary):
			return {"ok": false, "reason": "stage[%d] 不是对象" % i}
		var stage: Dictionary = stage_any
		var basic_required := [
			"id",
			"name",
			"unlock_min_level",
			"elite_every_kills",
			"boss_every_kills",
			"spawn_patch",
		]
		for key in basic_required:
			if not stage.has(key):
				return {"ok": false, "reason": "stage[%d] 缺少字段 %s" % [i, key]}

		if str(stage.get("id", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "stage[%d].id 不能为空" % i}
		if str(stage.get("name", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "stage[%d].name 不能为空" % i}

		var unlock_min_level := int(stage.get("unlock_min_level", 0))
		var elite_every_kills := int(stage.get("elite_every_kills", 0))
		var boss_every_kills := int(stage.get("boss_every_kills", 0))
		if unlock_min_level < 1:
			return {"ok": false, "reason": "stage[%d].unlock_min_level 必须 >= 1" % i}
		if elite_every_kills < 1:
			return {"ok": false, "reason": "stage[%d].elite_every_kills 必须 >= 1" % i}
		if boss_every_kills < 1:
			return {"ok": false, "reason": "stage[%d].boss_every_kills 必须 >= 1" % i}

		if stage.has("monsters"):
			var monsters_any: Variant = stage.get("monsters", {})
			if not (monsters_any is Dictionary):
				return {"ok": false, "reason": "stage[%d].monsters 必须是对象" % i}
			var monsters_map: Dictionary = monsters_any
			var has_legacy := false
			var has_pool := false

			for mk in ["normal", "elite", "boss"]:
				if not monsters_map.has(mk):
					continue
				var legacy_id := str(monsters_map.get(mk, "")).strip_edges()
				if legacy_id.is_empty():
					return {"ok": false, "reason": "stage[%d].monsters.%s 不能为空" % [i, mk]}
				has_legacy = true

			for pool_key in ["normal_pool", "elite_pool", "boss_pool"]:
				if monsters_map.has(pool_key):
					has_pool = true

			if has_pool:
				for pool_key in ["normal_pool", "elite_pool", "boss_pool"]:
					if not monsters_map.has(pool_key):
						return {"ok": false, "reason": "stage[%d].monsters.%s 缺失" % [i, pool_key]}
					var pool_any: Variant = monsters_map.get(pool_key, [])
					if not (pool_any is Array):
						return {"ok": false, "reason": "stage[%d].monsters.%s 必须是数组" % [i, pool_key]}
					var pool: Array = pool_any
					if pool.is_empty():
						return {"ok": false, "reason": "stage[%d].monsters.%s 不能为空" % [i, pool_key]}
					var sum_w := 0
					for j in range(pool.size()):
						var row_any: Variant = pool[j]
						if not (row_any is Dictionary):
							return {"ok": false, "reason": "stage[%d].monsters.%s[%d] 必须是对象" % [i, pool_key, j]}
						var row: Dictionary = row_any
						var monster_id := str(row.get("id", "")).strip_edges()
						if monster_id.is_empty():
							return {"ok": false, "reason": "stage[%d].monsters.%s[%d].id 不能为空" % [i, pool_key, j]}
						var w := int(row.get("w", -1))
						if w < 0:
							return {"ok": false, "reason": "stage[%d].monsters.%s[%d].w 不能为负数" % [i, pool_key, j]}
						sum_w += w
					if sum_w <= 0:
						return {"ok": false, "reason": "stage[%d].monsters.%s 权重总和必须 > 0" % [i, pool_key]}
			elif has_legacy:
				for mk in ["normal", "elite", "boss"]:
					var legacy_id := str(monsters_map.get(mk, "")).strip_edges()
					if legacy_id.is_empty():
						return {"ok": false, "reason": "stage[%d].monsters.%s 不能为空" % [i, mk]}

			if not has_legacy and not has_pool and not monsters_map.is_empty():
				return {"ok": false, "reason": "stage[%d].monsters 结构无效" % i}

		var spawn_any: Variant = stage.get("spawn_patch", {})
		if not (spawn_any is Dictionary):
			return {"ok": false, "reason": "stage[%d].spawn_patch 必须是对象" % i}
		var spawn_patch: Dictionary = spawn_any
		for key in ["respawn_s", "max_alive", "spawn_radius"]:
			if not spawn_patch.has(key):
				return {"ok": false, "reason": "stage[%d].spawn_patch 缺少字段 %s" % [i, key]}
		var respawn_s := float(spawn_patch.get("respawn_s", -1.0))
		var max_alive := int(spawn_patch.get("max_alive", 0))
		var spawn_radius := float(spawn_patch.get("spawn_radius", 0.0))
		if respawn_s <= 0.0:
			return {"ok": false, "reason": "stage[%d].spawn_patch.respawn_s 必须 > 0" % i}
		if max_alive < 1:
			return {"ok": false, "reason": "stage[%d].spawn_patch.max_alive 必须 >= 1" % i}
		if spawn_radius <= 0.0:
			return {"ok": false, "reason": "stage[%d].spawn_patch.spawn_radius 必须 > 0" % i}

		if stage.has("drops_patch"):
			var drops_any: Variant = stage.get("drops_patch", {})
			if not (drops_any is Dictionary):
				return {"ok": false, "reason": "stage[%d].drops_patch 必须是对象" % i}
			var drops_patch: Dictionary = drops_any
			for key in ["drop_chance", "rarity_weights", "items_by_rarity", "special"]:
				if not drops_patch.has(key):
					return {"ok": false, "reason": "stage[%d].drops_patch 缺少字段 %s" % [i, key]}

			var drop_chance := float(drops_patch.get("drop_chance", -1.0))
			if drop_chance < 0.0 or drop_chance > 1.0:
				return {"ok": false, "reason": "stage[%d].drops_patch.drop_chance 需在0~1" % i}

			var rarity_any: Variant = drops_patch.get("rarity_weights", {})
			if not (rarity_any is Dictionary):
				return {"ok": false, "reason": "stage[%d].drops_patch.rarity_weights 必须是对象" % i}
			var rarity_weights: Dictionary = rarity_any
			var white_w := int(rarity_weights.get("white", -1))
			var blue_w := int(rarity_weights.get("blue", -1))
			var gold_w := int(rarity_weights.get("gold", -1))
			if white_w < 0 or blue_w < 0 or gold_w < 0:
				return {"ok": false, "reason": "stage[%d].rarity_weights 不能为负数" % i}
			if white_w + blue_w + gold_w <= 0:
				return {"ok": false, "reason": "stage[%d].rarity_weights 总和必须 > 0" % i}

			var items_any: Variant = drops_patch.get("items_by_rarity", {})
			if not (items_any is Dictionary):
				return {"ok": false, "reason": "stage[%d].drops_patch.items_by_rarity 必须是对象" % i}
			var items_by_rarity: Dictionary = items_any
			var white_items_any: Variant = items_by_rarity.get("white", [])
			if not (white_items_any is Array) or (white_items_any as Array).is_empty():
				return {"ok": false, "reason": "stage[%d].items_by_rarity.white 至少1个" % i}
			for key in ["blue", "gold"]:
				var arr_any: Variant = items_by_rarity.get(key, [])
				if not (arr_any is Array):
					return {"ok": false, "reason": "stage[%d].items_by_rarity.%s 必须是数组" % [i, key]}

			var special_any: Variant = drops_patch.get("special", {})
			if not (special_any is Dictionary):
				return {"ok": false, "reason": "stage[%d].drops_patch.special 必须是对象" % i}
			var special: Dictionary = special_any
			for kind in ["normal", "elite", "boss"]:
				var def_any: Variant = special.get(kind, {})
				if not (def_any is Dictionary):
					return {"ok": false, "reason": "stage[%d].special.%s 必须是对象" % [i, kind]}
				var def: Dictionary = def_any
				if kind == "normal":
					if not _check_probability(def, "extra_gem_chance"):
						return {"ok": false, "reason": "stage[%d].special.normal.extra_gem_chance 需在0~1" % i}
				elif kind == "elite":
					if not _check_probability(def, "punch_stone_chance"):
						return {"ok": false, "reason": "stage[%d].special.elite.punch_stone_chance 需在0~1" % i}
					if not _check_probability(def, "extra_gem_chance"):
						return {"ok": false, "reason": "stage[%d].special.elite.extra_gem_chance 需在0~1" % i}
				else:
					if str(def.get("core_guarantee", "")).strip_edges().is_empty():
						return {"ok": false, "reason": "stage[%d].special.boss.core_guarantee 必填" % i}
					if not _check_probability(def, "punch_stone_chance"):
						return {"ok": false, "reason": "stage[%d].special.boss.punch_stone_chance 需在0~1" % i}
					if not _check_probability(def, "extra_gem_chance"):
						return {"ok": false, "reason": "stage[%d].special.boss.extra_gem_chance 需在0~1" % i}

		if stage.has("difficulties"):
			var diff_check := _validate_stage_difficulties(stage.get("difficulties", []), i)
			if not bool(diff_check.get("ok", false)):
				return diff_check

	return {"ok": true}

func _validate_stage_difficulties(difficulties_any: Variant, stage_index: int) -> Dictionary:
	if not (difficulties_any is Array):
		return {"ok": false, "reason": "stage[%d].difficulties 必须是数组" % stage_index}
	var difficulties: Array = difficulties_any
	if difficulties.is_empty():
		return {"ok": false, "reason": "stage[%d].difficulties 不能为空" % stage_index}
	for j in range(difficulties.size()):
		var diff_any: Variant = difficulties[j]
		if not (diff_any is Dictionary):
			return {"ok": false, "reason": "stage[%d].difficulties[%d] 必须是对象" % [stage_index, j]}
		var diff: Dictionary = diff_any
		if str(diff.get("name", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "stage[%d].difficulties[%d].name 不能为空" % [stage_index, j]}
		if int(diff.get("recommend_score", 0)) < 0:
			return {"ok": false, "reason": "stage[%d].difficulties[%d].recommend_score 不能为负数" % [stage_index, j]}

		var unlock_any: Variant = diff.get("unlock", {})
		if unlock_any is Dictionary:
			var unlock: Dictionary = unlock_any
			if int(unlock.get("boss_kills_required", 0)) < 0:
				return {"ok": false, "reason": "stage[%d].difficulties[%d].unlock.boss_kills_required 不能为负数" % [stage_index, j]}
			var material_any: Variant = unlock.get("material_cost", {})
			if not (material_any is Dictionary):
				return {"ok": false, "reason": "stage[%d].difficulties[%d].unlock.material_cost 必须是对象" % [stage_index, j]}
			var material_cost: Dictionary = material_any
			for item_id_any in material_cost.keys():
				var item_id := str(item_id_any).strip_edges()
				var cnt := int(material_cost.get(item_id_any, 0))
				if item_id.is_empty():
					return {"ok": false, "reason": "stage[%d].difficulties[%d].unlock.material_cost 存在空物品ID" % [stage_index, j]}
				if cnt < 0:
					return {"ok": false, "reason": "stage[%d].difficulties[%d].unlock.material_cost.%s 不能为负数" % [stage_index, j, item_id]}
			if unlock.has("reward"):
				var reward_any: Variant = unlock.get("reward", {})
				if not (reward_any is Dictionary):
					push_warning("RemoteConfigService: 忽略非法 reward，stage[%d] difficulties[%d]" % [stage_index, j])
				else:
					var reward: Dictionary = reward_any
					if int(reward.get("gold", 0)) < 0 or int(reward.get("skill_points", 0)) < 0:
						push_warning("RemoteConfigService: 忽略非法 reward 数值，stage[%d] difficulties[%d]" % [stage_index, j])
					var reward_items_any: Variant = reward.get("items", {})
					if reward_items_any is Dictionary:
						var reward_items: Dictionary = reward_items_any
						for rid_any in reward_items.keys():
							var rid := str(rid_any).strip_edges()
							var rcnt := int(reward_items.get(rid_any, 0))
							if rid.is_empty() or rcnt < 0:
								push_warning("RemoteConfigService: 忽略非法 reward.items，stage[%d] difficulties[%d]" % [stage_index, j])
								break
		if diff.has("first_clear_reward"):
			var first_reward_any: Variant = diff.get("first_clear_reward", {})
			if not (first_reward_any is Dictionary):
				push_warning("RemoteConfigService: 忽略非法 first_clear_reward，stage[%d] difficulties[%d]" % [stage_index, j])
			else:
				var first_reward: Dictionary = first_reward_any
				if int(first_reward.get("gold", 0)) < 0 or int(first_reward.get("skill_points", 0)) < 0:
					push_warning("RemoteConfigService: 忽略非法 first_clear_reward 数值，stage[%d] difficulties[%d]" % [stage_index, j])
				var first_items_any: Variant = first_reward.get("items", {})
				if first_items_any is Dictionary:
					var first_items: Dictionary = first_items_any
					for fid_any in first_items.keys():
						var fid := str(fid_any).strip_edges()
						var fcnt := int(first_items.get(fid_any, 0))
						if fid.is_empty() or fcnt < 0:
							push_warning("RemoteConfigService: 忽略非法 first_clear_reward.items，stage[%d] difficulties[%d]" % [stage_index, j])
							break

		var mult_any: Variant = diff.get("monster_mult", {})
		if mult_any is Dictionary:
			var mult: Dictionary = mult_any
			for key in ["hp", "atk", "def"]:
				var v := float(mult.get(key, 1.0))
				if v <= 0.0:
					return {"ok": false, "reason": "stage[%d].difficulties[%d].monster_mult.%s 必须 > 0" % [stage_index, j, key]}
		elif diff.has("monster_mult"):
			return {"ok": false, "reason": "stage[%d].difficulties[%d].monster_mult 必须是对象" % [stage_index, j]}

		if diff.has("drops_override") and not (diff.get("drops_override", {}) is Dictionary):
			return {"ok": false, "reason": "stage[%d].difficulties[%d].drops_override 必须是对象" % [stage_index, j]}
		if diff.has("drops_override"):
			var drops_override: Dictionary = diff.get("drops_override", {})
			if drops_override.has("drop_chance"):
				var drop_chance := float(drops_override.get("drop_chance", -1.0))
				if drop_chance < 0.0 or drop_chance > 1.0:
					return {"ok": false, "reason": "stage[%d].difficulties[%d].drops_override.drop_chance 需在0~1" % [stage_index, j]}
			if drops_override.has("rarity_weights"):
				var rarity_any = drops_override.get("rarity_weights", {})
				if not (rarity_any is Dictionary):
					return {"ok": false, "reason": "stage[%d].difficulties[%d].drops_override.rarity_weights 必须是对象" % [stage_index, j]}
				var rarity_weights: Dictionary = rarity_any
				var white_w := int(rarity_weights.get("white", 0))
				var blue_w := int(rarity_weights.get("blue", 0))
				var gold_w := int(rarity_weights.get("gold", 0))
				if white_w < 0 or blue_w < 0 or gold_w < 0:
					return {"ok": false, "reason": "stage[%d].difficulties[%d].drops_override.rarity_weights 不能为负数" % [stage_index, j]}
				if white_w + blue_w + gold_w <= 0:
					return {"ok": false, "reason": "stage[%d].difficulties[%d].drops_override.rarity_weights 总和必须 > 0" % [stage_index, j]}
			if drops_override.has("items_by_rarity"):
				var items_any = drops_override.get("items_by_rarity", {})
				if not (items_any is Dictionary):
					push_warning("RemoteConfigService: 忽略非法 drops_override.items_by_rarity，stage[%d] difficulties[%d]" % [stage_index, j])
				else:
					var items_by_rarity: Dictionary = items_any
					for rarity_any in items_by_rarity.keys():
						var rarity := str(rarity_any).strip_edges()
						if rarity.is_empty():
							continue
						var arr_any = items_by_rarity.get(rarity_any, [])
						if not (arr_any is Array):
							push_warning("RemoteConfigService: 忽略非法 drops_override.items_by_rarity.%s，stage[%d] difficulties[%d]" % [rarity, stage_index, j])
							continue
						var arr: Array = arr_any
						# NOTE: 空数组表示“继承上层掉落池”，是合法输入，不是错误。
						if arr.is_empty():
							continue
						var valid := true
						for item_any in arr:
							if str(item_any).strip_edges().is_empty():
								valid = false
								break
						if not valid:
							push_warning("RemoteConfigService: 忽略包含空物品ID的 drops_override.items_by_rarity.%s，stage[%d] difficulties[%d]" % [rarity, stage_index, j])
			if drops_override.has("special"):
				var special_any = drops_override.get("special", {})
				if not (special_any is Dictionary):
					return {"ok": false, "reason": "stage[%d].difficulties[%d].drops_override.special 必须是对象" % [stage_index, j]}
				var special: Dictionary = special_any
				for kind in ["normal", "elite", "boss"]:
					if not special.has(kind):
						continue
					var def_any = special.get(kind, {})
					if not (def_any is Dictionary):
						return {"ok": false, "reason": "stage[%d].difficulties[%d].drops_override.special.%s 必须是对象" % [stage_index, j, kind]}
					var def: Dictionary = def_any
					if kind == "normal":
						if not _check_probability(def, "extra_gem_chance"):
							return {"ok": false, "reason": "stage[%d].difficulties[%d].normal.extra_gem_chance 需在0~1" % [stage_index, j]}
					elif kind == "elite":
						if not _check_probability(def, "punch_stone_chance"):
							return {"ok": false, "reason": "stage[%d].difficulties[%d].elite.punch_stone_chance 需在0~1" % [stage_index, j]}
						if not _check_probability(def, "extra_gem_chance"):
							return {"ok": false, "reason": "stage[%d].difficulties[%d].elite.extra_gem_chance 需在0~1" % [stage_index, j]}
					else:
						if def.has("punch_stone_chance") and not _check_probability(def, "punch_stone_chance"):
							return {"ok": false, "reason": "stage[%d].difficulties[%d].boss.punch_stone_chance 需在0~1" % [stage_index, j]}
						if def.has("extra_gem_chance") and not _check_probability(def, "extra_gem_chance"):
							return {"ok": false, "reason": "stage[%d].difficulties[%d].boss.extra_gem_chance 需在0~1" % [stage_index, j]}
	return {"ok": true}

func validate_monsters_json(text: String) -> Dictionary:
	if text.strip_edges().is_empty():
		return {"ok": false, "reason": "怪物配置内容为空"}

	var parsed: Variant = JSON.parse_string(text)
	if not (parsed is Dictionary):
		return {"ok": false, "reason": "怪物JSON根节点必须是对象"}
	var root: Dictionary = parsed
	var meta_check := _validate_optional_meta(root, "monsters")
	if not bool(meta_check.get("ok", false)):
		return meta_check

	var monsters_any: Variant = root.get("monsters", null)
	if not (monsters_any is Array):
		return {"ok": false, "reason": "缺少 monsters 数组"}
	var monsters: Array = monsters_any
	if monsters.is_empty():
		return {"ok": false, "reason": "monsters 不能为空"}

	var required := [
		"id",
		"name",
		"kind",
		"hp",
		"atk",
		"def",
		"speed",
		"radius",
		"aggro_range",
		"attack_interval",
		"attack_range",
		"exp",
		"drop_bonus_percent",
		"dex_gold",
	]
	for i in range(monsters.size()):
		var mon_any: Variant = monsters[i]
		if not (mon_any is Dictionary):
			return {"ok": false, "reason": "monster[%d] 不是对象" % i}
		var mon: Dictionary = mon_any
		for key in required:
			if not mon.has(key):
				return {"ok": false, "reason": "monster[%d] 缺少字段 %s" % [i, key]}

		var mon_id := str(mon.get("id", "")).strip_edges()
		var mon_name := str(mon.get("name", "")).strip_edges()
		if mon_id.is_empty() or mon_name.is_empty():
			return {"ok": false, "reason": "monster[%d] id/name 不能为空" % i}

		var kind := str(mon.get("kind", "")).strip_edges().to_lower()
		if kind != "normal" and kind != "elite" and kind != "boss":
			return {"ok": false, "reason": "monster[%d].kind 非法" % i}

		if int(mon.get("hp", 0)) < 1:
			return {"ok": false, "reason": "monster[%d].hp 必须 >= 1" % i}
		var attack_interval := float(mon.get("attack_interval", 0.0))
		if attack_interval < 0.2 or attack_interval > 10.0:
			return {"ok": false, "reason": "monster[%d].attack_interval 需在0.2~10" % i}

	return {"ok": true}

func validate_items_json(text: String) -> Dictionary:
	if text.strip_edges().is_empty():
		return {"ok": false, "reason": "物品配置内容为空"}

	var parsed: Variant = JSON.parse_string(text)
	if not (parsed is Dictionary):
		return {"ok": false, "reason": "物品JSON根节点必须是对象"}
	var root: Dictionary = parsed
	var meta_check := _validate_optional_meta(root, "items")
	if not bool(meta_check.get("ok", false)):
		return meta_check

	var rows_any: Variant = root.get("items", null)
	if not (rows_any is Array):
		return {"ok": false, "reason": "缺少 items 数组"}
	var rows: Array = rows_any
	if rows.is_empty():
		return {"ok": false, "reason": "items 不能为空"}

	for i in range(rows.size()):
		var row_any: Variant = rows[i]
		if not (row_any is Dictionary):
			return {"ok": false, "reason": "items[%d] 不是对象" % i}
		var row: Dictionary = row_any
		var item_id := str(row.get("id", "")).strip_edges()
		var item_name := str(row.get("name", "")).strip_edges()
		var rarity := str(row.get("rarity", "")).strip_edges()
		if item_id.is_empty() or item_name.is_empty():
			return {"ok": false, "reason": "items[%d] id/name 不能为空" % i}
		if rarity != "white" and rarity != "blue" and rarity != "gold":
			return {"ok": false, "reason": "items[%d].rarity 非法" % i}

	return {"ok": true}

func validate_equip_templates_json(text: String) -> Dictionary:
	if text.strip_edges().is_empty():
		return {"ok": false, "reason": "装备模板内容为空"}

	var parsed: Variant = JSON.parse_string(text)
	if not (parsed is Dictionary):
		return {"ok": false, "reason": "装备模板JSON根节点必须是对象"}
	var root: Dictionary = parsed
	var meta_check := _validate_optional_meta(root, "equip_templates")
	if not bool(meta_check.get("ok", false)):
		return meta_check

	var rows_any: Variant = root.get("equip_templates", null)
	if not (rows_any is Array):
		return {"ok": false, "reason": "缺少 equip_templates 数组"}
	var rows: Array = rows_any
	if rows.is_empty():
		return {"ok": false, "reason": "equip_templates 不能为空"}

	for i in range(rows.size()):
		var row_any: Variant = rows[i]
		if not (row_any is Dictionary):
			return {"ok": false, "reason": "equip_templates[%d] 不是对象" % i}
		var row: Dictionary = row_any
		var template_id := str(row.get("id", "")).strip_edges()
		var name := str(row.get("name", "")).strip_edges()
		var slot := str(row.get("slot", "")).strip_edges()
		var rarity := str(row.get("rarity", "")).strip_edges()
		var main_stat := str(row.get("main_stat", "")).strip_edges()
		if template_id.is_empty() or name.is_empty():
			return {"ok": false, "reason": "equip_templates[%d] id/name 不能为空" % i}
		if slot.is_empty() or rarity.is_empty() or main_stat.is_empty():
			return {"ok": false, "reason": "equip_templates[%d] 缺少核心字段" % i}
		if int(row.get("main_min", -1)) < 0 or int(row.get("main_max", -1)) < 0:
			return {"ok": false, "reason": "equip_templates[%d] main_min/main_max 非法" % i}
		if row.has("base_stats"):
			var base_stats_any = row.get("base_stats", {})
			if not (base_stats_any is Dictionary):
				return {"ok": false, "reason": "equip_templates[%d].base_stats 必须是对象" % i}
			var base_stats: Dictionary = base_stats_any
			for stat_any in base_stats.keys():
				if int(base_stats.get(stat_any, 0)) < 0:
					return {"ok": false, "reason": "equip_templates[%d].base_stats.%s 不能为负数" % [i, str(stat_any)]}
		if row.has("star_growth"):
			var growth_any = row.get("star_growth", {})
			if not (growth_any is Dictionary):
				return {"ok": false, "reason": "equip_templates[%d].star_growth 必须是对象" % i}
			var growth: Dictionary = growth_any
			for stat_any in growth.keys():
				if int(growth.get(stat_any, 0)) < 0:
					return {"ok": false, "reason": "equip_templates[%d].star_growth.%s 不能为负数" % [i, str(stat_any)]}
		if row.has("star_max") and int(row.get("star_max", 0)) < 0:
			return {"ok": false, "reason": "equip_templates[%d].star_max 不能为负数" % i}
		if row.has("max_sockets") and int(row.get("max_sockets", 0)) < 0:
			return {"ok": false, "reason": "equip_templates[%d].max_sockets 不能为负数" % i}
		if row.has("default_socket_count") and int(row.get("default_socket_count", 0)) < 0:
			return {"ok": false, "reason": "equip_templates[%d].default_socket_count 不能为负数" % i}

	return {"ok": true}

func validate_equipment_sets_json(text: String) -> Dictionary:
	if text.strip_edges().is_empty():
		return {"ok": false, "reason": "套装配置内容为空"}

	var parsed: Variant = JSON.parse_string(text)
	if not (parsed is Dictionary):
		return {"ok": false, "reason": "套装配置JSON根节点必须是对象"}
	var root: Dictionary = parsed
	var meta_check := _validate_optional_meta(root, "equipment_sets")
	if not bool(meta_check.get("ok", false)):
		return meta_check

	var rows_any: Variant = root.get("equipment_sets", null)
	if not (rows_any is Array):
		return {"ok": false, "reason": "缺少 equipment_sets 数组"}
	var rows: Array = rows_any
	for i in range(rows.size()):
		var row_any: Variant = rows[i]
		if not (row_any is Dictionary):
			return {"ok": false, "reason": "equipment_sets[%d] 不是对象" % i}
		var row: Dictionary = row_any
		for key in ["id", "name", "max_pieces", "thresholds"]:
			if not row.has(key):
				return {"ok": false, "reason": "equipment_sets[%d] 缺少字段 %s" % [i, key]}
		var set_id := str(row.get("id", "")).strip_edges()
		var set_name := str(row.get("name", "")).strip_edges()
		var max_pieces := int(row.get("max_pieces", 0))
		if set_id.is_empty() or set_name.is_empty():
			return {"ok": false, "reason": "equipment_sets[%d] id/name 不能为空" % i}
		if max_pieces < 1:
			return {"ok": false, "reason": "equipment_sets[%d].max_pieces 必须 >= 1" % i}

		var thresholds_any: Variant = row.get("thresholds", [])
		if not (thresholds_any is Array):
			return {"ok": false, "reason": "equipment_sets[%d].thresholds 必须是数组" % i}
		var thresholds: Array = thresholds_any
		for j in range(thresholds.size()):
			var th_any: Variant = thresholds[j]
			if not (th_any is Dictionary):
				return {"ok": false, "reason": "equipment_sets[%d].thresholds[%d] 不是对象" % [i, j]}
			var th: Dictionary = th_any
			var cnt := int(th.get("count", 0))
			if cnt < 1:
				return {"ok": false, "reason": "equipment_sets[%d].thresholds[%d].count 必须 >= 1" % [i, j]}
			var bonuses_any: Variant = th.get("bonuses", [])
			if not (bonuses_any is Array):
				return {"ok": false, "reason": "equipment_sets[%d].thresholds[%d].bonuses 必须是数组" % [i, j]}
			var bonuses: Array = bonuses_any
			for k in range(bonuses.size()):
				var bonus_any: Variant = bonuses[k]
				if not (bonus_any is Dictionary):
					return {"ok": false, "reason": "equipment_sets[%d].thresholds[%d].bonuses[%d] 不是对象" % [i, j, k]}
				var bonus: Dictionary = bonus_any
				var btype := str(bonus.get("type", "")).strip_edges()
				if btype == "stat":
					var stat := str(bonus.get("stat", "")).strip_edges()
					if stat.is_empty():
						return {"ok": false, "reason": "equipment_sets[%d].thresholds[%d].bonuses[%d].stat 不能为空" % [i, j, k]}
				elif btype == "skill_level":
					var skill_id := str(bonus.get("skill_id", "")).strip_edges()
					if skill_id.is_empty():
						return {"ok": false, "reason": "equipment_sets[%d].thresholds[%d].bonuses[%d].skill_id 不能为空" % [i, j, k]}
				else:
					return {"ok": false, "reason": "equipment_sets[%d].thresholds[%d].bonuses[%d].type 非法" % [i, j, k]}
				if int(bonus.get("val", -1)) < 0:
					return {"ok": false, "reason": "equipment_sets[%d].thresholds[%d].bonuses[%d].val 不能为负数" % [i, j, k]}

	return {"ok": true}

func validate_skills_catalog(text: String) -> Dictionary:
	if text.strip_edges().is_empty():
		return {"ok": false, "reason": "技能字典内容为空"}

	var parsed: Variant = JSON.parse_string(text)
	if not (parsed is Dictionary):
		return {"ok": false, "reason": "技能字典JSON根节点必须是对象"}
	var root: Dictionary = parsed
	var meta_check := _validate_optional_meta(root, "skills_catalog")
	if not bool(meta_check.get("ok", false)):
		return meta_check

	var rows_any: Variant = root.get("skills_catalog", null)
	if not (rows_any is Array):
		return {"ok": false, "reason": "缺少 skills_catalog 数组"}
	var rows: Array = rows_any
	if rows.is_empty():
		return {"ok": false, "reason": "skills_catalog 不能为空"}

	for i in range(rows.size()):
		var row_any: Variant = rows[i]
		if not (row_any is Dictionary):
			return {"ok": false, "reason": "skills_catalog[%d] 不是对象" % i}
		var row: Dictionary = row_any
		var skill_id := str(row.get("id", "")).strip_edges()
		var skill_name := str(row.get("name", "")).strip_edges()
		if skill_id.is_empty():
			return {"ok": false, "reason": "skills_catalog[%d].id 不能为空" % i}
		if skill_name.is_empty():
			return {"ok": false, "reason": "skills_catalog[%d].name 不能为空" % i}

	return {"ok": true}

func validate_battle_defaults(text: String) -> Dictionary:
	if text.strip_edges().is_empty():
		return {"ok": false, "reason": "战斗默认配置内容为空"}

	var parsed: Variant = JSON.parse_string(text)
	if not (parsed is Dictionary):
		return {"ok": false, "reason": "战斗默认配置JSON根节点必须是对象"}
	var root: Dictionary = parsed
	var meta_check := _validate_optional_meta(root, "battle_defaults")
	if not bool(meta_check.get("ok", false)):
		return meta_check

	var battle_any: Variant = root.get("battle", null)
	if not (battle_any is Dictionary):
		return {"ok": false, "reason": "缺少 battle 对象"}

	var battle: Dictionary = battle_any
	if battle.has("refine_effect_pool"):
		var refine_check := _validate_refine_effect_pool(battle.get("refine_effect_pool", []))
		if not bool(refine_check.get("ok", false)):
			return {
				"ok": true,
				"refine_effect_pool_ok": false,
				"reason": str(refine_check.get("reason", "refine_effect_pool 非法")),
			}

	return {"ok": true, "refine_effect_pool_ok": true}

func validate_star_rules_json(text: String) -> Dictionary:
	if text.strip_edges().is_empty():
		return {"ok": false, "reason": "升星规则内容为空"}

	var parsed_any: Variant = JSON.parse_string(text)
	if not (parsed_any is Dictionary):
		return {"ok": false, "reason": "升星规则JSON根节点必须是对象"}
	var root: Dictionary = parsed_any
	var meta_check := _validate_optional_meta(root, "star_rules")
	if not bool(meta_check.get("ok", false)):
		return meta_check

	var rules_any: Variant = root.get("star_rules", {})
	if not (rules_any is Dictionary):
		return {"ok": false, "reason": "缺少 star_rules 对象"}
	var rules: Dictionary = rules_any
	var tiers_any: Variant = rules.get("tiers", {})
	if not (tiers_any is Dictionary):
		return {"ok": false, "reason": "star_rules.tiers 必须是对象"}
	var tiers: Dictionary = tiers_any
	if tiers.is_empty():
		return {"ok": false, "reason": "star_rules.tiers 不能为空"}

	for tier_key_any in tiers.keys():
		var tier_key := str(tier_key_any).strip_edges()
		if tier_key.is_empty():
			return {"ok": false, "reason": "star_rules.tiers 存在空tier键名"}
		var tier_any: Variant = tiers.get(tier_key_any, {})
		if not (tier_any is Dictionary):
			return {"ok": false, "reason": "star_rules.tiers.%s 必须是对象" % tier_key}
		var tier: Dictionary = tier_any

		var range_any: Variant = tier.get("level_range", [])
		if not (range_any is Array):
			return {"ok": false, "reason": "star_rules.tiers.%s.level_range 必须是数组" % tier_key}
		var level_range: Array = range_any
		if level_range.size() < 2:
			return {"ok": false, "reason": "star_rules.tiers.%s.level_range 至少2项" % tier_key}
		var lv_min := int(level_range[0])
		var lv_max := int(level_range[1])
		if lv_min < 1 or lv_max < lv_min:
			return {"ok": false, "reason": "star_rules.tiers.%s.level_range 非法" % tier_key}

		var stages_any: Variant = tier.get("stages", {})
		if not (stages_any is Dictionary):
			return {"ok": false, "reason": "star_rules.tiers.%s.stages 必须是对象" % tier_key}
		var stages: Dictionary = stages_any
		if stages.is_empty():
			return {"ok": false, "reason": "star_rules.tiers.%s.stages 不能为空" % tier_key}

		for stage_key_any in stages.keys():
			var stage_key := str(stage_key_any).strip_edges()
			if stage_key.is_empty():
				return {"ok": false, "reason": "star_rules.tiers.%s.stages 存在空阶段键名" % tier_key}
			var stage_any: Variant = stages.get(stage_key_any, {})
			if not (stage_any is Dictionary):
				return {"ok": false, "reason": "star_rules.tiers.%s.stages.%s 必须是对象" % [tier_key, stage_key]}
			var stage: Dictionary = stage_any
			var min_star := int(stage.get("min_star", -1))
			var max_star := int(stage.get("max_star", -1))
			var target_star_max := int(stage.get("target_star_max", 0))
			if min_star < 0 or max_star < min_star:
				return {"ok": false, "reason": "star_rules.tiers.%s.stages.%s 星级区间非法" % [tier_key, stage_key]}
			if target_star_max < 1:
				return {"ok": false, "reason": "star_rules.tiers.%s.stages.%s.target_star_max 必须 >= 1" % [tier_key, stage_key]}
			if target_star_max > 10:
				return {"ok": false, "reason": "star_rules.tiers.%s.stages.%s.target_star_max 不能超过10" % [tier_key, stage_key]}

			var gold_cost := int(stage.get("gold_cost", -1))
			if gold_cost < 0:
				return {"ok": false, "reason": "star_rules.tiers.%s.stages.%s.gold_cost 不能为负数" % [tier_key, stage_key]}

			if stage.has("applicable_slot_groups"):
				var groups_any: Variant = stage.get("applicable_slot_groups", [])
				if not (groups_any is Array):
					return {"ok": false, "reason": "star_rules.tiers.%s.stages.%s.applicable_slot_groups 必须是数组" % [tier_key, stage_key]}
				for g_any in groups_any:
					if str(g_any).strip_edges().is_empty():
						return {"ok": false, "reason": "star_rules.tiers.%s.stages.%s.applicable_slot_groups 含空值" % [tier_key, stage_key]}

			var options_any: Variant = stage.get("material_options", [])
			if not (options_any is Array):
				return {"ok": false, "reason": "star_rules.tiers.%s.stages.%s.material_options 必须是数组" % [tier_key, stage_key]}
			var options: Array = options_any
			if options.is_empty():
				return {"ok": false, "reason": "star_rules.tiers.%s.stages.%s.material_options 不能为空" % [tier_key, stage_key]}
			for i in range(options.size()):
				var opt_any: Variant = options[i]
				if not (opt_any is Dictionary):
					return {"ok": false, "reason": "star_rules.tiers.%s.stages.%s.material_options[%d] 必须是对象" % [tier_key, stage_key, i]}
				var opt: Dictionary = opt_any
				var item_id := str(opt.get("item_id", "")).strip_edges()
				var count := int(opt.get("count", 0))
				if item_id.is_empty():
					return {"ok": false, "reason": "star_rules.tiers.%s.stages.%s.material_options[%d].item_id 不能为空" % [tier_key, stage_key, i]}
				if count <= 0:
					return {"ok": false, "reason": "star_rules.tiers.%s.stages.%s.material_options[%d].count 必须 > 0" % [tier_key, stage_key, i]}

	return {"ok": true}

func validate_forge_rules_json(text: String) -> Dictionary:
	if text.strip_edges().is_empty():
		return {"ok": false, "reason": "打造规则内容为空"}

	var parsed_any: Variant = JSON.parse_string(text)
	if not (parsed_any is Dictionary):
		return {"ok": false, "reason": "打造规则JSON根节点必须是对象"}
	var root: Dictionary = parsed_any
	var meta_check := _validate_optional_meta(root, "forge_rules")
	if not bool(meta_check.get("ok", false)):
		return meta_check

	var rules_any: Variant = root.get("forge_rules", {})
	if not (rules_any is Dictionary):
		return {"ok": false, "reason": "缺少 forge_rules 对象"}
	var rules: Dictionary = rules_any

	var tiers_any: Variant = rules.get("tiers", {})
	if not (tiers_any is Dictionary):
		return {"ok": false, "reason": "forge_rules.tiers 必须是对象"}
	var tiers: Dictionary = tiers_any
	if tiers.is_empty():
		return {"ok": false, "reason": "forge_rules.tiers 不能为空"}
	for tier_key_any in tiers.keys():
		var tier_key := str(tier_key_any).strip_edges()
		if tier_key.is_empty():
			return {"ok": false, "reason": "forge_rules.tiers 存在空tier键名"}
		var row_any: Variant = tiers.get(tier_key_any, {})
		if not (row_any is Dictionary):
			return {"ok": false, "reason": "forge_rules.tiers.%s 必须是对象" % tier_key}
		var row: Dictionary = row_any
		var lv_any: Variant = row.get("level_range", [])
		if not (lv_any is Array):
			return {"ok": false, "reason": "forge_rules.tiers.%s.level_range 必须是数组" % tier_key}
		var lv: Array = lv_any
		if lv.size() < 2:
			return {"ok": false, "reason": "forge_rules.tiers.%s.level_range 至少2项" % tier_key}
		var min_lv := int(lv[0])
		var max_lv := int(lv[1])
		if min_lv < 1 or max_lv < min_lv:
			return {"ok": false, "reason": "forge_rules.tiers.%s.level_range 非法" % tier_key}

	var normal_any: Variant = rules.get("normal_forge_rules", [])
	if not (normal_any is Array):
		return {"ok": false, "reason": "forge_rules.normal_forge_rules 必须是数组"}
	var normal_rows: Array = normal_any
	if normal_rows.is_empty():
		return {"ok": false, "reason": "forge_rules.normal_forge_rules 不能为空"}
	for i in range(normal_rows.size()):
		var row_any: Variant = normal_rows[i]
		if not (row_any is Dictionary):
			return {"ok": false, "reason": "normal_forge_rules[%d] 必须是对象" % i}
		var row: Dictionary = row_any
		if str(row.get("forge_tier", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "normal_forge_rules[%d].forge_tier 不能为空" % i}
		if str(row.get("slot_group", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "normal_forge_rules[%d].slot_group 不能为空" % i}
		if int(row.get("gold_cost", -1)) < 0:
			return {"ok": false, "reason": "normal_forge_rules[%d].gold_cost 不能为负数" % i}
		var mats_any: Variant = row.get("materials", [])
		if not (mats_any is Array):
			return {"ok": false, "reason": "normal_forge_rules[%d].materials 必须是数组" % i}
		var mats: Array = mats_any
		if mats.is_empty():
			return {"ok": false, "reason": "normal_forge_rules[%d].materials 不能为空" % i}
		for j in range(mats.size()):
			var mat_any: Variant = mats[j]
			if not (mat_any is Dictionary):
				return {"ok": false, "reason": "normal_forge_rules[%d].materials[%d] 必须是对象" % [i, j]}
			var mat: Dictionary = mat_any
			if str(mat.get("item_id", "")).strip_edges().is_empty():
				return {"ok": false, "reason": "normal_forge_rules[%d].materials[%d].item_id 不能为空" % [i, j]}
			if int(mat.get("count", 0)) <= 0:
				return {"ok": false, "reason": "normal_forge_rules[%d].materials[%d].count 必须 > 0" % [i, j]}

	var high_any: Variant = rules.get("high_forge_rules", [])
	if not (high_any is Array):
		return {"ok": false, "reason": "forge_rules.high_forge_rules 必须是数组"}
	var high_rows: Array = high_any
	if high_rows.is_empty():
		return {"ok": false, "reason": "forge_rules.high_forge_rules 不能为空"}
	for i in range(high_rows.size()):
		var row_any: Variant = high_rows[i]
		if not (row_any is Dictionary):
			return {"ok": false, "reason": "high_forge_rules[%d] 必须是对象" % i}
		var row: Dictionary = row_any
		if str(row.get("forge_tier", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "high_forge_rules[%d].forge_tier 不能为空" % i}
		if str(row.get("slot_group", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "high_forge_rules[%d].slot_group 不能为空" % i}
		if str(row.get("theme_key", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "high_forge_rules[%d].theme_key 不能为空" % i}
		if int(row.get("gold_cost", -1)) < 0:
			return {"ok": false, "reason": "high_forge_rules[%d].gold_cost 不能为负数" % i}
		var mats_any: Variant = row.get("materials", [])
		if not (mats_any is Array):
			return {"ok": false, "reason": "high_forge_rules[%d].materials 必须是数组" % i}
		var mats: Array = mats_any
		if mats.is_empty():
			return {"ok": false, "reason": "high_forge_rules[%d].materials 不能为空" % i}
		for j in range(mats.size()):
			var mat_any: Variant = mats[j]
			if not (mat_any is Dictionary):
				return {"ok": false, "reason": "high_forge_rules[%d].materials[%d] 必须是对象" % [i, j]}
			var mat: Dictionary = mat_any
			if str(mat.get("item_id", "")).strip_edges().is_empty():
				return {"ok": false, "reason": "high_forge_rules[%d].materials[%d].item_id 不能为空" % [i, j]}
			if int(mat.get("count", 0)) <= 0:
				return {"ok": false, "reason": "high_forge_rules[%d].materials[%d].count 必须 > 0" % [i, j]}
		if row.has("extra_materials"):
			var extra_any: Variant = row.get("extra_materials", [])
			if not (extra_any is Array):
				return {"ok": false, "reason": "high_forge_rules[%d].extra_materials 必须是数组" % i}
			for j in range((extra_any as Array).size()):
				var mat_any: Variant = (extra_any as Array)[j]
				if not (mat_any is Dictionary):
					return {"ok": false, "reason": "high_forge_rules[%d].extra_materials[%d] 必须是对象" % [i, j]}
				var mat: Dictionary = mat_any
				if str(mat.get("item_id", "")).strip_edges().is_empty():
					return {"ok": false, "reason": "high_forge_rules[%d].extra_materials[%d].item_id 不能为空" % [i, j]}
				if int(mat.get("count", 0)) <= 0:
					return {"ok": false, "reason": "high_forge_rules[%d].extra_materials[%d].count 必须 > 0" % [i, j]}

	var compose_any: Variant = rules.get("blueprint_compose_rules", [])
	if not (compose_any is Array):
		return {"ok": false, "reason": "forge_rules.blueprint_compose_rules 必须是数组"}
	var compose_rows: Array = compose_any
	if compose_rows.is_empty():
		return {"ok": false, "reason": "forge_rules.blueprint_compose_rules 不能为空"}
	for i in range(compose_rows.size()):
		var row_any: Variant = compose_rows[i]
		if not (row_any is Dictionary):
			return {"ok": false, "reason": "blueprint_compose_rules[%d] 必须是对象" % i}
		var row: Dictionary = row_any
		if str(row.get("theme_key", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "blueprint_compose_rules[%d].theme_key 不能为空" % i}
		if str(row.get("fragment_item_id", "")).strip_edges().is_empty():
			return {"ok": false, "reason": "blueprint_compose_rules[%d].fragment_item_id 不能为空" % i}
		if int(row.get("fragment_count", 0)) <= 0:
			return {"ok": false, "reason": "blueprint_compose_rules[%d].fragment_count 必须 > 0" % i}
		if int(row.get("gold_cost", -1)) < 0:
			return {"ok": false, "reason": "blueprint_compose_rules[%d].gold_cost 不能为负数" % i}

	return {"ok": true}

func is_valid_refine_effect_pool(pool_any: Variant) -> bool:
	var ret := _validate_refine_effect_pool(pool_any)
	return bool(ret.get("ok", false))

func _validate_refine_effect_pool(pool_any: Variant) -> Dictionary:
	if not (pool_any is Array):
		return {"ok": false, "reason": "refine_effect_pool 必须是数组"}
	var pool: Array = pool_any
	if pool.is_empty():
		return {"ok": false, "reason": "refine_effect_pool 不能为空"}

	var total_w := 0
	for i in range(pool.size()):
		var row_any: Variant = pool[i]
		if not (row_any is Dictionary):
			return {"ok": false, "reason": "refine_effect_pool[%d] 必须是对象" % i}
		var row: Dictionary = row_any
		var w := int(row.get("w", -1))
		if w < 0:
			return {"ok": false, "reason": "refine_effect_pool[%d].w 不能为负数" % i}
		var val := int(row.get("val", -1))
		if val < 0:
			return {"ok": false, "reason": "refine_effect_pool[%d].val 不能为负数" % i}
		var effect_type := str(row.get("type", "")).strip_edges()
		if effect_type != "stat" and effect_type != "skill_level":
			return {"ok": false, "reason": "refine_effect_pool[%d].type 仅支持 stat/skill_level" % i}
		if effect_type == "stat":
			var stat := str(row.get("stat", "")).strip_edges()
			if stat.is_empty():
				return {"ok": false, "reason": "refine_effect_pool[%d].stat 不能为空" % i}
		else:
			var skill_id := str(row.get("skill_id", "")).strip_edges()
			if skill_id.is_empty():
				return {"ok": false, "reason": "refine_effect_pool[%d].skill_id 不能为空" % i}
		total_w += maxi(0, w)
	if total_w <= 0:
		return {"ok": false, "reason": "refine_effect_pool 权重总和必须 > 0"}
	return {"ok": true}

func download_bundle(on_done: Callable) -> void:
	var manifest_url := "%s/manifest.json" % BASE_URL
	_download_text(manifest_url, func(ok: bool, text: String, msg: String) -> void:
		if not ok:
			_call_done(on_done, false, "配置包更新失败：%s" % msg)
			return

		var manifest_check := _validate_bundle_manifest(text)
		if not bool(manifest_check.get("ok", false)):
			_call_done(on_done, false, "配置包更新失败：%s" % str(manifest_check.get("reason", "manifest 校验失败")))
			return

		var bundle_id := str(manifest_check.get("bundle_id", "")).strip_edges()
		var files_any = manifest_check.get("files", [])
		if bundle_id.is_empty() or not (files_any is Array):
			_call_done(on_done, false, "配置包更新失败：manifest 缺少必要信息")
			return
		var files: Array = files_any

		var tmp_bundle_dir := "%s/%s" % [TMP_DIR, bundle_id]
		_remove_dir_recursive_absolute(tmp_bundle_dir)
		var mk_tmp_err := DirAccess.make_dir_recursive_absolute(tmp_bundle_dir)
		if mk_tmp_err != OK:
			_call_done(on_done, false, "配置包更新失败：无法创建临时目录（%d）" % mk_tmp_err)
			return

		_download_bundle_file_recursive(0, files, tmp_bundle_dir, text, bundle_id, on_done)
	)

func download_all(on_done: Callable) -> void:
	download_bundle(func(ok: bool, msg: String) -> void:
		var results: Array[Dictionary] = []
		for key in FILE_KEY_ORDER:
			_append_download_result(results, str(key), ok, msg)
		_call_done_results(on_done, results)
	)

func _download_bundle_file_recursive(index: int, files: Array, tmp_bundle_dir: String, manifest_text: String, bundle_id: String, on_done: Callable) -> void:
	if index >= files.size():
		var activate_err := _activate_bundle(files, tmp_bundle_dir, manifest_text)
		_remove_dir_recursive_absolute(tmp_bundle_dir)
		if not activate_err.is_empty():
			_call_done(on_done, false, "配置包更新失败：%s" % activate_err)
			return
		_call_done(on_done, true, "配置包更新成功（bundle_id=%s）" % bundle_id)
		return

	var row_any: Variant = files[index]
	if not (row_any is Dictionary):
		_remove_dir_recursive_absolute(tmp_bundle_dir)
		_call_done(on_done, false, "配置包更新失败：manifest 文件项结构错误")
		return
	var row: Dictionary = row_any
	var key := str(row.get("key", "")).strip_edges()
	var filename := str(row.get("filename", "")).strip_edges()
	var expect_sha := str(row.get("sha256", "")).strip_edges().to_lower()
	if key.is_empty() or filename.is_empty() or expect_sha.is_empty():
		_remove_dir_recursive_absolute(tmp_bundle_dir)
		_call_done(on_done, false, "配置包更新失败：manifest 文件项字段缺失")
		return

	var file_url := "%s/%s" % [BASE_URL, filename]
	_download_text(file_url, func(ok: bool, text: String, msg: String) -> void:
		if not ok:
			_remove_dir_recursive_absolute(tmp_bundle_dir)
			_call_done(on_done, false, "配置包更新失败：%s 下载失败（%s）" % [key, msg])
			return

		var payload_check := _validate_payload_by_key(key, text)
		if not bool(payload_check.get("ok", false)):
			_remove_dir_recursive_absolute(tmp_bundle_dir)
			_call_done(on_done, false, "配置包更新失败：%s 校验失败（%s）" % [key, str(payload_check.get("reason", "未知错误"))])
			return

		var actual_sha := _sha256_text(text)
		if actual_sha != expect_sha:
			_remove_dir_recursive_absolute(tmp_bundle_dir)
			_call_done(on_done, false, "配置包更新失败：%s 哈希不匹配" % key)
			return

		var write_err := _write_text("%s/%s" % [tmp_bundle_dir, filename], text)
		if not write_err.is_empty():
			_remove_dir_recursive_absolute(tmp_bundle_dir)
			_call_done(on_done, false, "配置包更新失败：%s 写入临时文件失败（%s）" % [key, write_err])
			return

		_download_bundle_file_recursive(index + 1, files, tmp_bundle_dir, manifest_text, bundle_id, on_done)
	)

func _validate_bundle_manifest(text: String) -> Dictionary:
	if text.strip_edges().is_empty():
		return {"ok": false, "reason": "manifest 内容为空"}
	var parsed_any: Variant = JSON.parse_string(text)
	if not (parsed_any is Dictionary):
		return {"ok": false, "reason": "manifest 必须是 JSON 对象"}
	var parsed: Dictionary = parsed_any

	var meta_any: Variant = parsed.get("meta", {})
	if not (meta_any is Dictionary):
		return {"ok": false, "reason": "manifest.meta 缺失"}
	var meta: Dictionary = meta_any
	var bundle_id := str(meta.get("bundle_id", "")).strip_edges()
	if bundle_id.is_empty():
		return {"ok": false, "reason": "manifest.meta.bundle_id 不能为空"}

	var files_any: Variant = parsed.get("files", [])
	if not (files_any is Array):
		return {"ok": false, "reason": "manifest.files 必须是数组"}
	var files_arr: Array = files_any
	if files_arr.is_empty():
		return {"ok": false, "reason": "manifest.files 不能为空"}

	var by_key: Dictionary = {}
	for item_any in files_arr:
		if not (item_any is Dictionary):
			return {"ok": false, "reason": "manifest.files 项必须是对象"}
		var item: Dictionary = item_any
		var key := str(item.get("key", "")).strip_edges()
		var filename := str(item.get("filename", "")).strip_edges()
		var sha256 := str(item.get("sha256", "")).strip_edges()
		if key.is_empty() or filename.is_empty() or sha256.is_empty():
			return {"ok": false, "reason": "manifest.files 项缺少 key/filename/sha256"}
		if not FILE_KEY_TO_NAME.has(key):
			continue
		by_key[key] = {
			"key": key,
			"filename": filename,
			"version": maxi(0, int(item.get("version", 0))),
			"sha256": sha256.to_lower(),
		}

	var ordered: Array[Dictionary] = []
	for key_any in FILE_KEY_ORDER:
		var key := str(key_any)
		if not by_key.has(key):
			return {"ok": false, "reason": "manifest 缺少文件项：%s" % key}
		var row_any = by_key.get(key, {})
		if not (row_any is Dictionary):
			return {"ok": false, "reason": "manifest 文件项格式错误：%s" % key}
		var row: Dictionary = row_any
		var expected_filename := str(FILE_KEY_TO_NAME.get(key, ""))
		var actual_filename := str(row.get("filename", ""))
		if actual_filename != expected_filename:
			return {"ok": false, "reason": "%s 文件名不匹配：%s" % [key, actual_filename]}
		ordered.append(row)

	for key_any in OPTIONAL_FILE_KEYS:
		var key := str(key_any)
		if not by_key.has(key):
			continue
		var row_any = by_key.get(key, {})
		if not (row_any is Dictionary):
			return {"ok": false, "reason": "manifest 文件项格式错误：%s" % key}
		var row: Dictionary = row_any
		var expected_filename := str(FILE_KEY_TO_NAME.get(key, ""))
		var actual_filename := str(row.get("filename", ""))
		if actual_filename != expected_filename:
			return {"ok": false, "reason": "%s 文件名不匹配：%s" % [key, actual_filename]}
		ordered.append(row)

	return {
		"ok": true,
		"bundle_id": bundle_id,
		"files": ordered,
	}

func _validate_payload_by_key(key: String, text: String) -> Dictionary:
	match key:
		"stages":
			return validate_stages_json(text)
		"items":
			return validate_items_json(text)
		"equip_templates":
			return validate_equip_templates_json(text)
		"equipment_sets":
			return validate_equipment_sets_json(text)
		"monsters":
			return validate_monsters_json(text)
		"skills_catalog":
			return validate_skills_catalog(text)
		"battle_defaults":
			return validate_battle_defaults(text)
		"star_rules":
			return validate_star_rules_json(text)
		"forge_rules":
			return validate_forge_rules_json(text)
		_:
			return {"ok": false, "reason": "未知配置 key：%s" % key}

func _activate_bundle(files: Array, tmp_bundle_dir: String, manifest_text: String) -> String:
	var backup_dir := "%s_prev" % ACTIVE_DIR
	_remove_dir_recursive_absolute(backup_dir)
	if DirAccess.dir_exists_absolute(ACTIVE_DIR):
		var backup_err := _copy_dir_recursive_absolute(ACTIVE_DIR, backup_dir)
		if not backup_err.is_empty():
			return "active 切换失败：备份旧配置失败（%s）" % backup_err

	_remove_dir_recursive_absolute(ACTIVE_DIR)
	var mk_active_err := DirAccess.make_dir_recursive_absolute(ACTIVE_DIR)
	if mk_active_err != OK:
		if DirAccess.dir_exists_absolute(backup_dir):
			_remove_dir_recursive_absolute(ACTIVE_DIR)
			_copy_dir_recursive_absolute(backup_dir, ACTIVE_DIR)
		return "无法创建 active 目录（%d）" % mk_active_err

	for row_any in files:
		if not (row_any is Dictionary):
			_restore_active_from_backup(backup_dir)
			return "active 切换失败：文件列表项结构错误"
		var row: Dictionary = row_any
		var filename := str(row.get("filename", "")).strip_edges()
		if filename.is_empty():
			_restore_active_from_backup(backup_dir)
			return "active 切换失败：文件名为空"
		var src_path := "%s/%s" % [tmp_bundle_dir, filename]
		if not FileAccess.file_exists(src_path):
			_restore_active_from_backup(backup_dir)
			return "active 切换失败：临时文件缺失 %s" % filename
		var content := FileAccess.get_file_as_string(src_path)
		var write_err := _write_text("%s/%s" % [ACTIVE_DIR, filename], content)
		if not write_err.is_empty():
			_restore_active_from_backup(backup_dir)
			return "active 切换失败：写入 %s 失败（%s）" % [filename, write_err]

	var mk_root_err := DirAccess.make_dir_recursive_absolute(ACTIVE_ROOT)
	if mk_root_err != OK:
		_restore_active_from_backup(backup_dir)
		return "active 切换失败：无法创建 remote 根目录（%d）" % mk_root_err
	var manifest_write_err := _write_text(ACTIVE_MANIFEST, manifest_text)
	if not manifest_write_err.is_empty():
		_restore_active_from_backup(backup_dir)
		return "active 切换失败：写入 active_manifest 失败（%s）" % manifest_write_err

	_remove_dir_recursive_absolute(backup_dir)
	return ""

func _download_text(url: String, on_done: Callable) -> void:
	var trimmed := url.strip_edges()
	if trimmed.is_empty():
		if on_done.is_valid():
			on_done.call(false, "", "URL为空")
		return

	var req := HTTPRequest.new()
	req.request_completed.connect(_on_download_text_completed.bind(req, on_done), CONNECT_ONE_SHOT)
	add_child(req)
	var err := req.request(trimmed)
	if err != OK:
		if is_instance_valid(req):
			req.queue_free()
		if on_done.is_valid():
			on_done.call(false, "", "请求启动失败（%d）" % err)

func _on_download_text_completed(result: int, response_code: int, _headers: PackedStringArray, body: PackedByteArray, req: HTTPRequest, on_done: Callable) -> void:
	if is_instance_valid(req):
		req.queue_free()
	if result != HTTPRequest.RESULT_SUCCESS:
		if on_done.is_valid():
			on_done.call(false, "", "网络错误（%d）" % result)
		return
	if response_code < 200 or response_code >= 300:
		if on_done.is_valid():
			on_done.call(false, "", "HTTP %d" % response_code)
		return
	if on_done.is_valid():
		on_done.call(true, body.get_string_from_utf8(), "ok")

func _write_text(path: String, text: String) -> String:
	var parent_idx := path.rfind("/")
	if parent_idx > 0:
		var parent := path.substr(0, parent_idx)
		var mk_err := DirAccess.make_dir_recursive_absolute(parent)
		if mk_err != OK:
			return "创建目录失败（%d）" % mk_err
	var file := FileAccess.open(path, FileAccess.WRITE)
	if file == null:
		return "打开文件失败"
	file.store_string(text)
	file.flush()
	file.close()
	return ""

func _copy_dir_recursive_absolute(src: String, dst: String) -> String:
	if not DirAccess.dir_exists_absolute(src):
		return "源目录不存在"
	_remove_dir_recursive_absolute(dst)
	var mk_err := DirAccess.make_dir_recursive_absolute(dst)
	if mk_err != OK:
		return "创建目标目录失败（%d）" % mk_err
	var dir := DirAccess.open(src)
	if dir == null:
		return "打开源目录失败"
	dir.list_dir_begin()
	var name := dir.get_next()
	while name != "":
		if name != "." and name != "..":
			var src_path := "%s/%s" % [src, name]
			var dst_path := "%s/%s" % [dst, name]
			if dir.current_is_dir():
				var sub_err := _copy_dir_recursive_absolute(src_path, dst_path)
				if not sub_err.is_empty():
					dir.list_dir_end()
					return sub_err
			else:
				var bytes := FileAccess.get_file_as_bytes(src_path)
				var file := FileAccess.open(dst_path, FileAccess.WRITE)
				if file == null:
					dir.list_dir_end()
					return "写入文件失败：%s" % dst_path
				file.store_buffer(bytes)
				file.close()
		name = dir.get_next()
	dir.list_dir_end()
	return ""

func _restore_active_from_backup(backup_dir: String) -> void:
	if not DirAccess.dir_exists_absolute(backup_dir):
		return
	_remove_dir_recursive_absolute(ACTIVE_DIR)
	_copy_dir_recursive_absolute(backup_dir, ACTIVE_DIR)

func _sha256_text(text: String) -> String:
	var ctx := HashingContext.new()
	var err := ctx.start(HashingContext.HASH_SHA256)
	if err != OK:
		return ""
	ctx.update(text.to_utf8_buffer())
	var digest := ctx.finish()
	return digest.hex_encode().to_lower()

func _remove_dir_recursive_absolute(path: String) -> void:
	if path.strip_edges().is_empty():
		return
	if not DirAccess.dir_exists_absolute(path):
		return
	var dir := DirAccess.open(path)
	if dir == null:
		return
	dir.list_dir_begin()
	var name := dir.get_next()
	while name != "":
		if name != "." and name != "..":
			var child := "%s/%s" % [path, name]
			if dir.current_is_dir():
				_remove_dir_recursive_absolute(child)
			else:
				DirAccess.remove_absolute(child)
		name = dir.get_next()
	dir.list_dir_end()
	DirAccess.remove_absolute(path)

func _check_probability(dict: Dictionary, key: String) -> bool:
	if not dict.has(key):
		return false
	var v := float(dict.get(key, -1.0))
	return v >= 0.0 and v <= 1.0

func _call_done(cb: Callable, ok: bool, msg: String) -> void:
	if cb.is_valid():
		cb.call(ok, msg)

func _call_done_results(cb: Callable, results: Array[Dictionary]) -> void:
	if cb.is_valid():
		cb.call(results)

func _append_download_result(results: Array[Dictionary], key: String, ok: bool, msg: String) -> void:
	results.append({
		"key": key,
		"ok": ok,
		"msg": msg,
	})

func _validate_optional_meta(root: Dictionary, expected_key: String) -> Dictionary:
	if not root.has("meta"):
		return {"ok": true}

	var meta_any: Variant = root.get("meta", null)
	if not (meta_any is Dictionary):
		return {"ok": false, "reason": "meta 必须是对象"}
	var meta: Dictionary = meta_any
	var version := int(meta.get("version", 0))
	if version < 0:
		return {"ok": false, "reason": "meta.version 必须 >= 0"}

	var key := str(meta.get("key", "")).strip_edges()
	if not key.is_empty() and key != expected_key:
		return {"ok": false, "reason": "meta.key 不匹配，期望 %s 实际 %s" % [expected_key, key]}

	return {"ok": true}
