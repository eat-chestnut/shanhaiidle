<?php

namespace App\Filament\Resources\BossCoreResource\Pages;

use App\Filament\Resources\BossCoreResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListBossCores extends ListRecords
{
    protected static string $resource = BossCoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('exportBossCoreModule')
                ->label('导出 boss_core_module_v1.json')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action('exportBossCoreModule'),
        ];
    }

    public function exportBossCoreModule(): ?BinaryFileResponse
    {
        Artisan::call('game:export-boss-core-module');

        $path = storage_path('app/exports/boss_core_module_v1.json');
        if (! is_file($path)) {
            Notification::make()
                ->title('导出失败')
                ->body('未找到导出文件：storage/app/exports/boss_core_module_v1.json')
                ->danger()
                ->send();

            return null;
        }

        Notification::make()
            ->title('导出成功')
            ->body('已生成：storage/app/exports/boss_core_module_v1.json')
            ->success()
            ->send();

        return response()->download($path, 'boss_core_module_v1.json', [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }
}
