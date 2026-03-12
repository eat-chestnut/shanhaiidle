extends RefCounted
class_name StatsService

static func calc_base_stats(level: int, attrs: Dictionary) -> Dictionary:
	var safe_level := maxi(1, level)
	var base_growth := _base_growth_cfg()
	var formulas := _attribute_formulas_cfg()
	var physique := maxi(0, int(attrs.get("physique", 0)))
	var agility := maxi(0, int(attrs.get("agility", 0)))
	var strength := maxi(0, int(attrs.get("strength", 0)))
	var spirit := maxi(0, int(attrs.get("spirit", 0)))
	var true_energy := maxi(0, int(attrs.get("true_energy", 0)))
	var fortune := maxi(0, int(attrs.get("fortune", 0)))

	var hp_cfg := _dict(base_growth.get("hp", {}))
	var hp_base := int(hp_cfg.get("base", 10))
	var hp_every := maxi(1, int(hp_cfg.get("per_level_every", 4)))
	var hp_gain := int(hp_cfg.get("per_level_gain", 1))
	var physique_formula := _dict(formulas.get("physique", {}))
	var hp_per_point := float(physique_formula.get("hp_per_point", 1.0))
	var hp_extra_every_10 := int(physique_formula.get("hp_extra_every_10", 1))
	var hp_lv := int(floor(float(safe_level - 1) / float(hp_every))) * hp_gain
	var hp_from_physique := int(floor(float(physique) * hp_per_point)) + int(floor(float(physique) / 10.0)) * hp_extra_every_10
	var hp_total := hp_base + hp_lv + hp_from_physique

	var qi_cfg := _dict(base_growth.get("qi", {}))
	var qi_base := int(qi_cfg.get("base", 10))
	var qi_every := maxi(1, int(qi_cfg.get("per_level_every", 5)))
	var qi_gain := int(qi_cfg.get("per_level_gain", 1))
	var true_energy_formula := _dict(formulas.get("true_energy", {}))
	var qi_per_point := float(true_energy_formula.get("qi_per_point", 1.0))
	var qi_lv := int(floor(float(safe_level - 1) / float(qi_every))) * qi_gain
	var qi_total := qi_base + qi_lv + int(floor(float(true_energy) * qi_per_point))

	var atk_cfg := _dict(base_growth.get("atk", {}))
	var atk_total := int(atk_cfg.get("base", 1)) + (safe_level - 1) * int(atk_cfg.get("per_level_gain", 0))

	var def_cfg := _dict(base_growth.get("def", {}))
	var def_per_point := float(physique_formula.get("def_per_point", 0.0))
	var def_total := int(def_cfg.get("base", 0)) + (safe_level - 1) * int(def_cfg.get("per_level_gain", 0)) + int(floor(float(physique) * def_per_point))

	var agility_formula := _dict(formulas.get("agility", {}))
	var base_crit := int(_dict(base_growth.get("crit_percent", {})).get("base", 5))
	var crit_add := int(floor(float(agility) * float(agility_formula.get("crit_percent_per_point", 0.25))))
	var crit_percent := base_crit + crit_add

	var strength_formula := _dict(formulas.get("strength", {}))
	var spirit_formula := _dict(formulas.get("spirit", {}))
	var fortune_formula := _dict(formulas.get("fortune", {}))
	var phys_mul_permille := 1000 + strength * int(strength_formula.get("phys_mul_permille_per_point", 12))
	var spell_mul_permille := 1000 + spirit * int(spirit_formula.get("spell_mul_permille_per_point", 12))
	var loot_base := int(_dict(base_growth.get("loot_bonus_percent", {})).get("base", 0))
	var loot_bonus_percent := loot_base + int(floor(float(fortune) * float(fortune_formula.get("loot_bonus_percent_per_point", 0.6))))

	return {
		"HP": hp_total,
		"ATK": atk_total,
		"DEF": def_total,
		"QI": qi_total,
		"CRIT_PERCENT": crit_percent,
		"LOOT_BONUS_PERCENT": loot_bonus_percent,
		"PHYS_MUL_PERMILLE": phys_mul_permille,
		"SPELL_MUL_PERMILLE": spell_mul_permille,
	}

static func _growth_cfg() -> Dictionary:
	return ConfigService.get_character_growth_rules()

static func _base_growth_cfg() -> Dictionary:
	var growth := _growth_cfg()
	return _dict(growth.get("base_growth", {}))

static func _attribute_formulas_cfg() -> Dictionary:
	var growth := _growth_cfg()
	return _dict(growth.get("attribute_formulas", {}))

static func _dict(value: Variant) -> Dictionary:
	if value is Dictionary:
		return value
	return {}
