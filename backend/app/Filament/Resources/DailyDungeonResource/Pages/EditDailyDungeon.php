<?php

namespace App\Filament\Resources\DailyDungeonResource\Pages;

use App\Filament\Resources\DailyDungeonLevelResource;
use App\Filament\Resources\DailyDungeonResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditDailyDungeon extends EditRecord
{
    protected static string $resource = DailyDungeonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewLevels')
                ->label('查看等级配置')
                ->url(fn (): string => DailyDungeonLevelResource::getUrl('index')),
        ];
    }
}
