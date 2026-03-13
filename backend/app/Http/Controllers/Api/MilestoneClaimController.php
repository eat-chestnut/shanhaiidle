<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MilestonePlayerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MilestoneClaimController extends Controller
{
    public function __invoke(Request $request, MilestonePlayerService $service): JsonResponse
    {
        $payload = $request->validate([
            'player_id' => ['required', 'string', 'max:64'],
            'milestone_id' => ['required', 'string', 'max:64'],
        ]);

        $result = $service->claim(
            (string) $payload['player_id'],
            (string) $payload['milestone_id'],
        );

        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'success' => false,
                'reason' => (string) ($result['reason'] ?? 'milestone_claim_failed'),
                'updated_currencies' => $result['updated_currencies'] ?? null,
                'detail' => $result,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'milestone_id' => $result['milestone_id'],
            'granted_items' => $result['granted_items'],
            'updated_currencies' => $result['updated_currencies'],
            'milestone_state' => $result['milestone_state'],
            'summary' => $result['summary'],
            'profile' => $result['profile'],
        ]);
    }
}
