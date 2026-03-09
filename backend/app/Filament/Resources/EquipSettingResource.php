<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EquipSettingResource\Pages;
use App\Models\EquipSetting;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class EquipSettingResource extends Resource
{
    protected static ?string $model = EquipSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationLabel = '装备全局设置';

    protected static ?string $pluralModelLabel = '装备设置';

    protected static ?string $modelLabel = '装备设置';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('key')
                    ->label('配置键')
                    ->required()
                    ->maxLength(64)
                    ->default('socket_weights')
                    ->disabled()
                    ->dehydrated()
                    ->helperText('固定为 socket_weights'),
                TextInput::make('value.0')->label('0孔权重')->integer()->minValue(0)->required()->default(60),
                TextInput::make('value.1')->label('1孔权重')->integer()->minValue(0)->required()->default(25),
                TextInput::make('value.2')->label('2孔权重')->integer()->minValue(0)->required()->default(10),
                TextInput::make('value.3')->label('3孔权重')->integer()->minValue(0)->required()->default(4),
                TextInput::make('value.4')->label('4孔权重')->integer()->minValue(0)->required()->default(1),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')->label('键')->searchable()->sortable(),
                TextColumn::make('weights')
                    ->label('孔位权重')
                    ->state(fn (EquipSetting $record): string => static::weightsSummary($record->value)),
                TextColumn::make('updated_at')->label('更新时间')->dateTime('Y-m-d H:i:s')->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('key');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEquipSettings::route('/'),
            'create' => Pages\CreateEquipSetting::route('/create'),
            'edit' => Pages\EditEquipSetting::route('/{record}/edit'),
        ];
    }

    public static function normalizeSocketWeights(array $data): array
    {
        $value = $data['value'] ?? [];
        $weights = [
            '0' => max(0, (int) ($value['0'] ?? 0)),
            '1' => max(0, (int) ($value['1'] ?? 0)),
            '2' => max(0, (int) ($value['2'] ?? 0)),
            '3' => max(0, (int) ($value['3'] ?? 0)),
            '4' => max(0, (int) ($value['4'] ?? 0)),
        ];

        if (array_sum($weights) <= 0) {
            throw ValidationException::withMessages([
                'value.0' => '0~4孔权重总和必须大于0。',
            ]);
        }

        $data['value'] = $weights;

        return $data;
    }

    private static function weightsSummary(mixed $value): string
    {
        $weights = is_array($value) ? $value : [];

        return sprintf(
            '0:%d 1:%d 2:%d 3:%d 4:%d',
            (int) ($weights['0'] ?? 0),
            (int) ($weights['1'] ?? 0),
            (int) ($weights['2'] ?? 0),
            (int) ($weights['3'] ?? 0),
            (int) ($weights['4'] ?? 0),
        );
    }
}
