<?php

namespace App\Filament\Resources\BlueAffixResource\Pages;

use App\Filament\Resources\BlueAffixResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBlueAffixes extends ListRecords
{
    protected static string $resource = BlueAffixResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
