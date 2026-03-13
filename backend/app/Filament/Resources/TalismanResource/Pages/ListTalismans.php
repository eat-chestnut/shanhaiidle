<?php

namespace App\Filament\Resources\TalismanResource\Pages;

use App\Filament\Resources\TalismanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTalismans extends ListRecords
{
    protected static string $resource = TalismanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
