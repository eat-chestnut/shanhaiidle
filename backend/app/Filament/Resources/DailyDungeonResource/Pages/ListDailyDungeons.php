<?php

namespace App\Filament\Resources\DailyDungeonResource\Pages;

use App\Filament\Resources\DailyDungeonResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListDailyDungeons extends ListRecords
{
    protected static string $resource = DailyDungeonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('exportDailyDungeons')
                ->label('导出 daily_dungeons_v1.json')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action('exportDailyDungeons'),
        ];
    }

    public function exportDailyDungeons(): ?BinaryFileResponse
    {
        Artisan::call('game:export-daily-dungeons');

        $path = storage_path('app/exports/daily_dungeons_v1.json');
        if (! is_file($path)) {
            Notification::make()
                ->title('导出失败')
                ->body('未找到导出文件：storage/app/exports/daily_dungeons_v1.json')
                ->danger()
                ->send();

            return null;
        }

        Notification::make()
            ->title('导出成功')
            ->body('已生成：storage/app/exports/daily_dungeons_v1.json')
            ->success()
            ->send();

        return response()->download($path, 'daily_dungeons_v1.json', [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }
}
