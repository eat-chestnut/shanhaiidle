<?php

namespace App\Filament\Resources\PurpleAffixResource\Pages;

use App\Filament\Resources\PurpleAffixResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPurpleAffix extends EditRecord
{
    protected static string $resource = PurpleAffixResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
