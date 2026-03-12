<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlayerProfileSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerProfileSyncController extends Controller
{
    public function __invoke(Request $request, PlayerProfileSyncService $service): JsonResponse
    {
        $payload = $request->validate([
            'player_id' => ['required', 'string', 'max:64'],
            'nickname' => ['nullable', 'string', 'max:64'],
            'level' => ['nullable', 'integer', 'min:1'],
            'exp' => ['nullable', 'integer', 'min:0'],
            'gold' => ['nullable', 'integer', 'min:0'],
            'crystal' => ['nullable', 'integer', 'min:0'],
            'contribution' => ['nullable', 'integer', 'min:0'],
            'free_attr_points' => ['nullable', 'integer', 'min:0'],
            'skill_points' => ['nullable', 'integer', 'min:0'],
            'current_stage_id' => ['nullable', 'string', 'max:64'],
            'current_difficulty' => ['nullable', 'integer', 'min:0'],
            'highest_cleared_stage_id' => ['nullable', 'string', 'max:64'],
            'highest_cleared_difficulty' => ['nullable', 'integer', 'min:0'],
            'current_sect_id' => ['nullable', 'string', 'max:64'],
            'attrs_json' => ['nullable', 'array'],
            'inventory' => ['nullable', 'array'],
            'equipment' => ['nullable', 'array'],
            'claimed_milestones' => ['nullable', 'array'],
            'patrol_summary' => ['nullable', 'array'],
            'task_summary' => ['nullable', 'array'],
        ]);

        $profile = $service->upsertSnapshot((string) $payload['player_id'], $payload);

        return response()->json([
            'success' => true,
            'profile' => [
                'player_id' => (string) $profile->player_id,
                'level' => (int) $profile->level,
                'updated_at' => $profile->updated_at?->toDateTimeString(),
            ],
        ]);
    }
}
