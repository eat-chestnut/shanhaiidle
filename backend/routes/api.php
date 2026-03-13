<?php

use App\Http\Controllers\Api\MilestoneClaimController;
use App\Http\Controllers\Api\PlayerProfileSyncController;
use App\Http\Controllers\Api\ShopPlayerProfileSyncController;
use App\Http\Controllers\Api\ShopPurchaseController;
use App\Http\Middleware\VerifyClientApiToken;
use Illuminate\Support\Facades\Route;

Route::middleware(VerifyClientApiToken::class)->group(function (): void {
    Route::post('/player/profile/sync', PlayerProfileSyncController::class);
    Route::post('/shop/profile/sync', ShopPlayerProfileSyncController::class);
    Route::post('/shop/purchase', ShopPurchaseController::class);
    Route::post('/milestones/claim', MilestoneClaimController::class);
});
