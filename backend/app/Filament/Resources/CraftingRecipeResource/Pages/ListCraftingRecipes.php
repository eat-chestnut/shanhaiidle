<?php

namespace App\Filament\Resources\CraftingRecipeResource\Pages;

use App\Filament\Resources\CraftingRecipeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCraftingRecipes extends ListRecords
{
    protected static string $resource = CraftingRecipeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
