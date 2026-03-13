<?php

namespace App\Filament\Resources\ShopGoodsResource\Pages;

use App\Filament\Resources\ShopGoodsResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListShopGoods extends ListRecords
{
    protected static string $resource = ShopGoodsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('exportShopGoods')
                ->label('导出 shop_goods_v1.json')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action('exportShopGoods'),
        ];
    }

    public function exportShopGoods(): ?BinaryFileResponse
    {
        Artisan::call('game:export-shop-goods');

        $path = storage_path('app/exports/shop_goods_v1.json');
        if (! is_file($path)) {
            Notification::make()
                ->title('导出失败')
                ->body('未找到导出文件：storage/app/exports/shop_goods_v1.json')
                ->danger()
                ->send();

            return null;
        }

        Notification::make()
            ->title('导出成功')
            ->body('已生成：storage/app/exports/shop_goods_v1.json')
            ->success()
            ->send();

        return response()->download($path, 'shop_goods_v1.json', [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }
}
