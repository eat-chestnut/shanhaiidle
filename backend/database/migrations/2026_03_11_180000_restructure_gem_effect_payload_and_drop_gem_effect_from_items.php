<?php

use App\Support\GemEffectRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('items')) {
            return;
        }

        $hasGemEffect = Schema::hasColumn('items', 'gem_effect');
        $hasEffectPayload = Schema::hasColumn('items', 'effect_payload');
        if (! $hasGemEffect && ! $hasEffectPayload) {
            return;
        }

        $query = DB::table('items')->where('type', 'gem');
        $columns = ['id', 'sub_type', 'effect_type', 'target_scope'];
        if ($hasEffectPayload) {
            $columns[] = 'effect_payload';
        }
        if ($hasGemEffect) {
            $columns[] = 'gem_effect';
        }

        foreach ($query->get($columns) as $rowObject) {
            $row = (array) $rowObject;
            $subType = trim((string) ($row['sub_type'] ?? 'attr'));
            $targetScope = trim((string) ($row['target_scope'] ?? ''));
            $effectPayload = static::decodeJson($row['effect_payload'] ?? null);
            $gemEffect = static::decodeJson($row['gem_effect'] ?? null);

            $normalizedPayload = GemEffectRegistry::normalizeLegacyPayloadOrFail([
                'id' => (string) ($row['id'] ?? ''),
                'sub_type' => $subType,
                'effect_type' => (string) ($row['effect_type'] ?? ''),
                'target_scope' => $targetScope,
                'effect_payload' => $effectPayload,
                'gem_effect' => $gemEffect,
            ]);

            if ($subType === 'skill') {
                $targetScope = trim((string) ($targetScope !== '' ? $targetScope : (($effectPayload['skill_id'] ?? $gemEffect['skill_id'] ?? ''))));
            } else {
                $targetScope = GemEffectRegistry::defaultTargetScope('attr') ?? 'global';
            }

            DB::table('items')
                ->where('id', $row['id'])
                ->update([
                    'effect_type' => GemEffectRegistry::effectTypeForGemType($subType),
                    'target_scope' => $targetScope,
                    'effect_payload' => json_encode($normalizedPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
        }

        if ($hasGemEffect) {
            Schema::table('items', function (Blueprint $table): void {
                $table->dropColumn('gem_effect');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('items')) {
            return;
        }

        if (! Schema::hasColumn('items', 'gem_effect')) {
            Schema::table('items', function (Blueprint $table): void {
                $table->json('gem_effect')->nullable()->after('trait');
            });
        }

        foreach (DB::table('items')->where('type', 'gem')->get(['id', 'effect_payload']) as $rowObject) {
            $payload = static::decodeJson(((array) $rowObject)['effect_payload'] ?? null);
            $legacy = [];
            $effectCode = trim((string) ($payload['effect_code'] ?? ''));
            $params = $payload['params'] ?? [];
            if (is_array($params)) {
                if ($effectCode === GemEffectRegistry::ATTR_EFFECT_CODE) {
                    $legacy = [
                        'stat' => (string) ($params['stat'] ?? ''),
                        'value' => $params['value'] ?? 0,
                    ];
                } elseif ($effectCode !== '') {
                    $firstParamKey = array_key_first($params);
                    $legacy = [
                        'modifier' => $effectCode,
                        'value' => $firstParamKey !== null ? ($params[$firstParamKey] ?? 0) : 0,
                    ];
                }
            }

            DB::table('items')
                ->where('id', $rowObject->id)
                ->update([
                    'gem_effect' => $legacy === [] ? null : json_encode($legacy, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
        }
    }

    private static function decodeJson(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
};
