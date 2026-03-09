<?php

namespace App\Filament\Resources\MonsterResource\Pages;

use App\Filament\Resources\MonsterResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListMonsters extends ListRecords
{
    protected static string $resource = MonsterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('exportMonsters')
                ->label('导出 monsters.json')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action('exportMonsters'),
        ];
    }

    public function exportMonsters(): ?BinaryFileResponse
    {
        Artisan::call('game:export-monsters');

        $path = storage_path('app/exports/monsters.json');
        if (! is_file($path)) {
            Notification::make()
                ->title('导出失败')
                ->body('未找到导出文件：storage/app/exports/monsters.json')
                ->danger()
                ->send();

            return null;
        }

        Notification::make()
            ->title('导出成功')
            ->body('已生成：storage/app/exports/monsters.json')
            ->success()
            ->send();

        return response()->download($path, 'monsters.json', [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }
}
