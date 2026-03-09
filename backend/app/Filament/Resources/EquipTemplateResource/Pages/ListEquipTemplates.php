<?php

namespace App\Filament\Resources\EquipTemplateResource\Pages;

use App\Filament\Resources\EquipTemplateResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListEquipTemplates extends ListRecords
{
    protected static string $resource = EquipTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('exportEquipTemplates')
                ->label('导出 equip_templates.json')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action('exportEquipTemplates'),
        ];
    }

    public function exportEquipTemplates(): ?BinaryFileResponse
    {
        Artisan::call('game:export-equip-templates');

        $path = storage_path('app/exports/equip_templates.json');
        if (! is_file($path)) {
            Notification::make()
                ->title('导出失败')
                ->body('未找到导出文件：storage/app/exports/equip_templates.json')
                ->danger()
                ->send();

            return null;
        }

        Notification::make()
            ->title('导出成功')
            ->body('已生成：storage/app/exports/equip_templates.json')
            ->success()
            ->send();

        return response()->download($path, 'equip_templates.json', [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }
}
