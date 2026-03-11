<?php

namespace App\Filament\Resources\BlueGearTemplateResource\Pages;

use App\Filament\Resources\BlueGearTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBlueGearTemplate extends CreateRecord
{
    protected static string $resource = BlueGearTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return static::normalizeData($data);
    }

    private static function normalizeData(array $data): array
    {
        $data['white_stats'] = collect(is_array($data['white_stats'] ?? null) ? $data['white_stats'] : [])
            ->map(function (mixed $row): ?array {
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
            })
            ->filter()
            ->values()
            ->all();

        $data['min_affix_count'] = max(0, (int) ($data['min_affix_count'] ?? $data['affix_count'] ?? 0));
        $data['max_affix_count'] = max($data['min_affix_count'], (int) ($data['max_affix_count'] ?? $data['affix_count'] ?? $data['min_affix_count']));
        $data['affix_count'] = $data['max_affix_count'];

        return $data;
    }
}
