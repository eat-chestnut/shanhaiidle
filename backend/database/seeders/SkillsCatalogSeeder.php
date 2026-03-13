<?php

namespace Database\Seeders;

use App\Models\SkillCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class SkillsCatalogSeeder extends Seeder
{
    private const PROJECT_SKILLS_CATALOG_FILE = '../data/skills_catalog.json';

    public function run(): void
    {
        foreach ($this->seedRows() as $row) {
            $existing = SkillCatalog::query()->find($row['id']);
            if ($existing instanceof SkillCatalog && trim((string) ($existing->desc ?? '')) !== '' && trim((string) $row['desc']) === '') {
                $row['desc'] = (string) $existing->desc;
            }

            SkillCatalog::query()->updateOrCreate(
                ['id' => (string) $row['id']],
                $row,
            );
        }
    }

    private function seedRows(): array
    {
        $path = base_path(self::PROJECT_SKILLS_CATALOG_FILE);
        if (! File::exists($path)) {
            return [];
        }

        $decoded = json_decode((string) File::get($path), true);
        if (! is_array($decoded)) {
            return [];
        }

        $rows = $decoded['skills_catalog'] ?? [];
        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($row): ?array {
            if (! is_array($row)) {
                return null;
            }

            $id = trim((string) ($row['id'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            if ($id === '' || $name === '') {
                return null;
            }

            $base = [
                'id' => $id,
                'name' => $name,
                'desc' => trim((string) ($row['desc'] ?? '')),
                'sect' => trim((string) ($row['sect'] ?? '')),
                'class' => trim((string) ($row['class'] ?? '')),
                'type' => trim((string) ($row['type'] ?? 'active')) ?: 'active',
                'min_level' => max(1, (int) ($row['min_level'] ?? 1)),
                'max_level' => max(1, (int) ($row['max_level'] ?? 1)),
                'target_rule' => trim((string) ($row['target_rule'] ?? '')),
                'cd_sec' => (float) ($row['cd_sec'] ?? 0),
                'cost_qi' => max(0, (int) ($row['cost_qi'] ?? 0)),
                'cast_range' => array_key_exists('cast_range', $row) ? (($row['cast_range'] !== null) ? (float) $row['cast_range'] : null) : null,
                'tags' => array_values(array_filter(array_map(
                    static fn ($tag): string => trim((string) $tag),
                    is_array($row['tags'] ?? null) ? $row['tags'] : []
                ))),
                'sort_order' => max(0, (int) ($row['sort_order'] ?? 0)),
                'is_enabled' => (bool) ($row['is_enabled'] ?? true),
            ];

            unset(
                $row['id'],
                $row['name'],
                $row['desc'],
                $row['sect'],
                $row['class'],
                $row['type'],
                $row['min_level'],
                $row['max_level'],
                $row['target_rule'],
                $row['cd_sec'],
                $row['cost_qi'],
                $row['cast_range'],
                $row['tags'],
                $row['sort_order'],
                $row['is_enabled']
            );

            $base['runtime_blocks'] = $row;

            return $base;
        }, $rows)));
    }
}
