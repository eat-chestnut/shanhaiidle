<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ConfigBundleResource\Pages;
use App\Models\AppSetting;
use App\Models\ConfigBundle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ConfigBundleResource extends Resource
{
    protected static ?string $model = ConfigBundle::class;
    protected static ?string $navigationIcon = 'heroicon-o-archive-box-arrow-down';
    protected static ?string $navigationLabel = '配置包管理';
    protected static ?string $pluralModelLabel = '配置包';
    protected static ?string $modelLabel = '配置包';
    protected static ?string $navigationGroup = '系统配置';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('bundle_id')->label('包 ID')->searchable()->sortable(),
                TextColumn::make('created_at')->label('创建时间')->dateTime('Y-m-d H:i:s')->sortable(),
                TextColumn::make('sha256')->label('SHA256')->copyable()->searchable()->limit(24)->tooltip(fn (ConfigBundle $record): string => (string) $record->sha256),
                IconColumn::make('is_latest')
                    ->label('当前最新')
                    ->boolean()
                    ->getStateUsing(fn (ConfigBundle $record): bool => $record->bundle_id === trim((string) AppSetting::getValue('latest_bundle_id', ''))),
                TextColumn::make('updated_at')->label('更新时间')->since()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_latest')
                    ->label('仅看最新')
                    ->queries(
                        true: fn ($query) => $query->where('bundle_id', trim((string) AppSetting::getValue('latest_bundle_id', ''))),
                        false: fn ($query) => $query->where('bundle_id', '!=', trim((string) AppSetting::getValue('latest_bundle_id', ''))),
                        blank: fn ($query) => $query,
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('downloadManifest')
                    ->label('下载 manifest')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn (ConfigBundle $record) => response()->download(
                        storage_path('app/' . ltrim((string) $record->manifest_path, '/')),
                        sprintf('manifest_%s.json', $record->bundle_id),
                        ['Content-Type' => 'application/json; charset=UTF-8']
                    )),
                Tables\Actions\Action::make('setLatest')
                    ->label('设为最新')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (ConfigBundle $record): void {
                        AppSetting::setValue('latest_bundle_id', $record->bundle_id);
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConfigBundles::route('/'),
        ];
    }
}
