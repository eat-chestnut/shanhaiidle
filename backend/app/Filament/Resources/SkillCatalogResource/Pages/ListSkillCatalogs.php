<?php

namespace App\Filament\Resources\SkillCatalogResource\Pages;

use App\Filament\Resources\SkillCatalogResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListSkillCatalogs extends ListRecords
{
    protected static string $resource = SkillCatalogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('exportSkillsCatalog')
                ->label('导出 skills_catalog.json')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action('exportSkillsCatalog'),
        ];
    }

    public function exportSkillsCatalog(): ?BinaryFileResponse
    {
        Artisan::call('game:export-skills-catalog');

        $path = storage_path('app/exports/skills_catalog.json');
        if (! is_file($path)) {
            Notification::make()
                ->title('导出失败')
                ->body('未找到导出文件：storage/app/exports/skills_catalog.json')
                ->danger()
                ->send();

            return null;
        }

        Notification::make()
            ->title('导出成功')
            ->body('已生成：storage/app/exports/skills_catalog.json')
            ->success()
            ->send();

        return response()->download($path, 'skills_catalog.json', [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }
}
