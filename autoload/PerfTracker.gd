extends Node

const SAVE_PATH := "user://perf_1h.json"
const BUCKET_COUNT := 60

var buckets: Array = []
var current_minute: int = -1
var filled_minutes: int = 0
var _last_save_ts: int = 0

func _ready() -> void:
	load_data()

func tick(now_ts: int) -> void:
	var now_minute := int(now_ts / 60)
	if current_minute == -1:
		current_minute = now_minute
		_init_buckets()
		_clear_bucket_by_minute(now_minute)
		filled_minutes = maxi(filled_minutes, 1)
		return
	if now_minute <= current_minute:
		return
	var diff := now_minute - current_minute
	if diff >= BUCKET_COUNT:
		_clear_all_buckets()
		filled_minutes = 0
	for minute in range(current_minute + 1, now_minute + 1):
		_clear_bucket_by_minute(minute)
	current_minute = now_minute
	filled_minutes = mini(BUCKET_COUNT, filled_minutes + diff)
	filled_minutes = maxi(1, filled_minutes)

func record_kill(kind: String, exp: int) -> void:
	var now_ts := _now_ts()
	tick(now_ts)
	var bucket := _get_current_bucket()
	bucket["kills"] = int(bucket.get("kills", 0)) + 1
	var normalized_kind := _normalize_kind(kind)
	if normalized_kind == "elite":
		bucket["kills_elite"] = int(bucket.get("kills_elite", 0)) + 1
	elif normalized_kind == "boss":
		bucket["kills_boss"] = int(bucket.get("kills_boss", 0)) + 1
	bucket["exp"] = int(bucket.get("exp", 0)) + maxi(0, exp)
	_set_current_bucket(bucket)
	maybe_save(now_ts)

func record_item_gain(item_id: String, count: int) -> void:
	if item_id.is_empty() or count <= 0:
		return
	var now_ts := _now_ts()
	tick(now_ts)
	var bucket := _get_current_bucket()
	var items_dict_any = bucket.get("items", {})
	var items_dict: Dictionary = items_dict_any if items_dict_any is Dictionary else {}
	items_dict[item_id] = int(items_dict.get(item_id, 0)) + count
	bucket["items"] = items_dict
	_set_current_bucket(bucket)
	maybe_save(now_ts)

func record_equip_gain(template_id: String, count: int) -> void:
	if template_id.is_empty() or count <= 0:
		return
	var now_ts := _now_ts()
	tick(now_ts)
	var bucket := _get_current_bucket()
	var equips_dict_any = bucket.get("equips", {})
	var equips_dict: Dictionary = equips_dict_any if equips_dict_any is Dictionary else {}
	equips_dict[template_id] = int(equips_dict.get(template_id, 0)) + count
	bucket["equips"] = equips_dict
	_set_current_bucket(bucket)
	maybe_save(now_ts)

func sum_last_hour() -> Dictionary:
	var totals := _empty_bucket()
	for bucket_any in buckets:
		if not (bucket_any is Dictionary):
			continue
		var bucket: Dictionary = bucket_any
		totals["kills"] = int(totals.get("kills", 0)) + int(bucket.get("kills", 0))
		totals["kills_elite"] = int(totals.get("kills_elite", 0)) + int(bucket.get("kills_elite", 0))
		totals["kills_boss"] = int(totals.get("kills_boss", 0)) + int(bucket.get("kills_boss", 0))
		totals["exp"] = int(totals.get("exp", 0)) + int(bucket.get("exp", 0))
		_merge_count_dict(totals.get("items", {}), bucket.get("items", {}))
		_merge_count_dict(totals.get("equips", {}), bucket.get("equips", {}))
	return totals

func window_seconds() -> int:
	var minutes := mini(BUCKET_COUNT, maxi(1, filled_minutes))
	return maxi(60, mini(3600, minutes * 60))

func maybe_save(now_ts: int) -> void:
	if now_ts - _last_save_ts < 30:
		return
	save_data()
	_last_save_ts = now_ts

func load() -> void:
	load_data()

func save() -> void:
	save_data()

