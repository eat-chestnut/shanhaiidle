<?php

namespace App\Filament\Resources\ShopGoodsResource\Pages;

use App\Filament\Resources\ShopGoodsResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditShopGood extends EditRecord
{
    protected static string $resource = ShopGoodsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
