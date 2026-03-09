<?php

namespace App\Filament\Resources\ConfigBundleResource\Pages;

use App\Filament\Resources\ConfigBundleResource;
use App\Models\AppSetting;
use App\Models\ConfigBundle;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListConfigBundles extends ListRecords
{
    protected static string $resource = ConfigBundleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('rollbackPrevious')
                ->label('回滚到上一版')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->requiresConfirmation()
                ->action(fn () => $this->rollbackPrevious()),
        ];
    }

    public function rollbackPrevious(): void
    {
        $records = ConfigBundle::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        if ($records->count() < 2) {
            Notification::make()
                ->title('没有可回滚版本')
                ->warning()
                ->send();

            return;
        }

        $latestBundleId = trim((string) AppSetting::getValue('latest_bundle_id', ''));
        $currentIndex = $records->search(
            fn (ConfigBundle $record): bool => $record->bundle_id === $latestBundleId
        );

        $target = null;
        if ($currentIndex === false) {
            $target = $records->get(1);
        } else {
            $target = $records->get((int) $currentIndex + 1);
        }

        if (! $target instanceof ConfigBundle) {
            Notification::make()
                ->title('没有可回滚版本')
                ->warning()
                ->send();

            return;
        }

        AppSetting::setValue('latest_bundle_id', $target->bundle_id);

        Notification::make()
            ->title('回滚成功')
            ->body("已回滚到 {$target->bundle_id}")
            ->success()
            ->send();
    }
}