func load_data() -> void:
	_init_buckets()
	current_minute = -1
	filled_minutes = 0
	_last_save_ts = _now_ts()

	if not FileAccess.file_exists(SAVE_PATH):
		return

	var text := FileAccess.get_file_as_string(SAVE_PATH)
	var parsed: Variant = JSON.parse_string(text)
	if not (parsed is Dictionary):
		return
	var data: Dictionary = parsed
	current_minute = int(data.get("current_minute", -1))
	filled_minutes = clampi(int(data.get("filled_minutes", 0)), 0, BUCKET_COUNT)

	var buckets_any = data.get("buckets", [])
	if buckets_any is Array:
		var src: Array = buckets_any
		for i in range(mini(BUCKET_COUNT, src.size())):
			var bucket_any = src[i]
			if bucket_any is Dictionary:
				buckets[i] = _sanitize_bucket(bucket_any)

	if current_minute >= 0 and filled_minutes <= 0:
		filled_minutes = 1

func save_data() -> void:
	_init_buckets()
	var file := FileAccess.open(SAVE_PATH, FileAccess.WRITE)
	if file == null:
		push_warning("PerfTracker: failed to open save file")
		return
	var payload := {
		"current_minute": current_minute,
		"filled_minutes": filled_minutes,
		"buckets": buckets,
	}
	file.store_string(JSON.stringify(payload))

func _init_buckets() -> void:
	if buckets.size() == BUCKET_COUNT:
		return
	buckets.clear()
	for _i in range(BUCKET_COUNT):
		buckets.append(_empty_bucket())

func _clear_all_buckets() -> void:
	_init_buckets()
	for i in range(BUCKET_COUNT):
		buckets[i] = _empty_bucket()

func _clear_bucket_by_minute(minute: int) -> void:
	_init_buckets()
	var idx := _bucket_index(minute)
	buckets[idx] = _empty_bucket()

func _bucket_index(minute: int) -> int:
	return int(posmod(minute, BUCKET_COUNT))

func _get_current_bucket() -> Dictionary:
	_init_buckets()
	if current_minute < 0:
		tick(_now_ts())
	var idx := _bucket_index(current_minute)
	var bucket_any = buckets[idx]
	if bucket_any is Dictionary:
		return bucket_any
	var new_bucket := _empty_bucket()
	buckets[idx] = new_bucket
	return new_bucket

func _set_current_bucket(bucket: Dictionary) -> void:
	_init_buckets()
	if current_minute < 0:
		return
	var idx := _bucket_index(current_minute)
	buckets[idx] = _sanitize_bucket(bucket)

func _empty_bucket() -> Dictionary:
	return {
		"kills": 0,
		"kills_elite": 0,
		"kills_boss": 0,
		"exp": 0,
		"items": {},
		"equips": {},
	}

func _sanitize_bucket(bucket: Dictionary) -> Dictionary:
	var out := _empty_bucket()
	out["kills"] = maxi(0, int(bucket.get("kills", 0)))
	out["kills_elite"] = maxi(0, int(bucket.get("kills_elite", 0)))
	out["kills_boss"] = maxi(0, int(bucket.get("kills_boss", 0)))
	out["exp"] = maxi(0, int(bucket.get("exp", 0)))
	out["items"] = _sanitize_count_dict(bucket.get("items", {}))
	out["equips"] = _sanitize_count_dict(bucket.get("equips", {}))
	return out

func _sanitize_count_dict(src_any: Variant) -> Dictionary:
	var out: Dictionary = {}
	if not (src_any is Dictionary):
		return out
	var src: Dictionary = src_any
	for key_any in src.keys():
		var key := str(key_any)
		if key.is_empty():
			continue
		var count := maxi(0, int(src.get(key_any, 0)))
		if count <= 0:
			continue
		out[key] = count
	return out

func _merge_count_dict(dst_any: Variant, src_any: Variant) -> void:
	if not (dst_any is Dictionary):
		return
	if not (src_any is Dictionary):
		return
	var dst: Dictionary = dst_any
	var src: Dictionary = src_any
	for key_any in src.keys():
		var key := str(key_any)
		if key.is_empty():
			continue
		var add := maxi(0, int(src.get(key_any, 0)))
		if add <= 0:
			continue
		dst[key] = int(dst.get(key, 0)) + add

func _normalize_kind(kind: String) -> String:
	var k := kind.strip_edges().to_lower()
	if k == "elite" or k == "boss":
		return k
	return "normal"

func _now_ts() -> int:
	return int(Time.get_unix_time_from_system())
