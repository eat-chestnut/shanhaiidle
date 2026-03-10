<?php

namespace App\Filament\Resources\BlueAffixResource\Pages;

use App\Filament\Resources\BlueAffixResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBlueAffix extends EditRecord
{
    protected static string $resource = BlueAffixResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
