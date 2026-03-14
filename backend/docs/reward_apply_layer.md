# Reward Apply Layer (Inventory Minimal)

## Scope (this round only)
- Only implements reward grant apply + inventory persistence.
- Does not include:
  - Full inventory UI
  - Mail compensation/re-delivery
  - Full equipment-instance drop instantiation
  - Controller/API endpoints
  - Battle runtime/settlement execution

## JSON contract alignment
- Field naming and response structure are aligned with:
  - `data/reward_apply_examples_v1.json`
- Public service methods use unified envelope:

```json
{
  "ok": true,
  "reason": null,
  "data": {}
}
```

## Core components

### 1) `RewardGrantService`
- Responsibility: grant `reward_items` into player assets.
- Method: `execute(playerId, rewardItems)`
- Rules:
  - `item_id` required
  - `count > 0`
  - Item must exist in `items` and be enabled
  - `main_type = currency` -> write `player_currencies`
  - `main_type in [material, boss_core, consumable, item]` -> write `player_items`
- Output:
  - `data.granted_items`

### 2) `RewardGrantGuardService`
- Responsibility: idempotency and duplicate-prevention gate.
- Method: `check(playerId, battleId, rewardPayload)`
- Rules:
  - Reject if same `battle_id` already has grant logs
  - Reject if same `grant_batch_id` already has grant logs
  - Reject duplicate first-clear grant:
    - checks `reward_source_type = main_stage_first_clear` + `reward_source_id` from payload
    - checks `player_main_stage_first_clear_claims` when `first_clear_claim.difficulty_id` is provided
- Output:
  - `data.allowed`
  - clear `reason` on failure

### 3) `RewardGrantLogService`
- Responsibility: persist reward grant logs.
- Method: `record(playerId, battleId, rewardItems, rewardSourceType, rewardSourceId, grantBatchId)`
- Rules:
  - At least one log per reward item
  - Supports reward source types:
    - `main_stage_first_clear`
    - `monster_drop_config`
    - `battle_settlement`
- Output:
  - `data.logged_count`

### 4) `RewardApplyTransactionService`
- Responsibility: orchestrate full apply transaction.
- Method: `execute(playerId, battleId, rewardPayload)`
- Flow:
  1. Call `RewardGrantGuardService`
  2. If allowed, open DB transaction
  3. Call `RewardGrantService`
  4. Call `RewardGrantLogService`
  5. Commit transaction
- Output:
  - `data.battle_id`
  - `data.granted`
  - `data.granted_items`

## Minimal persistence tables

### `player_currencies`
- `id`
- `player_id`
- `currency_id`
- `amount`
- `created_at`
- `updated_at`

### `player_items`
- `id`
- `player_id`
- `item_id`
- `count`
- `created_at`
- `updated_at`

### `reward_grant_logs`
- `id`
- `player_id`
- `battle_id`
- `item_id`
- `count`
- `reward_source_type`
- `reward_source_id`
- `grant_batch_id`
- `created_at`
- `updated_at`

## Idempotency notes
- `battle_id` duplicate grant is blocked.
- `grant_batch_id` duplicate grant is blocked.
- First-clear reward duplicate grant is blocked.
- Grant logs are mandatory for every granted reward item and are used as the persistent idempotency signal.

## Supported reward types in this round
- Currency
- Material
- Boss core
- Ordinary item (consumable/item)
