<?php

namespace App\Filament\Resources\ItemResource\Pages;

use App\Filament\Resources\ItemResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListItems extends ListRecords
{
    protected static string $resource = ItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('exportItems')
                ->label('导出 items.json')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action('exportItems'),
        ];
    }

    public function exportItems(): ?BinaryFileResponse
    {
        Artisan::call('game:export-items');

        $path = storage_path('app/exports/items.json');
        if (! is_file($path)) {
            Notification::make()
                ->title('导出失败')
                ->body('未找到导出文件：storage/app/exports/items.json')
                ->danger()
                ->send();

            return null;
        }

        Notification::make()
            ->title('导出成功')
            ->body('已生成：storage/app/exports/items.json')
            ->success()
            ->send();

        return response()->download($path, 'items.json', [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }
}
