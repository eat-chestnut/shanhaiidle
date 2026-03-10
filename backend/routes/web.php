<?php

use App\Models\AppSetting;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;

Route::get('/', function () {
    return view('welcome');
});

$serveLatestBundleFile = function (string $file) {
    $allowed = [
        'manifest.json',
        'stages_v1.json',
        'items.json',
        'equip_templates.json',
        'equipment_sets.json',
        'equip_slots_v1.json',
        'equipment_growth_rules_v1.json',
        'blue_gear_templates_v1.json',
        'blue_affix_pool_v1.json',
        'purple_affix_pool_v1.json',
        'gem_catalog_v1.json',
        'material_catalog_v1.json',
        'material_dungeons_v1.json',
        'crafting_recipes_v1.json',
        'monsters.json',
        'skills_catalog.json',
        'battle_defaults.json',
    ];
    abort_unless(in_array($file, $allowed, true), 404);

    $bundleId = trim((string) AppSetting::getValue('latest_bundle_id', ''));
    abort_if($bundleId === '', 404);

    $path = storage_path("app/exports/bundles/{$bundleId}/{$file}");
    abort_unless(File::exists($path), 404);

    return response(File::get($path), 200, [
        'Content-Type' => 'application/json; charset=utf-8',
        'Cache-Control' => 'no-cache',
    ]);
};

Route::get('/bundles/latest/manifest.json', function () use ($serveLatestBundleFile) {
    return $serveLatestBundleFile('manifest.json');
});

Route::get('/bundles/latest/{file}', function (string $file) use ($serveLatestBundleFile) {
    return $serveLatestBundleFile($file);
})->where('file', 'stages_v1\.json|items\.json|equip_templates\.json|equipment_sets\.json|equip_slots_v1\.json|equipment_growth_rules_v1\.json|blue_gear_templates_v1\.json|blue_affix_pool_v1\.json|purple_affix_pool_v1\.json|gem_catalog_v1\.json|material_catalog_v1\.json|material_dungeons_v1\.json|crafting_recipes_v1\.json|monsters\.json|skills_catalog\.json|battle_defaults\.json');

Route::get('/stages_v1.json', function () {
    $path = storage_path('app/exports/stages_v1.json');
    abort_unless(File::exists($path), 404);
    return response(File::get($path), 200, [
        'Content-Type' => 'application/json; charset=utf-8',
        'Cache-Control' => 'no-cache',
    ]);
});

Route::get('/items.json', function () {
    $path = storage_path('app/exports/items.json');
    abort_unless(File::exists($path), 404);
    return response(File::get($path), 200, [
        'Content-Type' => 'application/json; charset=utf-8',
        'Cache-Control' => 'no-cache',
    ]);
});

Route::get('/equip_templates.json', function () {
    $path = storage_path('app/exports/equip_templates.json');
    abort_unless(File::exists($path), 404);
    return response(File::get($path), 200, [
        'Content-Type' => 'application/json; charset=utf-8',
        'Cache-Control' => 'no-cache',
    ]);
});

Route::get('/equipment_sets.json', function () {
    $path = storage_path('app/exports/equipment_sets.json');
    abort_unless(File::exists($path), 404);
    return response(File::get($path), 200, [
        'Content-Type' => 'application/json; charset=utf-8',
        'Cache-Control' => 'no-cache',
    ]);
});

Route::get('/equip_slots_v1.json', function () {
    $path = storage_path('app/exports/equip_slots_v1.json');
    abort_unless(File::exists($path), 404);
    return response(File::get($path), 200, [
        'Content-Type' => 'application/json; charset=utf-8',
        'Cache-Control' => 'no-cache',
    ]);
});

Route::get('/equipment_growth_rules_v1.json', function () {
    $path = storage_path('app/exports/equipment_growth_rules_v1.json');
    abort_unless(File::exists($path), 404);
    return response(File::get($path), 200, [
        'Content-Type' => 'application/json; charset=utf-8',
        'Cache-Control' => 'no-cache',
    ]);
});

Route::get('/blue_gear_templates_v1.json', function () {
    $path = storage_path('app/exports/blue_gear_templates_v1.json');
    abort_unless(File::exists($path), 404);
    return response(File::get($path), 200, [
        'Content-Type' => 'application/json; charset=utf-8',
        'Cache-Control' => 'no-cache',
    ]);
});

Route::get('/blue_affix_pool_v1.json', function () {
    $path = storage_path('app/exports/blue_affix_pool_v1.json');
    abort_unless(File::exists($path), 404);
    return response(File::get($path), 200, [
        'Content-Type' => 'application/json; charset=utf-8',
        'Cache-Control' => 'no-cache',
    ]);
});

Route::get('/purple_affix_pool_v1.json', function () {
    $path = storage_path('app/exports/purple_affix_pool_v1.json');
    abort_unless(File::exists($path), 404);
    return response(File::get($path), 200, [
        'Content-Type' => 'application/json; charset=utf-8',
        'Cache-Control' => 'no-cache',
    ]);
});

Route::get('/gem_catalog_v1.json', function () {
    $path = storage_path('app/exports/gem_catalog_v1.json');
    abort_unless(File::exists($path), 404);
    return response(File::get($path), 200, [
        'Content-Type' => 'application/json; charset=utf-8',
        'Cache-Control' => 'no-cache',
    ]);
});

Route::get('/material_catalog_v1.json', function () {
    $path = storage_path('app/exports/material_catalog_v1.json');
    abort_unless(File::exists($path), 404);
    return response(File::get($path), 200, [
        'Content-Type' => 'application/json; charset=utf-8',
        'Cache-Control' => 'no-cache',
    ]);
});

Route::get('/material_dungeons_v1.json', function () {
    $path = storage_path('app/exports/material_dungeons_v1.json');
    abort_unless(File::exists($path), 404);
    return response(File::get($path), 200, [
        'Content-Type' => 'application/json; charset=utf-8',
        'Cache-Control' => 'no-cache',
    ]);
});

Route::get('/crafting_recipes_v1.json', function () {
    $path = storage_path('app/exports/crafting_recipes_v1.json');
    abort_unless(File::exists($path), 404);
    return response(File::get($path), 200, [
        'Content-Type' => 'application/json; charset=utf-8',
        'Cache-Control' => 'no-cache',
    ]);
});

Route::get('/monsters.json', function () {
    $path = storage_path('app/exports/monsters.json');
    abort_unless(File::exists($path), 404);
    return response(File::get($path), 200, [
        'Content-Type' => 'application/json; charset=utf-8',
        'Cache-Control' => 'no-cache',
    ]);
});

Route::get('/skills_catalog.json', function () {
    $path = storage_path('app/exports/skills_catalog.json');
    abort_unless(File::exists($path), 404);
    return response(File::get($path), 200, [
        'Content-Type' => 'application/json; charset=utf-8',
        'Cache-Control' => 'no-cache',
    ]);
});

Route::get('/battle_defaults.json', function () {
    $path = storage_path('app/exports/battle_defaults.json');
    abort_unless(File::exists($path), 404);
    return response(File::get($path), 200, [
        'Content-Type' => 'application/json; charset=utf-8',
        'Cache-Control' => 'no-cache',
    ]);
});
