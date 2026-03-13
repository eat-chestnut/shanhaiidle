<?php

namespace App\Filament\Resources\MilestoneResource\Pages;

use App\Filament\Resources\MilestoneResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListMilestones extends ListRecords
{
    protected static string $resource = MilestoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('exportMilestones')
                ->label('导出 progression_milestones_v1.json')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action('exportMilestones'),
        ];
    }

    public function exportMilestones(): ?BinaryFileResponse
    {
        Artisan::call('game:export-progression-milestones');

        $path = storage_path('app/exports/progression_milestones_v1.json');
        if (! is_file($path)) {
            Notification::make()
                ->title('导出失败')
                ->body('未找到导出文件：storage/app/exports/progression_milestones_v1.json')
                ->danger()
                ->send();

            return null;
        }

        Notification::make()
            ->title('导出成功')
            ->body('已生成：storage/app/exports/progression_milestones_v1.json')
            ->success()
            ->send();

        return response()->download($path, 'progression_milestones_v1.json', [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }
}
