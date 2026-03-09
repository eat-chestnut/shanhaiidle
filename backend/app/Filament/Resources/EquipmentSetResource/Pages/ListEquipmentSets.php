<?php

namespace App\Filament\Resources\EquipmentSetResource\Pages;

use App\Filament\Resources\EquipmentSetResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListEquipmentSets extends ListRecords
{
    protected static string $resource = EquipmentSetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('exportEquipmentSets')
                ->label('导出 equipment_sets.json')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action('exportEquipmentSets'),
        ];
    }

    public function exportEquipmentSets(): ?BinaryFileResponse
    {
        Artisan::call('game:export-equipment-sets');

        $path = storage_path('app/exports/equipment_sets.json');
        if (! is_file($path)) {
            Notification::make()
                ->title('导出失败')
                ->body('未找到导出文件：storage/app/exports/equipment_sets.json')
                ->danger()
                ->send();

            return null;
        }

        Notification::make()
            ->title('导出成功')
            ->body('已生成：storage/app/exports/equipment_sets.json')
            ->success()
            ->send();

        return response()->download($path, 'equipment_sets.json', [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }
}

