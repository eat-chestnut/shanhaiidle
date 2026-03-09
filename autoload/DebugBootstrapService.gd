extends Node

const SAVE_SECTION := "debug_bootstrap"
const DONE_KEY := "phase3_star_seed_done"
const SECT_TOKEN_ID := "宗门令"
const DEBUG_GOLD_TARGET := 80000

const DEBUG_ITEM_REWARDS := {
	"宗门令": 10,
	"打孔石": 12,
	"赤晶石": 4,
	"沧澜石": 4,
	"青木石": 4,
	"star_stone_t1_common": 80,
	"star_stone_t2_common": 50,
	"star_stone_t3_common": 30,
	"star_stone_t4_common": 20,
}

# DEBUG 下每次启动都会把关键材料补到该库存下限，避免旧存档因一次性标记导致材料不足。
const DEBUG_TOPUP_TARGETS := {
	"宗门令": 30,
	"打孔石": 120,
	"玉屑": 1200,
	"桂枝": 1200,
	"白玉碎": 900,
	"妖核": 400,
	"妖王核心": 100,
	"赤晶石": 320,
	"沧澜石": 320,
	"青木石": 320,
	"star_stone_t1_common": 1200,
	"star_stone_t2_common": 900,
	"star_stone_t3_common": 700,
	"star_stone_t4_common": 500,
}

const DEBUG_EQUIP_PLAN := [
	{"id": "eq_weapon_002", "count": 1},
	{"id": "eq_helm_001", "count": 1},
	{"id": "eq_armor_002", "count": 1},
	{"id": "eq_pants_001", "count": 1},
	{"id": "eq_shoes_001", "count": 1},
	{"id": "eq_cloak_001", "count": 1},
	{"id": "eq_ring_001", "count": 2},
	{"id": "eq_bracelet_001", "count": 2},
]

func _ready() -> void:
	if not OS.is_debug_build():
		return
	call_deferred("_run_debug_bootstrap")

func _run_debug_bootstrap() -> void:
	_run_bootstrap_once()
	_top_up_debug_resources()

func _run_bootstrap_once() -> void:
	var section := SaveService.get_section(SAVE_SECTION)
	if bool(section.get(DONE_KEY, false)):
		return

	InventoryModel.add_items_bulk(DEBUG_ITEM_REWARDS, "system", false)
	PlayerModel.add_gold(5000, false)

	var new_uids: Array[int] = []
	for row_any in DEBUG_EQUIP_PLAN:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		var template_id := str(row.get("id", "")).strip_edges()
		var count := maxi(0, int(row.get("count", 0)))
		if template_id.is_empty() or count <= 0:
			continue
		new_uids.append_array(_grant_template_ids(template_id, count))

	_force_identified(new_uids)
	_equip_to_empty_slots(new_uids)

	SaveService.set_section(SAVE_SECTION, {
		DONE_KEY: true,
		"seeded_at": Time.get_datetime_string_from_system(false, true),
		"sect_token_id": SECT_TOKEN_ID,
	})
	EventBus.notify_inventory_updated()
	EventBus.add_log("DEBUG初始化：已发放宗门令x10、升星材料与测试装备")

func _top_up_debug_resources() -> void:
	var add_items: Dictionary = {}
	for item_id_any in DEBUG_TOPUP_TARGETS.keys():
		var item_id := str(item_id_any).strip_edges()
		var target := maxi(0, int(DEBUG_TOPUP_TARGETS.get(item_id_any, 0)))
		if item_id.is_empty() or target <= 0:
			continue
		var have := InventoryModel.get_count(item_id)
		if have >= target:
			continue
		add_items[item_id] = target - have

	var touched := false
	if not add_items.is_empty():
		InventoryModel.add_items_bulk(add_items, "system", false)
		touched = true

	if PlayerModel.gold < DEBUG_GOLD_TARGET:
		PlayerModel.add_gold(DEBUG_GOLD_TARGET - PlayerModel.gold, false)
		touched = true

	if touched:
		EventBus.notify_inventory_updated()
		EventBus.add_log("DEBUG补给：已补充大量材料（含升星材料）")

func _grant_template_ids(template_id: String, count: int) -> Array[int]:
	var before := _collect_bag_uids()
	for _i in range(count):
		EquipmentModel.add_equip(template_id, "system")
	var after := _collect_bag_uids()
	var out: Array[int] = []
	for uid_any in after.keys():
		var uid := int(uid_any)
		if uid <= 0:
			continue
		if before.has(uid):
			continue
		out.append(uid)
	return out

func _collect_bag_uids() -> Dictionary:
	var out: Dictionary = {}
	var rows := EquipmentModel.list_bag_sorted()
	for row_any in rows:
		if not (row_any is Dictionary):
			continue
		var row: Dictionary = row_any
		var uid := int(row.get("uid", 0))
		if uid > 0:
			out[uid] = true
	return out

func _force_identified(uids: Array[int]) -> void:
	var changed := false
	for uid in uids:
		if uid <= 0:
			continue
		var inst := EquipmentModel._get_instance_by_uid(uid)
		if inst.is_empty():
			continue
		if bool(inst.get("identified", true)):
			continue
		inst["identified"] = true
		if EquipmentModel._set_instance_by_uid(uid, inst):
			changed = true
	if changed:
		EquipmentModel._request_save()

func _equip_to_empty_slots(uids: Array[int]) -> void:
	var rows: Array[Dictionary] = []
	for uid in uids:
		if uid <= 0:
			continue
		var inst := EquipmentModel._get_instance_by_uid(uid)
		if inst.is_empty():
			continue
		rows.append(inst)
	rows.sort_custom(_sort_seed_inst_for_equip)

	for inst in rows:
		var uid := int(inst.get("uid", 0))
		if uid <= 0:
			continue
		if not _can_equip_to_empty_slot(inst):
			continue
		EquipmentModel.equip_uid(uid)

func _can_equip_to_empty_slot(inst: Dictionary) -> bool:
	var slot := str(inst.get("slot", "")).strip_edges().to_lower()
	if slot.is_empty():
		return false
	match slot:
		"ring":
			return EquipmentModel.get_equipped_uid("ring1") == 0 or EquipmentModel.get_equipped_uid("ring2") == 0
		"bracelet":
			return EquipmentModel.get_equipped_uid("bracelet1") == 0 or EquipmentModel.get_equipped_uid("bracelet2") == 0
		_:
			return EquipmentModel.get_equipped_uid(slot) == 0

func _sort_seed_inst_for_equip(a_any: Variant, b_any: Variant) -> bool:
	if not (a_any is Dictionary) or not (b_any is Dictionary):
		return false
	var a: Dictionary = a_any
	var b: Dictionary = b_any
	var sa := _slot_sort_value(str(a.get("slot", "")))
	var sb := _slot_sort_value(str(b.get("slot", "")))
	if sa != sb:
		return sa < sb
	return int(a.get("uid", 0)) < int(b.get("uid", 0))

func _slot_sort_value(slot: String) -> int:
	match slot.to_lower():
		"weapon":
			return 0
		"helm":
			return 1
		"armor":
			return 2
		"pants":
			return 3
		"shoes":
			return 4
		"cloak":
			return 5
		"ring":
			return 6
		"bracelet":
			return 7
		_:
			return 99
