<?php

namespace App\Console\Commands;

use App\Models\BlueEquipmentTemplate;
use App\Support\BlueEquipmentTemplateModuleSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExportBlueEquipmentTemplatesJson extends Command
{
    private const PROJECT_DATA_FILE = '../data/blue_equipment_templates_v1.json';

    protected $signature = 'game:export-blue-equipment-templates';

    protected $description = 'Export enabled blue equipment templates to storage/app/exports/blue_equipment_templates_v1.json';

    public function handle(): int
    {
        $templates = BlueEquipmentTemplate::query()
            ->with(['baseStats' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('template_id')
            ->get()
            ->map(fn (BlueEquipmentTemplate $template): array => BlueEquipmentTemplateModuleSupport::exportTemplate($template))
            ->values()
            ->all();

        $payload = [
            'version' => BlueEquipmentTemplateModuleSupport::VERSION,
            'module' => BlueEquipmentTemplateModuleSupport::MODULE,
            'rules' => BlueEquipmentTemplateModuleSupport::rulesPayload(),
            'templates' => $templates,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('blue_equipment_templates_v1.json 序列化失败。');
        }

        $dir = storage_path('app/exports');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'blue_equipment_templates_v1.json';
        $projectDataPath = base_path(self::PROJECT_DATA_FILE);
        File::put($path, $json);
        File::put($projectDataPath, $json);

        $this->info(sprintf('Exported %d blue equipment templates -> %s, %s', count($templates), $path, $projectDataPath));

        return self::SUCCESS;
    }
}
