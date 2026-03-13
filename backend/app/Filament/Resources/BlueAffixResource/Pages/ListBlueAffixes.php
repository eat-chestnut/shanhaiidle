<?php

namespace App\Filament\Resources\BlueAffixResource\Pages;

use App\Filament\Resources\BlueAffixResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListBlueAffixes extends ListRecords
{
    protected static string $resource = BlueAffixResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('exportBlueAffixes')
                ->label('导出 blue_affixes_v1.json')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action('exportBlueAffixes'),
        ];
    }

    public function exportBlueAffixes(): ?BinaryFileResponse
    {
        Artisan::call('game:export-blue-affixes');

        $path = storage_path('app/exports/blue_affixes_v1.json');
        if (! is_file($path)) {
            Notification::make()
                ->title('导出失败')
                ->body('未找到导出文件：storage/app/exports/blue_affixes_v1.json')
                ->danger()
                ->send();

            return null;
        }

        Notification::make()
            ->title('导出成功')
            ->body('已生成：storage/app/exports/blue_affixes_v1.json')
            ->success()
            ->send();

        return response()->download($path, 'blue_affixes_v1.json', [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }
}
