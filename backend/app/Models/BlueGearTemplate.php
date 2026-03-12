<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlueGearTemplate extends Model
{
    protected $table = 'blue_gear_templates';

    protected $fillable = [
        'template_id',
        'name',
        'blue_pool_id',
        'slot_id',
        'required_level',
        'white_stats',
        'affix_count',
        'min_affix_count',
        'max_affix_count',
        'affix_pool_tags',
        'affix_entries',
        'icon',
        'sort_order',
        'is_enabled',
    ];

    protected $casts = [
        'required_level' => 'integer',
        'white_stats' => 'array',
        'affix_count' => 'integer',
        'min_affix_count' => 'integer',
        'max_affix_count' => 'integer',
        'affix_pool_tags' => 'array',
        'affix_entries' => 'array',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    public function setWhiteStatsAttribute(mixed $value): void
    {
        if (! is_array($value)) {
            $this->attributes['white_stats'] = json_encode([], JSON_UNESCAPED_UNICODE);

            return;
        }

        $isSequential = array_is_list($value);

        $rows = collect($value)
            ->map(function (mixed $row, mixed $key) use ($isSequential): ?array {
                if ($isSequential) {
                    if (! is_array($row)) {
                        return null;
                    }

                    $stat = trim((string) ($row['stat'] ?? ''));
                    if ($stat === '') {
                        return null;
                    }

                    return [
                        'stat' => $stat,
                        'value' => (int) ($row['value'] ?? 0),
                    ];
                }

                $stat = trim((string) $key);
                if ($stat === '') {
                    return null;
                }

                return [
                    'stat' => $stat,
                    'value' => (int) $row,
                ];
            })
            ->filter()
            ->values();

        $stats = [];
        foreach ($rows as $row) {
            $stats[$row['stat']] = (int) $row['value'];
        }

        $this->attributes['white_stats'] = json_encode($stats, JSON_UNESCAPED_UNICODE);
    }

    public function setAffixEntriesAttribute(mixed $value): void
    {
        $rows = collect(is_array($value) ? $value : [])
            ->map(function (mixed $row): ?array {
                if (! is_array($row)) {
                    return null;
                }

                $affixId = trim((string) ($row['affix_id'] ?? ''));
                if ($affixId === '') {
                    return null;
                }

                return [
                    'affix_id' => $affixId,
                    'weight' => max(0, (int) ($row['weight'] ?? 0)),
                ];
            })
            ->filter()
            ->values()
            ->all();

        $this->attributes['affix_entries'] = json_encode($rows, JSON_UNESCAPED_UNICODE);
    }

    public function resolvedAffixEntries(): array
    {
        return collect(is_array($this->affix_entries) ? $this->affix_entries : [])
            ->map(function (mixed $row): ?array {
                if (! is_array($row)) {
                    return null;
                }

                $affixId = trim((string) ($row['affix_id'] ?? ''));
                if ($affixId === '') {
                    return null;
                }

                return [
                    'affix_id' => $affixId,
                    'weight' => max(0, (int) ($row['weight'] ?? 0)),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function resolvedMinAffixCount(): int
    {
        return max(0, (int) ($this->min_affix_count ?? $this->affix_count ?? 0));
    }

    public function resolvedMaxAffixCount(): int
    {
        return max($this->resolvedMinAffixCount(), (int) ($this->max_affix_count ?? $this->affix_count ?? 0));
    }
}
