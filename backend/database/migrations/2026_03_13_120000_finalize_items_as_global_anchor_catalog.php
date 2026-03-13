<?php

use App\Models\Item;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table): void {
            if (! Schema::hasColumn('items', 'item_id')) {
                $table->string('item_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('items', 'item_name')) {
                $table->string('item_name')->nullable()->after('item_id');
            }
            if (! Schema::hasColumn('items', 'display_name')) {
                $table->string('display_name')->nullable()->after('item_name');
            }
            if (! Schema::hasColumn('items', 'main_type')) {
                $table->string('main_type')->nullable()->after('display_name');
            }
            if (! Schema::hasColumn('items', 'quality')) {
                $table->string('quality')->nullable()->after('sub_type');
            }
            if (! Schema::hasColumn('items', 'is_stackable')) {
                $table->boolean('is_stackable')->default(true)->after('desc');
            }
            if (! Schema::hasColumn('items', 'max_stack')) {
                $table->unsignedInteger('max_stack')->default(9999)->after('is_stackable');
            }
            if (! Schema::hasColumn('items', 'remark')) {
                $table->text('remark')->nullable()->after('sort_order');
            }
            if (! Schema::hasColumn('items', 'source_library')) {
                $table->string('source_library')->nullable()->after('remark');
            }
            if (! Schema::hasColumn('items', 'required_level')) {
                $table->unsignedInteger('required_level')->default(1)->after('source_library');
            }
            if (! Schema::hasColumn('items', 'bind_type')) {
                $table->string('bind_type')->default('none')->after('required_level');
            }
            if (! Schema::hasColumn('items', 'sell_price')) {
                $table->unsignedBigInteger('sell_price')->default(0)->after('bind_type');
            }
            if (! Schema::hasColumn('items', 'use_type')) {
                $table->string('use_type')->default('none')->after('sell_price');
            }
            if (! Schema::hasColumn('items', 'rarity_frame_key')) {
                $table->string('rarity_frame_key')->nullable()->after('use_type');
            }
        });

        foreach (DB::table('items')->get() as $rowObject) {
            $row = (array) $rowObject;
            $legacyId = trim((string) ($row['id'] ?? ''));
            if ($legacyId === '') {
                continue;
            }

            $legacyName = trim((string) ($row['name'] ?? $legacyId));
            $legacyType = trim((string) ($row['type'] ?? 'material'));
            $legacySubType = trim((string) ($row['sub_type'] ?? ''));
            $legacyMaterialType = trim((string) ($row['material_type'] ?? ''));
            $legacyRarity = static::normalizeRarity((string) ($row['rarity'] ?? 'white'));
            $stackLimit = max(1, (int) ($row['stack_limit'] ?? 9999));

            DB::table('items')
                ->where('id', $legacyId)
                ->update([
                    'item_id' => $legacyId,
                    'item_name' => $legacyName,
                    'display_name' => $legacyName,
                    'main_type' => static::mapMainType($legacyType, $legacySubType, $legacyMaterialType),
                    'quality' => $legacyRarity,
                    'rarity' => $legacyRarity,
                    'is_stackable' => $stackLimit > 1,
                    'max_stack' => $stackLimit,
                    'source_library' => 'legacy_catalog_import',
                    'required_level' => max(1, (int) ($row['drop_unlock_level'] ?? 1)),
                    'bind_type' => 'none',
                    'sell_price' => 0,
                    'use_type' => static::mapUseType($legacyType, $legacySubType),
                ]);
        }

        if (! static::hasIndex('items', 'items_item_id_unique')) {
            Schema::table('items', function (Blueprint $table): void {
                $table->unique('item_id');
            });
        }
    }

    public function down(): void
    {
        if (static::hasIndex('items', 'items_item_id_unique')) {
            Schema::table('items', function (Blueprint $table): void {
                $table->dropUnique('items_item_id_unique');
            });
        }

        Schema::table('items', function (Blueprint $table): void {
            foreach ([
                'item_id',
                'item_name',
                'display_name',
                'main_type',
                'quality',
                'is_stackable',
                'max_stack',
                'remark',
                'source_library',
                'required_level',
                'bind_type',
                'sell_price',
                'use_type',
                'rarity_frame_key',
            ] as $column) {
                if (Schema::hasColumn('items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private static function mapMainType(string $legacyType, string $legacySubType, string $legacyMaterialType): string
    {
        return match ($legacyType) {
            'currency' => 'currency',
            'material' => 'material',
            'gem' => 'gem',
            'blueprint' => 'blueprint',
            'blueprint_fragment' => 'blueprint_fragment',
            'item' => match (true) {
                $legacySubType === 'pack' => 'gift_pack',
                $legacySubType === 'token' => 'currency',
                default => 'consumable',
            },
            default => 'material',
        };
    }

    private static function mapUseType(string $legacyType, string $legacySubType): string
    {
        return match ($legacyType) {
            'gem' => 'embed',
            'material' => 'craft_material',
            'blueprint', 'blueprint_fragment' => 'craft_material',
            'item' => match (true) {
                $legacySubType === 'pack' => 'open_pack',
                default => 'consume_reward',
            },
            default => 'none',
        };
    }

    private static function normalizeRarity(string $rarity): string
    {
        $rarity = trim($rarity) !== '' ? trim($rarity) : 'white';

        return $rarity === 'orange' ? 'red' : $rarity;
    }

    private static function hasIndex(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        return match ($driver) {
            'sqlite' => (bool) DB::selectOne(
                "select name from sqlite_master where type = 'index' and tbl_name = ? and name = ? limit 1",
                [$table, $index],
            ),
            'mysql' => (bool) DB::selectOne(
                'select index_name from information_schema.statistics where table_schema = database() and table_name = ? and index_name = ? limit 1',
                [$table, $index],
            ),
            default => false,
        };
    }
};
