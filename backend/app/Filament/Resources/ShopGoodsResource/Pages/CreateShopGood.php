<?php

namespace App\Filament\Resources\ShopGoodsResource\Pages;

use App\Filament\Resources\ShopGoodsResource;
use Filament\Resources\Pages\CreateRecord;

class CreateShopGood extends CreateRecord
{
    protected static string $resource = ShopGoodsResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return ShopGoodsResource::normalizeFormDataOrFail($data);
    }
}
