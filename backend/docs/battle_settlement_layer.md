# Battle Settlement Layer

## Scope

This round only implements the backend battle settlement layer. It does not add controller/API endpoints, frontend settlement UI, battle execution logic, the full random drop system, the skill system, or Buff timing.

## Core Components

### BattleSettlementGuardService

- Checks whether the current `battle_id` can enter settlement.
- Rejects duplicate settlement when the same `battle_id` has already been persisted or already marked settled.
- Exposes whether main-stage first-clear rewards have already been claimed for the current player and difficulty.
- All public responses follow the unified `{ ok, reason, data }` contract.

### BattleResultPersistenceService

- Persists ended battles into `battle_results`.
- Only accepts `victory` and `defeat`.
- Enforces `battle_id` uniqueness and rejects duplicate persistence.
- Provides a `markSettled()` entry point so the caller can complete the recommended settlement sequence and flip `is_settled`.

### RewardResolutionService

- Resolves the minimal supported reward sources for this round:
  - Main-stage first-clear rewards from `stage_difficulty_first_clear_rewards`
  - Boss drops from the boss monster's own `monster_drop_items`
- Does not define a second Boss reward table in the settlement layer.
- Applies resolved rewards onto `shop_player_profiles` currency/inventory fields as the current minimal grant landing point.
- Creates a main-stage first-clear claim record to guarantee idempotency.

### BattleSettlementPayloadBuilder

- Builds the fixed settlement payload shape.
- The output always contains:
  - `battle_result`
  - `reward_items`
  - `first_clear_granted`
  - `next_unlocks`
  - `debug_reward_sources`

## Persistence

### battle_results

The formal settlement persistence table includes:

- `id`
- `battle_id` (unique)
- `player_id`
- `battle_type`
- `stage_id`
- `difficulty_id`
- `battle_result` (`victory` or `defeat`)
- `elapsed_ticks`
- `remaining_player_hp`
- `remaining_enemy_count`
- `cleared_wave_count`
- `is_settled`
- `settled_at`
- `created_at`
- `updated_at`

### player_main_stage_first_clear_claims

This auxiliary table is the first-clear idempotency anchor for the current round.

- Unique key: `player_id + difficulty_id`
- Prevents duplicate first-clear reward grants for the same player and main-stage difficulty
- Links back to the originating `battle_results.id` when available

## Supported Rewards In This Round

### Main-stage first-clear rewards

- Source: `stage_difficulty_first_clear_rewards`
- Only granted on `victory`
- Only granted once per player and difficulty
- If already claimed, settlement still proceeds, but the first-clear reward is not granted again

### Boss drops

- Source: the defeated Boss monster's own `monster_drop_items`
- Debug source type is `monster_drop_config`
- The settlement layer only reads monster-bound drop data and does not duplicate Boss reward rules elsewhere

## Explicitly Not Supported In This Round

- Main-stage base clear rewards
- Daily dungeon base rewards
- A separate Boss fixed-drop config layer in settlement
- A separate Boss core-drop config layer in settlement
- A complete random drop pool system
- Controller/API wiring
- Frontend settlement UI

## Idempotency And Duplicate Prevention

- `battle_id` cannot be settled twice
- `battle_results.battle_id` is unique
- `battle_results.is_settled` and `settled_at` support final settlement deduplication
- Main-stage first-clear rewards cannot be granted twice because `player_main_stage_first_clear_claims` is unique per player+difficulty

## Recommended Execution Order

1. Call `BattleSettlementGuardService::check()`
2. Call `BattleResultPersistenceService::execute()`
3. Call `RewardResolutionService::execute()`
4. Grant rewards in the same transaction using the existing `shop_player_profiles` inventory/currency landing point
5. Call `BattleSettlementPayloadBuilder::build()`
6. Call `BattleResultPersistenceService::markSettled()`
