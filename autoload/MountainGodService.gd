extends Node

const SAVE_KEY := "mountain_god"

var daily_date: String = ""
var offered: Dictionary = {}

func _ready() -> void:
	_load_state()
	if _reset_daily_if_needed():
		_save_state()

func get_summary() -> Dictionary:
	if _reset_daily_if_needed():
		_save_state()
	var cfg := _config()
	var unlocked := is_unlocked()
	var unlock_stage_id := str(cfg.get("unlock_stage_id", "")).strip_edges()
	var rows := get_rows()
	var can_offer := 0
	var remaining_total := 0
	for row in rows:
		if bool(row.get("can_offer", false)):
			can_offer += 1
		remaining_total += maxi(0, int(row.get("remaining", 0)))
	return {
		"god_id": str(cfg.get("god_id", "nanshan_guardian")),
		"name": str(cfg.get("name", "南山山神")),
		"description": str(cfg.get("description", "")),
		"is_unlocked": unlocked,
		"unlock_stage_id": unlock_stage_id,
		"unlock_stage_name": _stage_name(unlock_stage_id),
		"available_offerings": rows.size(),
		"can_offer_count": can_offer,
		"remaining_total": remaining_total,
	}

func is_unlocked() -> bool:
	_reset_daily_if_needed()
	var cfg := _config()
	var unlock_stage_id := str(cfg.get("unlock_stage_id", "")).strip_edges()
	return unlock_stage_id.is_empty() or MapProgressModel.is_stage_cleared(unlock_stage_id)

func get_rows() -> Array[Dictionary]:
	if _reset_daily_if_needed():
		_save_state()
	var cfg := _config()
	var rows_any = cfg.get("offerings", [])
	var rows: Array[Dictionary] = []
	if not (rows_any is Array):
		return rows
	for row_any in rows_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		if not bool(row.get("is_enabled", true)):
			continue
		var offering_id := str(row.get("offering_id", "")).strip_edges()
		if offering_id.is_empty():
			continue
		var item_id := str(row.get("offering_item_id", "")).strip_edges()
		var owned := InventoryModel.get_count(item_id)
		var daily_limit := maxi(1, int(row.get("daily_limit", 1)))
		var offered_count := maxi(0, int(offered.get(offering_id, 0)))
		var remaining := maxi(0, daily_limit - offered_count)
		var exchange_any = row.get("exchange_cost", {})
		var exchange: Dictionary = exchange_any if exchange_any is Dictionary else {}
		var gold_cost := maxi(0, int(exchange.get("gold", 0)))
		var contribution_cost := maxi(0, int(exchange.get("sect_contribution", 0)))
		var can_exchange := is_unlocked() and PlayerModel.gold >= gold_cost and PlayerModel.sect_contribution >= contribution_cost
		rows.append({
			"offering_id": offering_id,
			"name": str(row.get("name", offering_id)),
			"sort_order": int(row.get("sort_order", 0)),
			"offering_item_id": item_id,
			"offering_item_name": _item_name(item_id),
			"owned_count": owned,
			"daily_limit": daily_limit,
			"remaining": remaining,
			"exchange_cost": exchange,
			"reward_summary": _reward_summary(row.get("rewards", {})),
			"can_exchange": can_exchange,
			"can_offer": is_unlocked() and owned > 0 and remaining > 0,
		})
	rows.sort_custom(func(a: Dictionary, b: Dictionary) -> bool:
		var sa := int(a.get("sort_order", 0))
		var sb := int(b.get("sort_order", 0))
		if sa != sb:
			return sa < sb
		return str(a.get("offering_id", "")) < str(b.get("offering_id", ""))
	)
	return rows

func exchange_offering(offering_id: String) -> Dictionary:
	var row := _find_row(offering_id)
	if row.is_empty():
		return {"ok": false, "reason": "not_found"}
	if not is_unlocked():
		return {"ok": false, "reason": "locked", "unlock_stage_name": _stage_name(str(_config().get("unlock_stage_id", "")))}
	var exchange_any = row.get("exchange_cost", {})
	var exchange: Dictionary = exchange_any if exchange_any is Dictionary else {}
	var gold_cost := maxi(0, int(exchange.get("gold", 0)))
	var contribution_cost := maxi(0, int(exchange.get("sect_contribution", 0)))
	if PlayerModel.gold < gold_cost:
		return {"ok": false, "reason": "no_gold", "need": gold_cost}
	if PlayerModel.sect_contribution < contribution_cost:
		return {"ok": false, "reason": "no_contribution", "need": contribution_cost}
	if gold_cost > 0 and not PlayerModel.spend_gold(gold_cost):
		return {"ok": false, "reason": "no_gold", "need": gold_cost}
	if contribution_cost > 0 and not PlayerModel.spend_sect_contribution(contribution_cost):
		if gold_cost > 0:
			PlayerModel.add_gold(gold_cost)
		return {"ok": false, "reason": "no_contribution", "need": contribution_cost}
	var item_id := str(row.get("offering_item_id", "")).strip_edges()
	InventoryModel.add_item(item_id, 1, "system")
	EventBus.notify_mountain_god_updated()
	EventBus.add_log("兑换祭品：%s" % str(row.get("name", offering_id)))
	return {"ok": true, "name": str(row.get("name", offering_id))}

