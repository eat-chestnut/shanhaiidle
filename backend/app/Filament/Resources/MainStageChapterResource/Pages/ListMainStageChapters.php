<?php

namespace App\Filament\Resources\MainStageChapterResource\Pages;

use App\Filament\Resources\MainStageChapterResource;
use App\Services\MainStageModuleExportService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListMainStageChapters extends ListRecords
{
    protected static string $resource = MainStageChapterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('export_main_stage_module')
                ->label('导出主线模块')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function (): void {
                    $result = app(MainStageModuleExportService::class)->export();

                    Notification::make()
                        ->title('主线模块导出成功')
                        ->body(sprintf('已生成：%s', $result['latest_path']))
                        ->success()
                        ->send();
                }),
        ];
    }
}
