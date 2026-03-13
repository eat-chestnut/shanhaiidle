extends Node

var _map: Dictionary = {}
var _desc_map: Dictionary = {}

func _ready() -> void:
	rebuild()

func rebuild() -> void:
	_map.clear()
	_desc_map.clear()
	var db_any = ConfigService.get_skills_catalog_db()
	if db_any is Dictionary:
		var rows_any = (db_any as Dictionary).get("skills_catalog", [])
		if rows_any is Array:
			for row_any in rows_any:
				if not (row_any is Dictionary):
					continue
				var row: Dictionary = row_any
				var skill_id := str(row.get("id", ""))
				var skill_name := str(row.get("name", ""))
				if skill_id.is_empty() or skill_name.is_empty():
					continue
				_map[skill_id] = skill_name
				_desc_map[skill_id] = str(row.get("desc", ""))
			if not _map.is_empty():
				return

func name(skill_id: String) -> String:
	if _map.is_empty():
		rebuild()
	return str(_map.get(skill_id, skill_id))

func desc(skill_id: String) -> String:
	if _map.is_empty():
		rebuild()
	return str(_desc_map.get(skill_id, ""))
