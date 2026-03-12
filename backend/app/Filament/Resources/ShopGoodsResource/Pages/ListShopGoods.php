<?php

namespace App\Filament\Resources\ShopGoodsResource\Pages;

use App\Filament\Resources\ShopGoodsResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListShopGoods extends ListRecords
{
    protected static string $resource = ShopGoodsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
