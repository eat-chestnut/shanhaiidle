<?php

namespace App\Filament\Resources\PurpleAffixResource\Pages;

use App\Filament\Resources\PurpleAffixResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPurpleAffixes extends ListRecords
{
    protected static string $resource = PurpleAffixResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
