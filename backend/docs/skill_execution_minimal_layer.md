# Skill Execution Minimal Layer

## Scope (this round only)
- Only implements the minimal skill execution loop in battle tick.
- Not implemented in this round:
  - Buff system
  - DOT/HOT
  - Control effects
  - Complex target selection
  - Controller/API endpoints
  - Frontend skill animation/effects

## Core components

### 1) `SkillRuntimeStateBuilder`
- Responsibility: convert skill config rows into runtime skill states.
- Public method: `build(unit, skillConfigs)`
- Runtime skill state fields:
  - `skill_id`
  - `owner_unit_id`
  - `skill_type`
  - `damage_ratio`
  - `cooldown_total`
  - `cooldown_remaining`
  - `auto_cast`
  - `enabled`

### 2) `SkillCooldownResolver`
- Responsibility: cooldown tick progression and cast availability check.
- Public methods:
  - `tick(skillState)`
  - `canCast(skillState)`
  - `enterCooldown(skillState)` (used after cast)
- Rules:
  - After cast: `cooldown_remaining = cooldown_total`
  - End of tick: `cooldown_remaining = max(0, cooldown_remaining - 1)`
  - Cast allowed only when `cooldown_remaining = 0`

### 3) `SkillCastResolver`
- Responsibility: decide whether a skill can be cast in this tick and determine target.
- Public methods:
  - `resolvePlayerCast(playerUnit, enemyUnits, skillStates)`
  - `resolveEnemyCast(enemyUnit, playerUnit, skillStates)`
- Rules:
  - Only `auto_cast = true` skills are considered
  - Player target: first alive unit in current wave (lowest `unit_index`)
  - Enemy target: player
  - Only single-target cast is supported

### 4) `SkillDamageResolver`
- Responsibility: minimal skill damage calculation.
- Public methods:
  - `resolvePlayerSkillDamage(playerUnit, enemyUnit, skillState)`
  - `resolveEnemySkillDamage(enemyUnit, playerUnit, skillState)`
- Supported skill type:
  - `single_damage`

### 5) `SkillActionLogger`
- Responsibility: append skill cast logs into runtime logs.
- Public method:
  - `logSkillCast(runtimeState, tick, actorUnitId, targetUnitId, skillId, damage)`
- Skill cast log fields:
  - `tick`
  - `actor`
  - `target`
  - `action = skill_cast`
  - `skill_id`
  - `damage`

## Runtime skill structure and config support
- First round skill config only needs:
  - `skill_type` (`single_damage`)
  - `damage_ratio`
  - `cooldown_ticks`
  - `auto_cast`
  - `enabled`
- `CombatTickRunner` resolves skill configs from unit skill rows and optional `battle_context.skill_configs`.

## Minimal skill damage formulas

### Player skill damage
- `base_skill_damage = player_attack * damage_ratio`
- `skill_bonus_multiplier = 1 + bonus_skill_dmg_percent`
- `final_damage = max(1, base_skill_damage * skill_bonus_multiplier - target_def)`

### Enemy skill damage
- `base_skill_damage = enemy_attack * damage_ratio`
- `final_damage = max(1, base_skill_damage - target_def)`

## Tick integration order
1. Player action:
   - Cast skill first if castable
   - Otherwise basic attack
2. Enemy actions:
   - Cast skill first if castable
   - Otherwise basic attack
3. Death settlement
4. Skill cooldown tick
5. Wave switching
6. Victory/defeat resolve

## Unified method response
All public methods in this layer return:

```json
{
  "ok": true,
  "reason": null,
  "data": {}
}
```
