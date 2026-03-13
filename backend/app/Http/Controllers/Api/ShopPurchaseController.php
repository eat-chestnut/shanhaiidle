<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ShopPurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopPurchaseController extends Controller
{
    public function __invoke(Request $request, ShopPurchaseService $service): JsonResponse
    {
        $payload = $request->validate([
            'player_id' => ['required', 'string', 'max:64'],
            'goods_id' => ['required', 'string', 'max:64'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:1'],
        ]);

        $result = $service->purchase(
            (string) $payload['player_id'],
            (string) $payload['goods_id'],
            (int) ($payload['quantity'] ?? 1),
            'player',
        );

        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'success' => false,
                'reason' => (string) ($result['reason'] ?? 'purchase_failed'),
                'updated_currencies' => $result['updated_currencies'] ?? null,
                'remaining_limits' => $result['remaining_limits'] ?? null,
                'detail' => $result,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'goods_id' => $result['goods_id'],
            'shop_tab' => $result['shop_tab'],
            'goods_type' => $result['goods_type'],
            'granted_items' => $result['granted_items'],
            'updated_currencies' => $result['updated_currencies'],
            'remaining_limits' => $result['remaining_limits'],
            'profile' => $result['profile'],
        ]);
    }
}