func offer(offering_id: String) -> Dictionary:
	_reset_daily_if_needed()
	var row := _find_row(offering_id)
	if row.is_empty():
		return {"ok": false, "reason": "not_found"}
	if not is_unlocked():
		return {"ok": false, "reason": "locked", "unlock_stage_name": _stage_name(str(_config().get("unlock_stage_id", "")))}
	var item_id := str(row.get("offering_item_id", "")).strip_edges()
	if InventoryModel.get_count(item_id) < 1:
		return {"ok": false, "reason": "no_item", "item_name": _item_name(item_id)}
	var daily_limit := maxi(1, int(row.get("daily_limit", 1)))
	var used := maxi(0, int(offered.get(offering_id, 0)))
	if used >= daily_limit:
		return {"ok": false, "reason": "daily_limit"}
	if not InventoryModel.spend_item(item_id, 1, "system"):
		return {"ok": false, "reason": "no_item", "item_name": _item_name(item_id)}
	offered[offering_id] = used + 1
	_grant_reward(row.get("rewards", {}), str(row.get("name", offering_id)))
	_save_state()
	EventBus.notify_mountain_god_updated()
	EventBus.add_log("供奉完成：%s" % str(row.get("name", offering_id)))
	return {"ok": true, "name": str(row.get("name", offering_id))}

func _config() -> Dictionary:
	return ConfigService.get_mountain_god()

func _find_row(offering_id: String) -> Dictionary:
	var cfg := _config()
	var rows_any = cfg.get("offerings", [])
	if not (rows_any is Array):
		return {}
	for row_any in rows_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		if str(row.get("offering_id", "")).strip_edges() == offering_id.strip_edges():
			return row
	return {}

func _grant_reward(reward_any: Variant, reason: String) -> void:
	if not (reward_any is Dictionary):
		return
	var reward: Dictionary = reward_any
	var gold := maxi(0, int(reward.get("gold", 0)))
	var spirit_stone := maxi(0, int(reward.get("spirit_stone", 0)))
	var contribution := maxi(0, int(reward.get("sect_contribution", 0)))
	var skill_points := maxi(0, int(reward.get("skill_points", 0)))
	if gold > 0:
		PlayerModel.add_gold(gold)
	if spirit_stone > 0:
		PlayerModel.add_spirit_stone(spirit_stone)
	if contribution > 0:
		PlayerModel.add_sect_contribution(contribution)
	if skill_points > 0:
		SkillModel.grant_skill_points(skill_points, reason)
	var items_any = reward.get("items", [])
	if items_any is Array:
		for item_any in items_any:
			if not (item_any is Dictionary):
				continue
			var item: Dictionary = item_any
			var item_id := str(item.get("item_id", "")).strip_edges()
			var count := maxi(0, int(item.get("count", 0)))
			if item_id.is_empty() or count <= 0:
				continue
			InventoryModel.add_item(item_id, count, "system")

func _reward_summary(reward_any: Variant) -> String:
	if not (reward_any is Dictionary):
		return "—"
	var reward: Dictionary = reward_any
	var parts: Array[String] = []
	var gold := maxi(0, int(reward.get("gold", 0)))
	var spirit_stone := maxi(0, int(reward.get("spirit_stone", 0)))
	var contribution := maxi(0, int(reward.get("sect_contribution", 0)))
	var skill_points := maxi(0, int(reward.get("skill_points", 0)))
	if gold > 0:
		parts.append("金币+%d" % gold)
	if spirit_stone > 0:
		parts.append("灵石+%d" % spirit_stone)
	if contribution > 0:
		parts.append("宗门贡献+%d" % contribution)
	if skill_points > 0:
		parts.append("技能点+%d" % skill_points)
	var items_any = reward.get("items", [])
	if items_any is Array:
		for item_any in items_any:
			if not (item_any is Dictionary):
				continue
			var item: Dictionary = item_any
			var item_id := str(item.get("item_id", "")).strip_edges()
			if item_id.is_empty():
				continue
			parts.append("%s×%d" % [_item_name(item_id), maxi(1, int(item.get("count", 1)))])
	return "、".join(parts) if not parts.is_empty() else "—"

func _reset_daily_if_needed() -> bool:
	var today := Time.get_date_string_from_system()
	if daily_date == today:
		return false
	daily_date = today
	offered.clear()
	return true

func _load_state() -> void:
	daily_date = ""
	offered = {}
	var data := SaveService.get_section(SAVE_KEY)
	daily_date = str(data.get("daily_date", ""))
	var offered_any = data.get("offered", {})
	if offered_any is Dictionary:
		offered = (offered_any as Dictionary).duplicate(true)

func _save_state() -> void:
	SaveService.set_section(SAVE_KEY, {
		"daily_date": daily_date,
		"offered": offered.duplicate(true),
	})

func _stage_name(stage_id: String) -> String:
	if stage_id.is_empty():
		return ""
	var rows_any = ConfigService.get_stages_db().get("stages", [])
	if not (rows_any is Array):
		return stage_id
	for row_any in rows_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		if str(row.get("id", "")).strip_edges() == stage_id:
			return str(row.get("name", stage_id))
	return stage_id

func _item_name(item_id: String) -> String:
	if item_id.is_empty():
		return ""
	var cfg: Dictionary = ConfigService.get_cfg()
	var items_db_any = cfg.get("items_db", {})
	if not (items_db_any is Dictionary):
		return item_id
	var rows_any = (items_db_any as Dictionary).get("items", [])
	if not (rows_any is Array):
		return item_id
	for row_any in rows_any:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		if str(row.get("id", "")).strip_edges() == item_id:
			return str(row.get("name", item_id))
	return item_id
