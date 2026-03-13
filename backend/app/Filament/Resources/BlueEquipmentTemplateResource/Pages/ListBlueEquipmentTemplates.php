<?php

namespace App\Filament\Resources\BlueEquipmentTemplateResource\Pages;

use App\Filament\Resources\BlueEquipmentTemplateResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListBlueEquipmentTemplates extends ListRecords
{
    protected static string $resource = BlueEquipmentTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('exportBlueEquipmentTemplates')
                ->label('导出 blue_equipment_templates_v1.json')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action('exportBlueEquipmentTemplates'),
        ];
    }

    public function exportBlueEquipmentTemplates(): ?BinaryFileResponse
    {
        Artisan::call('game:export-blue-equipment-templates');

        $path = storage_path('app/exports/blue_equipment_templates_v1.json');
        if (! is_file($path)) {
            Notification::make()
                ->title('导出失败')
                ->body('未找到导出文件：storage/app/exports/blue_equipment_templates_v1.json')
                ->danger()
                ->send();

            return null;
        }

        Notification::make()
            ->title('导出成功')
            ->body('已生成：storage/app/exports/blue_equipment_templates_v1.json')
            ->success()
            ->send();

        return response()->download($path, 'blue_equipment_templates_v1.json', [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }
}
