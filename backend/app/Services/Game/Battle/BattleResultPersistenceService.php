<?php

namespace App\Services\Game\Battle;

use App\Models\BattleResult;
use Illuminate\Support\Carbon;

class BattleResultPersistenceService
{
    public function execute(string|int $playerId, array $runtimeResult, array $battleContext): array
    {
        $safePlayerId = trim((string) $playerId);
        $battleId = trim((string) ($battleContext['battle_id'] ?? $runtimeResult['battle_id'] ?? ''));
        $battleResult = trim((string) ($runtimeResult['battle_result'] ?? ''));

        if ($safePlayerId === '' || $battleId === '') {
            return $this->failure('invalid_battle_context');
        }

        if (! in_array($battleResult, ['victory', 'defeat'], true)) {
            return $this->failure('invalid_battle_result');
        }

        $existing = BattleResult::query()->where('battle_id', $battleId)->first();
        if ($existing instanceof BattleResult) {
            return $this->failure(
                $existing->is_settled ? 'battle already settled' : 'battle result already persisted',
                [
                    'battle_id' => $battleId,
                    'battle_result' => (string) $existing->battle_result,
                    'persisted' => false,
                ],
            );
        }

        $record = BattleResult::query()->create([
            'battle_id' => $battleId,
            'player_id' => $safePlayerId,
            'battle_type' => trim((string) ($battleContext['battle_type'] ?? '')),
            'stage_id' => $this->nullableString($battleContext['stage_id'] ?? null),
            'difficulty_id' => $this->nullableString($battleContext['difficulty_id'] ?? null),
            'battle_result' => $battleResult,
            'elapsed_ticks' => max(0, (int) ($runtimeResult['elapsed_ticks'] ?? 0)),
            'remaining_player_hp' => max(0, (int) ($runtimeResult['remaining_player_hp'] ?? 0)),
            'remaining_enemy_count' => max(0, (int) ($runtimeResult['remaining_enemy_count'] ?? 0)),
            'cleared_wave_count' => max(0, (int) ($runtimeResult['cleared_wave_count'] ?? 0)),
            'is_settled' => false,
            'settled_at' => null,
        ]);

        return $this->success([
            'battle_id' => (string) $record->battle_id,
            'battle_result' => (string) $record->battle_result,
            'persisted' => true,
            'record' => $record,
        ]);
    }

    public function markSettled(BattleResult|string $battleResult): array
    {
        $record = $battleResult instanceof BattleResult
            ? $battleResult
            : BattleResult::query()->where('battle_id', trim((string) $battleResult))->first();

        if (! $record instanceof BattleResult) {
            return $this->failure('battle result not found');
        }

        if ((bool) $record->is_settled) {
            return $this->failure('battle already settled', [
                'battle_id' => (string) $record->battle_id,
                'settled' => true,
            ]);
        }

        $record->forceFill([
            'is_settled' => true,
            'settled_at' => Carbon::now(),
        ])->save();

        return $this->success([
            'battle_id' => (string) $record->battle_id,
            'settled' => true,
        ]);
    }

    private function nullableString(mixed $value): ?string
    {
        $safeValue = trim((string) $value);

        return $safeValue !== '' ? $safeValue : null;
    }

    private function success(array $data): array
    {
        return [
            'ok' => true,
            'reason' => null,
            'data' => $data,
        ];
    }

    private function failure(string $reason, ?array $data = null): array
    {
        return [
            'ok' => false,
            'reason' => $reason,
            'data' => $data,
        ];
    }
}
