extends RefCounted
class_name StatsService

static func calc_base_stats(level: int, attrs: Dictionary) -> Dictionary:
	var safe_level := maxi(1, level)
	var physique := maxi(0, int(attrs.get("physique", 0)))
	var agility := maxi(0, int(attrs.get("agility", 0)))
	var strength := maxi(0, int(attrs.get("strength", 0)))
	var spirit := maxi(0, int(attrs.get("spirit", 0)))
	var true_energy := maxi(0, int(attrs.get("true_energy", 0)))
	var fortune := maxi(0, int(attrs.get("fortune", 0)))

	var hp_lv := int(floor(float(safe_level - 1) / 4.0))
	var hp_from_physique := physique + int(floor(float(physique) / 10.0))
	var hp_total := 10 + hp_lv + hp_from_physique

	var qi_lv := int(floor(float(safe_level - 1) / 5.0))
	var qi_total := 10 + qi_lv + true_energy

	var atk_total := 1
	var def_total := 0

	var base_crit := 5
	var crit_add := int(floor(float(agility * 25) / 100.0))
	var crit_percent := base_crit + crit_add

	var phys_mul_permille := 1000 + strength * 12
	var spell_mul_permille := 1000 + spirit * 12
	var loot_bonus_percent := int(floor(float(fortune * 6) / 10.0))

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
