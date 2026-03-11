<?php

namespace App\Console\Commands;

use App\Models\EquipTemplate;
use App\Services\ExportMetaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExportEquipTemplatesJson extends Command
{
    protected $signature = 'game:export-equip-templates';

    protected $description = 'Export enabled equip templates to storage/app/exports/equip_templates.json';

    public function handle(): int
    {
        $templates = EquipTemplate::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (EquipTemplate $tpl): array {
                $row = [
                    'id' => (string) $tpl->id,
                    'name' => (string) $tpl->name,
                    'slot' => (string) $tpl->slot,
                    'equip_type' => (string) ($tpl->equip_type ?? 'set'),
                    'rarity' => (string) $tpl->rarity,
                    'required_level' => (int) ($tpl->required_level ?? 1),
                    'white_stats' => $this->normalizeStatEntries($tpl->white_stats),
                    'star_growth' => $this->normalizeStatEntries($tpl->star_growth),
                    'star_enabled' => (bool) ($tpl->star_enabled ?? true),
                    'star_cap' => (int) ($tpl->star_cap ?? 10),
                    'can_attach_blue_affix' => (bool) ($tpl->can_attach_blue_affix ?? false),
                    'can_roll_purple_affix' => (bool) ($tpl->can_roll_purple_affix ?? false),
                    'socket_rule_ref' => (string) ($tpl->socket_rule_ref ?: 'fixed_star_3_6_8_10'),
                    'icon' => (string) ($tpl->icon ?? ''),
                    'set_line_id' => (string) ($tpl->set_line_id ?? ''),
                    'set_stage' => (int) ($tpl->set_stage ?? 0),
                    'flow_tag' => (string) ($tpl->flow_tag ?? ''),
                    'quality_tier' => (string) ($tpl->quality_tier ?? 'normal'),
                    'forge_enabled' => (bool) ($tpl->forge_enabled ?? false),
                    'forge_tier' => (string) ($tpl->forge_tier ?? ''),
                    'slot_group' => (string) ($tpl->slot_group ?? ''),
                    'theme_key' => (string) ($tpl->theme_key ?? ''),
                    'forge_family_id' => (string) ($tpl->forge_family_id ?? ''),
                    'upgrade_from_template_id' => (string) ($tpl->upgrade_from_template_id ?? ''),
                    'upgrade_to_template_id' => (string) ($tpl->upgrade_to_template_id ?? ''),
                    'blueprint_item_id' => (string) ($tpl->blueprint_item_id ?? ''),
                ];
                $setId = trim((string) ($tpl->set_id ?? ''));
                if ($setId !== '') {
                    $row['set_id'] = $setId;
                }

                return $row;
            })
            ->values()
            ->all();

        $payloadWithoutMeta = [
            'equip_templates' => $templates,
        ];
        $version = ExportMetaService::getNextVersion('equip_templates');
        $meta = ExportMetaService::makeMeta('equip_templates', $version, $payloadWithoutMeta);
        $payload = ['meta' => $meta] + $payloadWithoutMeta;

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('equip_templates.json 序列化失败。');
        }

        $dir = storage_path('app/exports');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'equip_templates.json';
        File::put($path, $json);

        $this->info(sprintf('Exported %d equip templates -> %s (version=%d)', count($templates), $path, $version));

        return self::SUCCESS;
    }

    private function normalizeStatEntries(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $rows = [];
        $indexes = [];

        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }

            $stat = trim((string) ($row['stat'] ?? ''));
            if ($stat === '') {
                continue;
            }

            $normalized = [
                'stat' => $stat,
                'value' => max(0, (int) ($row['value'] ?? 0)),
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
}
