extends Node

signal summary_changed(summary: Dictionary)

const CAT_MONSTER := "monster"
const CAT_ITEM := "item"
const CAT_EQUIP := "equip"

var _dirty := true
var _last_bundle_id := ""
var _cache: Dictionary = {}

func _ready() -> void:
	if not EventBus.inventory_updated.is_connected(_on_inventory_updated):
		EventBus.inventory_updated.connect(_on_inventory_updated)
	refresh_cache()

func refresh_cache() -> void:
	var monster_summary := get_monster_summary()
	var item_summary := get_item_summary()
	var equip_summary := get_equip_summary()

	var total_count := int(monster_summary.get("total_count", 0)) + int(item_summary.get("total_count", 0)) + int(equip_summary.get("total_count", 0))
	var unlocked_count := int(monster_summary.get("unlocked_count", 0)) + int(item_summary.get("unlocked_count", 0)) + int(equip_summary.get("unlocked_count", 0))
	var reward_claimed_count := int(monster_summary.get("reward_claimed_count", 0)) + int(item_summary.get("reward_claimed_count", 0)) + int(equip_summary.get("reward_claimed_count", 0))
	var reward_pending_count := int(monster_summary.get("reward_pending_count", 0)) + int(item_summary.get("reward_pending_count", 0)) + int(equip_summary.get("reward_pending_count", 0))

	_cache = {
		"monster_summary": monster_summary,
		"item_summary": item_summary,
		"equip_summary": equip_summary,
		"total_count": total_count,
		"unlocked_count": unlocked_count,
		"reward_claimed_count": reward_claimed_count,
		"reward_pending_count": reward_pending_count,
		"completion_ratio": _ratio(unlocked_count, total_count),
	}
	_dirty = false
	_last_bundle_id = _current_bundle_id()
	emit_signal("summary_changed", _cache.duplicate(true))

func get_monster_summary() -> Dictionary:
	var ids := MonsterDexModel.get_total_entry_ids()
	var total_count := ids.size()
	var unlocked_count := 0
	var claimed_count := 0
	var pending_count := 0
	for monster_id in ids:
		if MonsterDexModel.is_unlocked(monster_id):
			unlocked_count += 1
			if MonsterDexModel.is_reward_claimed(monster_id):
				claimed_count += 1
			else:
				pending_count += 1
	return {
		"category": CAT_MONSTER,
		"title": "怪物图鉴",
		"total_count": total_count,
		"unlocked_count": unlocked_count,
		"reward_claimed_count": claimed_count,
		"reward_pending_count": pending_count,
		"completion_ratio": _ratio(unlocked_count, total_count),
	}

func get_item_summary() -> Dictionary:
	var ids := ItemDexModel.get_total_entry_ids()
	var total_count := ids.size()
	var unlocked_count := 0
	var claimed_count := 0
	var pending_count := 0
	for item_id in ids:
		if ItemDexModel.is_unlocked(item_id):
			unlocked_count += 1
			if ItemDexModel.is_reward_claimed(item_id):
				claimed_count += 1
			else:
				pending_count += 1
	return {
		"category": CAT_ITEM,
		"title": "材料图鉴",
		"total_count": total_count,
		"unlocked_count": unlocked_count,
		"reward_claimed_count": claimed_count,
		"reward_pending_count": pending_count,
		"completion_ratio": _ratio(unlocked_count, total_count),
	}

func get_equip_summary() -> Dictionary:
	var ids := EquipDexModel.get_total_entry_ids()
	var total_count := ids.size()
	var unlocked_count := 0
	var claimed_count := 0
	var pending_count := 0
	for template_id in ids:
		if EquipDexModel.is_unlocked(template_id):
			unlocked_count += 1
			if EquipDexModel.is_reward_claimed(template_id):
				claimed_count += 1
			else:
				pending_count += 1
	return {
		"category": CAT_EQUIP,
		"title": "装备图鉴",
		"total_count": total_count,
		"unlocked_count": unlocked_count,
		"reward_claimed_count": claimed_count,
		"reward_pending_count": pending_count,
		"completion_ratio": _ratio(unlocked_count, total_count),
	}

func get_total_summary() -> Dictionary:
	_ensure_cache()
	return _cache.duplicate(true)

func has_pending_rewards(category: String = "") -> bool:
	return get_pending_reward_count(category) > 0

func get_pending_reward_count(category: String = "") -> int:
	_ensure_cache()
	match category:
		CAT_MONSTER:
			var m_any = _cache.get("monster_summary", {})
			if m_any is Dictionary:
				return int((m_any as Dictionary).get("reward_pending_count", 0))
			return 0
		CAT_ITEM:
			var i_any = _cache.get("item_summary", {})
			if i_any is Dictionary:
				return int((i_any as Dictionary).get("reward_pending_count", 0))
			return 0
		CAT_EQUIP:
			var e_any = _cache.get("equip_summary", {})
			if e_any is Dictionary:
				return int((e_any as Dictionary).get("reward_pending_count", 0))
			return 0
		_:
			return int(_cache.get("reward_pending_count", 0))

func get_category_cards() -> Array[Dictionary]:
	_ensure_cache()
	var cards: Array[Dictionary] = []
	for key in ["monster_summary", "item_summary", "equip_summary"]:
		var summary_any = _cache.get(key, {})
		if not (summary_any is Dictionary):
			continue
		var summary: Dictionary = summary_any
		var pending := int(summary.get("reward_pending_count", 0))
		var row := summary.duplicate(true)
		row["has_red_dot"] = pending > 0
		cards.append(row)
	return cards

func _on_inventory_updated() -> void:
	_dirty = true
	refresh_cache()

func _ensure_cache() -> void:
	if _dirty or _cache.is_empty() or _last_bundle_id != _current_bundle_id():
		refresh_cache()

func _ratio(num: int, den: int) -> float:
	if den <= 0:
		return 0.0
	return clampf(float(num) / float(den), 0.0, 1.0)

func _current_bundle_id() -> String:
	if has_node("/root/RemoteConfigService"):
		return RemoteConfigService.get_active_bundle_id()
	return ""
