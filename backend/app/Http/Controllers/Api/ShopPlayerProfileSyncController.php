<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlayerProfileSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopPlayerProfileSyncController extends Controller
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
                'nickname' => (string) ($profile->nickname ?? ''),
                'level' => (int) $profile->level,
                'exp' => (int) $profile->exp,
                'gold' => (int) $profile->gold,
                'crystal' => (int) $profile->crystal,
                'contribution' => (int) $profile->contribution,
                'free_attr_points' => (int) $profile->free_attr_points,
                'skill_points' => (int) $profile->skill_points,
                'current_stage_id' => (string) ($profile->current_stage_id ?? ''),
                'current_difficulty' => (int) ($profile->current_difficulty ?? 0),
                'highest_cleared_stage_id' => (string) ($profile->highest_cleared_stage_id ?? ''),
                'highest_cleared_difficulty' => (int) ($profile->highest_cleared_difficulty ?? 0),
                'current_sect_id' => (string) ($profile->current_sect_id ?? ''),
                'attrs_json' => is_array($profile->attrs_json) ? $profile->attrs_json : [],
                'inventory' => is_array($profile->inventory) ? $profile->inventory : [],
                'equipment' => is_array($profile->equipment) ? $profile->equipment : [],
                'claimed_milestones' => is_array($profile->claimed_milestones) ? $profile->claimed_milestones : [],
                'patrol_summary' => is_array($profile->patrol_summary) ? $profile->patrol_summary : [],
                'task_summary' => is_array($profile->task_summary) ? $profile->task_summary : [],
            ],
        ]);
    }
}
