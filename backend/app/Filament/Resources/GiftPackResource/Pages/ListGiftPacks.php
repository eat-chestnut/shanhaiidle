<?php

namespace App\Filament\Resources\GiftPackResource\Pages;

use App\Filament\Resources\GiftPackResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListGiftPacks extends ListRecords
{
    protected static string $resource = GiftPackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('exportGiftPackModule')
                ->label('导出 gift_pack_module_v1.json')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action('exportGiftPackModule'),
        ];
    }

    public function exportGiftPackModule(): ?BinaryFileResponse
    {
        Artisan::call('game:export-gift-pack-module');

        $path = storage_path('app/exports/gift_pack_module_v1.json');
        if (! is_file($path)) {
            Notification::make()
                ->title('导出失败')
                ->body('未找到导出文件：storage/app/exports/gift_pack_module_v1.json')
                ->danger()
                ->send();

            return null;
        }

        Notification::make()
            ->title('导出成功')
            ->body('已生成：storage/app/exports/gift_pack_module_v1.json')
            ->success()
            ->send();

        return response()->download($path, 'gift_pack_module_v1.json', [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }
}
