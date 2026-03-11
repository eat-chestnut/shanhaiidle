<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('equip_templates')) {
            $rows = DB::table('equip_templates')
                ->select(['id', 'white_stats', 'star_growth', 'socket_rule_ref'])
                ->get();

            foreach ($rows as $row) {
                DB::table('equip_templates')
                    ->where('id', $row->id)
                    ->update([
                        'white_stats' => json_encode($this->normalizeStatEntries($row->white_stats), JSON_UNESCAPED_UNICODE),
                        'star_growth' => json_encode($this->normalizeStatEntries($row->star_growth), JSON_UNESCAPED_UNICODE),
                        'socket_rule_ref' => $this->normalizeSocketRule($row->socket_rule_ref),
                    ]);
            }
        }

        Schema::table('equip_templates', function (Blueprint $table): void {
            $drops = [];

            foreach ([
                'main_stat',
                'main_min',
                'main_max',
                'unidentified_chance',
                'effects',
            ] as $column) {
                if (Schema::hasColumn('equip_templates', $column)) {
                    $drops[] = $column;
                }
            }

            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }

    public function down(): void
    {
        Schema::table('equip_templates', function (Blueprint $table): void {
            if (! Schema::hasColumn('equip_templates', 'main_stat')) {
                $table->string('main_stat', 64)->nullable()->after('rarity');
            }
            if (! Schema::hasColumn('equip_templates', 'main_min')) {
                $table->unsignedInteger('main_min')->default(0)->after('main_stat');
            }
            if (! Schema::hasColumn('equip_templates', 'main_max')) {
                $table->unsignedInteger('main_max')->default(0)->after('main_min');
            }
            if (! Schema::hasColumn('equip_templates', 'unidentified_chance')) {
                $table->decimal('unidentified_chance', 6, 3)->default(0)->after('main_max');
            }
            if (! Schema::hasColumn('equip_templates', 'effects')) {
                $table->json('effects')->nullable()->after('blueprint_item_id');
            }
        });
    }

    private function normalizeStatEntries(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $raw = $decoded;
            }
        }

        if (! is_array($raw)) {
            return [];
        }

        $rows = [];
        $indexes = [];

        foreach ($raw as $key => $value) {
            if (is_array($value)) {
                $stat = trim((string) ($value['stat'] ?? ''));
                $numericValue = $value['value'] ?? null;
            } else {
                $stat = trim((string) $key);
                $numericValue = $value;
            }

            if ($stat === '' || ! is_numeric($numericValue)) {
                continue;
            }

            $normalized = [
                'stat' => $stat,
                'value' => max(0, (int) $numericValue),
            ];

            if (array_key_exists($stat, $indexes)) {
                $rows[$indexes[$stat]] = $normalized;

                continue;
            }

            $indexes[$stat] = count($rows);
            $rows[] = $normalized;
        }

        return array_values($rows);
    }

    private function normalizeSocketRule(mixed $raw): string
    {
        $value = trim((string) $raw);

        return match ($value) {
            '', 'main_equipment_default', 'auto_star_3_6_8_10' => 'fixed_star_3_6_8_10',
            default => $value,
        };
    }
};
