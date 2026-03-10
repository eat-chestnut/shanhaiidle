<?php

namespace App\Console\Commands;

use App\Models\EquipSetting;
use App\Models\EquipTemplate;
use App\Services\ExportMetaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExportEquipTemplatesJson extends Command
{
    private const DEFAULT_SOCKET_WEIGHTS = [
        '0' => 60,
        '1' => 25,
        '2' => 10,
        '3' => 4,
        '4' => 1,
    ];

    protected $signature = 'game:export-equip-templates';

    protected $description = 'Export enabled equip templates to storage/app/exports/equip_templates.json';

    public function handle(): int
    {
        $socketWeights = $this->loadSocketWeights();

        $templates = EquipTemplate::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (EquipTemplate $tpl): array {
                $effects = is_array($tpl->effects) ? array_values($tpl->effects) : [];
                $whiteStats = $this->normalizeStatMap($tpl->white_stats);
                if ($whiteStats === [] && filled($tpl->main_stat)) {
                    $whiteStats = [
                        (string) $tpl->main_stat => (int) $tpl->main_max,
                    ];
                }
                $starGrowth = $this->normalizeStatMap($tpl->star_growth);
                $row = [
                    'id' => (string) $tpl->id,
                    'name' => (string) $tpl->name,
                    'slot' => (string) $tpl->slot,
                    'equip_type' => (string) ($tpl->equip_type ?? 'set'),
                    'rarity' => (string) $tpl->rarity,
                    'required_level' => (int) ($tpl->required_level ?? 1),
                    'main_stat' => (string) $tpl->main_stat,
                    'main_min' => (int) $tpl->main_min,
                    'main_max' => (int) $tpl->main_max,
                    'white_stats' => $whiteStats,
                    'star_growth' => $starGrowth,
                    'star_enabled' => (bool) ($tpl->star_enabled ?? true),
                    'star_cap' => (int) ($tpl->star_cap ?? 10),
                    'can_attach_blue_affix' => (bool) ($tpl->can_attach_blue_affix ?? false),
                    'can_roll_purple_affix' => (bool) ($tpl->can_roll_purple_affix ?? false),
                    'unidentified_chance' => round((float) ($tpl->unidentified_chance ?? 0), 3),
                    'icon' => (string) ($tpl->icon ?? ''),
                    'set_line_id' => (string) ($tpl->set_line_id ?? ''),
                    'set_stage' => (int) ($tpl->set_stage ?? 0),
                    'flow_tag' => (string) ($tpl->flow_tag ?? ''),
                    'socket_rule_ref' => (string) ($tpl->socket_rule_ref ?? ''),
                    'quality_tier' => (string) ($tpl->quality_tier ?? 'normal'),
                    'forge_enabled' => (bool) ($tpl->forge_enabled ?? false),
                    'forge_tier' => (string) ($tpl->forge_tier ?? ''),
                    'slot_group' => (string) ($tpl->slot_group ?? ''),
                    'theme_key' => (string) ($tpl->theme_key ?? ''),
                    'forge_family_id' => (string) ($tpl->forge_family_id ?? ''),
                    'upgrade_from_template_id' => (string) ($tpl->upgrade_from_template_id ?? ''),
                    'upgrade_to_template_id' => (string) ($tpl->upgrade_to_template_id ?? ''),
                    'blueprint_item_id' => (string) ($tpl->blueprint_item_id ?? ''),
                    'effects' => $effects,
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
            'socket_weights' => $this->toSocketWeightsObject($socketWeights),
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

    private function loadSocketWeights(): array
    {
        $setting = EquipSetting::query()->find('socket_weights');
        $value = $setting?->value;

        if (! is_array($value)) {
            return [
                0 => self::DEFAULT_SOCKET_WEIGHTS['0'],
                1 => self::DEFAULT_SOCKET_WEIGHTS['1'],
                2 => self::DEFAULT_SOCKET_WEIGHTS['2'],
                3 => self::DEFAULT_SOCKET_WEIGHTS['3'],
                4 => self::DEFAULT_SOCKET_WEIGHTS['4'],
            ];
        }

        $weights = [
            0 => max(0, (int) ($value[0] ?? $value['0'] ?? self::DEFAULT_SOCKET_WEIGHTS['0'])),
            1 => max(0, (int) ($value[1] ?? $value['1'] ?? self::DEFAULT_SOCKET_WEIGHTS['1'])),
            2 => max(0, (int) ($value[2] ?? $value['2'] ?? self::DEFAULT_SOCKET_WEIGHTS['2'])),
            3 => max(0, (int) ($value[3] ?? $value['3'] ?? self::DEFAULT_SOCKET_WEIGHTS['3'])),
            4 => max(0, (int) ($value[4] ?? $value['4'] ?? self::DEFAULT_SOCKET_WEIGHTS['4'])),
        ];

        if (array_sum($weights) > 0) {
            return $weights;
        }

        return [
            0 => self::DEFAULT_SOCKET_WEIGHTS['0'],
            1 => self::DEFAULT_SOCKET_WEIGHTS['1'],
            2 => self::DEFAULT_SOCKET_WEIGHTS['2'],
            3 => self::DEFAULT_SOCKET_WEIGHTS['3'],
            4 => self::DEFAULT_SOCKET_WEIGHTS['4'],
        ];
    }

    private function toSocketWeightsObject(array $weights): object
    {
        $obj = new \stdClass();
        $obj->{'0'} = (int) ($weights[0] ?? self::DEFAULT_SOCKET_WEIGHTS['0']);
        $obj->{'1'} = (int) ($weights[1] ?? self::DEFAULT_SOCKET_WEIGHTS['1']);
        $obj->{'2'} = (int) ($weights[2] ?? self::DEFAULT_SOCKET_WEIGHTS['2']);
        $obj->{'3'} = (int) ($weights[3] ?? self::DEFAULT_SOCKET_WEIGHTS['3']);
        $obj->{'4'} = (int) ($weights[4] ?? self::DEFAULT_SOCKET_WEIGHTS['4']);

        return $obj;
    }

    private function normalizeStatMap(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $key => $value) {
            $k = trim((string) $key);
            if ($k === '') {
                continue;
            }
            if (! is_numeric($value)) {
                continue;
            }
            $out[$k] = (int) $value;
        }

        return $out;
    }
}
